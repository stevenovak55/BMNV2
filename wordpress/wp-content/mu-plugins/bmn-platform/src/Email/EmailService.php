<?php

declare(strict_types=1);

namespace BMN\Platform\Email;

/**
 * Email service abstraction.
 *
 * Wraps wp_mail() with templating and unified footer branding.
 */
interface EmailService
{
    /**
     * Send an HTML email.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject line.
     * @param string $body    HTML body content (will be wrapped in template).
     * @param array  $options Optional overrides: from_email, from_name, cc, bcc, reply_to, unsubscribe_url, context.
     * @return bool Whether the email was accepted for delivery.
     */
    public function send(string $to, string $subject, string $body, array $options = []): bool;

    /**
     * Send an HTML email using a template with variable interpolation.
     *
     * @param string $to        Recipient email address.
     * @param string $subject   Email subject line.
     * @param string $template  HTML template containing {{variable}} placeholders.
     * @param array  $variables Key-value pairs for placeholder replacement.
     * @param array  $options   Optional overrides (same as send()).
     * @return bool Whether the email was accepted for delivery.
     */
    public function sendTemplate(string $to, string $subject, string $template, array $variables = [], array $options = []): bool;

    /**
     * Build a formatted From header, optionally personalised to the recipient's assigned agent.
     *
     * @param int $recipientUserId WordPress user ID of the recipient (0 = use default).
     * @return string Formatted header value: "Name" <email>
     */
    public function getFromHeader(int $recipientUserId = 0): string;
}
