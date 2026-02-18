<?php
/**
 * BMN SMTP Configuration.
 *
 * Routes wp_mail() through Mailhog in the Docker dev environment.
 * Uses the WORDPRESS_SMTP_HOST and WORDPRESS_SMTP_PORT constants
 * defined in docker-compose.yml.
 */

add_action('phpmailer_init', static function ($phpmailer): void {
    if (! defined('WORDPRESS_SMTP_HOST') || WORDPRESS_SMTP_HOST === '') {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = WORDPRESS_SMTP_HOST;
    $phpmailer->Port       = defined('WORDPRESS_SMTP_PORT') ? (int) WORDPRESS_SMTP_PORT : 1025;
    $phpmailer->SMTPAuth   = false;
    $phpmailer->SMTPSecure = '';
});

// Set a valid From address so wp_mail() doesn't fail with "wordpress@localhost".
add_filter('wp_mail_from', static function (): string {
    return 'noreply@bmnboston.com';
});

add_filter('wp_mail_from_name', static function (): string {
    return 'BMN Boston Real Estate';
});
