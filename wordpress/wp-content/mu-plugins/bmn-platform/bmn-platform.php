<?php

declare(strict_types=1);

/**
 * Plugin Name: BMN Platform
 * Plugin URI:  https://bmnboston.com
 * Description: Shared foundation services for all BMN plugins (DI container, auth, caching, database, HTTP, etc.)
 * Version:     2.0.0-dev
 * Author:      BMN Boston
 * Author URI:  https://bmnboston.com
 * License:     Proprietary
 * Requires PHP: 8.1
 * Network:     true
 */

// Prevent direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Enforce PHP 8.1+.
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>BMN Platform</strong> requires PHP 8.1 or higher. ';
        echo 'You are running PHP ' . esc_html(PHP_VERSION) . '.';
        echo '</p></div>';
    });
    return;
}

// Plugin constants.
define('BMN_PLATFORM_VERSION', '2.0.0-dev');
define('BMN_PLATFORM_DIR', __DIR__);
define('BMN_PLATFORM_FILE', __FILE__);

// Load Composer autoloader.
$autoloader = BMN_PLATFORM_DIR . '/vendor/autoload.php';
if (! file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>BMN Platform</strong>: Composer dependencies not installed. ';
        echo 'Run <code>composer install</code> in <code>' . esc_html(BMN_PLATFORM_DIR) . '</code>.';
        echo '</p></div>';
    });
    return;
}
require_once $autoloader;

// Boot the application.
$app = \BMN\Platform\Core\Application::getInstance();
$app->boot();

// Register platform REST API routes.
add_action('rest_api_init', static function () use ($app): void {
    $container = $app->getContainer();

    $healthController = new \BMN\Platform\Http\HealthController($container);
    $healthController->registerRoutes();
});

/**
 * Signal to all dependent plugins that the platform is ready.
 *
 * Plugins should hook into this action to access platform services:
 *
 *     add_action('bmn_platform_loaded', function (\BMN\Platform\Core\Application $app) {
 *         $container = $app->getContainer();
 *         // ...
 *     });
 *
 * @param \BMN\Platform\Core\Application $app The booted application instance.
 */
do_action('bmn_platform_loaded', $app);
