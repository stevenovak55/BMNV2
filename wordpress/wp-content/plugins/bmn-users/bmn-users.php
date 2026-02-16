<?php
declare(strict_types=1);

/**
 * Plugin Name: BMN Users
 * Plugin URI: https://bmnboston.com
 * Description: User authentication, favorites, saved searches, notifications
 * Version: 2.0.0-dev
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: BMN Boston
 * Author URI: https://bmnboston.com
 * License: Proprietary
 * Text Domain: bmn-users
 */

namespace BMN\Users;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Ensure bmn-platform is loaded.
if (!defined('BMN_PLATFORM_VERSION')) {
    add_action('admin_notices', function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('BMN Users requires the BMN Platform mu-plugin.', 'bmn-users');
        echo '</p></div>';
    });
    return;
}

// Constants.
define('BMN_USERS_VERSION', '2.0.0-dev');
define('BMN_USERS_PATH', plugin_dir_path(__FILE__));
define('BMN_USERS_URL', plugin_dir_url(__FILE__));

// Autoloader.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Boot plugin when platform is ready.
add_action('bmn_platform_loaded', function (\BMN\Platform\Core\Application $app): void {
    $container = $app->getContainer();

    $provider = new Provider\UsersServiceProvider();
    $provider->register($container);
    $provider->boot($container);
});
