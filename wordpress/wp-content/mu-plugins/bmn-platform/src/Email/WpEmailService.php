<?php

declare(strict_types=1);

namespace BMN\Platform\Email;

/**
 * WordPress-backed email service.
 *
 * Sends HTML emails via wp_mail() with consistent branding, a unified footer,
 * and optional per-recipient agent personalisation.
 */
class WpEmailService implements EmailService
{
    /** @var array|null Lazily-loaded configuration. */
    private ?array $config = null;

    // -------------------------------------------------------------------------
    //  Public API
    // -------------------------------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $config = $this->getConfig();

        $fromEmail = $options['from_email'] ?? $config['from_email'];
        $fromName  = $options['from_name']  ?? $config['from_name'];

        $headers   = [];
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = sprintf('From: %s <%s>', $fromName, $fromEmail);

        if (! empty($options['cc'])) {
            $headers[] = sprintf('Cc: %s', $options['cc']);
        }

        if (! empty($options['bcc'])) {
            $headers[] = sprintf('Bcc: %s', $options['bcc']);
        }

        if (! empty($options['reply_to'])) {
            $headers[] = sprintf('Reply-To: %s', $options['reply_to']);
        }

        $wrappedBody = $this->buildHtmlWrapper($body, $options);

        return wp_mail($to, $subject, $wrappedBody, $headers);
    }

    /**
     * {@inheritDoc}
     */
    public function sendTemplate(
        string $to,
        string $subject,
        string $template,
        array $variables = [],
        array $options = []
    ): bool {
        $interpolatedBody = $this->interpolate($template, $variables);

        return $this->send($to, $subject, $interpolatedBody, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function getFromHeader(int $recipientUserId = 0): string
    {
        $config = $this->getConfig();
        $name   = $config['from_name'];
        $email  = $config['from_email'];

        if ($recipientUserId > 0 && function_exists('get_user_meta')) {
            $agentId = (int) get_user_meta($recipientUserId, 'bmn_assigned_agent_id', true);

            if ($agentId > 0) {
                $agentUser = function_exists('get_userdata') ? get_userdata($agentId) : false;

                if ($agentUser && ! empty($agentUser->user_email)) {
                    $email = $agentUser->user_email;
                    $name  = trim($agentUser->first_name . ' ' . $agentUser->last_name);

                    if ($name === '') {
                        $name = $agentUser->display_name ?: $config['from_name'];
                    }
                }
            }
        }

        return sprintf('"%s" <%s>', $name, $email);
    }

    /**
     * Build the unified email footer HTML.
     *
     * @param array $options Optional keys: unsubscribe_url, context, show_social, show_app_download.
     * @return string Footer HTML.
     */
    public function buildFooter(array $options = []): string
    {
        $config = $this->getConfig();

        $siteUrl  = function_exists('home_url') ? home_url('/') : 'https://bmnboston.com/';
        $siteName = $config['from_name'];

        $showSocial      = $options['show_social']       ?? $config['footer_show_social'];
        $showAppDownload = $options['show_app_download']  ?? $config['footer_show_app_download'];
        $unsubscribeUrl  = $options['unsubscribe_url']    ?? '';
        $context         = $options['context']            ?? 'general';

        $footer = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #e0e0e0;margin-top:30px;padding-top:20px;">';
        $footer .= '<tr><td style="text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#888888;line-height:1.6;">';

        // Site link
        $footer .= sprintf(
            '<a href="%s" style="color:#2b6cb0;text-decoration:none;">%s</a>',
            esc_url($siteUrl),
            esc_html($siteName)
        );

        // Social media placeholder
        if ($showSocial) {
            $footer .= '<br style="line-height:24px;">';
            $footer .= '<!-- social media icons placeholder -->';
            $footer .= '<span style="font-size:12px;color:#aaaaaa;">Follow us on social media</span>';
        }

        // App download link
        if ($showAppDownload && ! empty($config['app_store_url'])) {
            $footer .= '<br style="line-height:24px;">';
            $footer .= sprintf(
                '<a href="%s" style="color:#2b6cb0;text-decoration:none;font-size:12px;">Download the BMN Boston app</a>',
                esc_url($config['app_store_url'])
            );
        }

        // Context-specific text
        $footer .= '<br style="line-height:24px;">';
        switch ($context) {
            case 'property_alert':
                $footer .= '<span style="font-size:11px;color:#aaaaaa;">You are receiving this because you have active property alerts.</span>';
                break;
            case 'appointment':
                $footer .= '<span style="font-size:11px;color:#aaaaaa;">This is a transactional email related to your appointment.</span>';
                break;
            default:
                $footer .= sprintf(
                    '<span style="font-size:11px;color:#aaaaaa;">Sent by %s</span>',
                    esc_html($siteName)
                );
                break;
        }

        // Unsubscribe link
        if ($unsubscribeUrl !== '') {
            $footer .= '<br>';
            $footer .= sprintf(
                '<a href="%s" style="color:#888888;text-decoration:underline;font-size:11px;">Unsubscribe</a>',
                esc_url($unsubscribeUrl)
            );
        }

        // Copyright year via current_time (never date('Y'))
        $year = function_exists('current_time')
            ? date('Y', (int) current_time('timestamp'))
            : gmdate('Y');

        $footer .= '<br style="line-height:20px;">';
        $footer .= sprintf(
            '<span style="font-size:11px;color:#cccccc;">&copy; %s %s. All rights reserved.</span>',
            esc_html($year),
            esc_html($siteName)
        );

        $footer .= '</td></tr></table>';

        return $footer;
    }

    // -------------------------------------------------------------------------
    //  Configuration
    // -------------------------------------------------------------------------

    /**
     * Lazily load and cache email configuration.
     *
     * @return array Merged configuration with defaults.
     */
    private function getConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $stored = function_exists('get_option')
            ? (array) get_option('bmn_email_settings', [])
            : [];

        $defaults = [
            'from_email'            => function_exists('get_bloginfo') ? get_bloginfo('admin_email') : 'admin@bmnboston.com',
            'from_name'             => function_exists('get_bloginfo') ? get_bloginfo('name') : 'BMN Boston',
            'footer_show_social'    => true,
            'footer_show_app_download' => true,
            'app_store_url'         => 'https://apps.apple.com/us/app/bmn-boston/id6745724401',
        ];

        $this->config = array_merge($defaults, $stored);

        return $this->config;
    }

    // -------------------------------------------------------------------------
    //  Private helpers
    // -------------------------------------------------------------------------

    /**
     * Replace {{key}} placeholders with values from the variables array.
     *
     * Unknown placeholders are left untouched.
     *
     * @param string $template  Template string with {{variable}} placeholders.
     * @param array  $variables Key-value map of replacements.
     * @return string Interpolated string.
     */
    private function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{(\s*[\w.]+\s*)\}\}/',
            static function (array $matches) use ($variables): string {
                $key = trim($matches[1]);

                return array_key_exists($key, $variables)
                    ? (string) $variables[$key]
                    : $matches[0]; // leave unknown placeholders as-is
            },
            $template
        ) ?? $template;
    }

    /**
     * Wrap email body content in a full HTML document with the unified footer.
     *
     * Uses table-based layout and inline styles for maximum email-client compatibility.
     *
     * @param string $body    The HTML body content.
     * @param array  $options Options forwarded to buildFooter() (unsubscribe_url, context, etc.).
     * @return string Complete HTML document string.
     */
    private function buildHtmlWrapper(string $body, array $options = []): string
    {
        $config   = $this->getConfig();
        $siteName = esc_html($config['from_name']);
        $footer   = $this->buildFooter($options);

        $html  = '<!DOCTYPE html>';
        $html .= '<html lang="en">';
        $html .= '<head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= sprintf('<title>%s</title>', $siteName);
        $html .= '</head>';
        $html .= '<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">';

        // Outer wrapper table
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f4f7;">';
        $html .= '<tr><td align="center" style="padding:20px 10px;">';

        // Inner content table
        $html .= '<table width="600" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;border-radius:4px;overflow:hidden;max-width:600px;width:100%;">';

        // Body
        $html .= '<tr><td style="padding:30px 40px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#333333;line-height:1.6;">';
        $html .= $body;
        $html .= '</td></tr>';

        // Footer
        $html .= '<tr><td style="padding:0 40px 30px 40px;">';
        $html .= $footer;
        $html .= '</td></tr>';

        $html .= '</table>'; // end inner
        $html .= '</td></tr></table>'; // end outer
        $html .= '</body></html>';

        return $html;
    }
}
