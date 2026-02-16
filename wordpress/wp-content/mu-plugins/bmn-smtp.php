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
    $phpmailer->Host     = WORDPRESS_SMTP_HOST;
    $phpmailer->Port     = defined('WORDPRESS_SMTP_PORT') ? (int) WORDPRESS_SMTP_PORT : 1025;
    $phpmailer->SMTPAuth = false;
    $phpmailer->SMTPSecure = '';
});
