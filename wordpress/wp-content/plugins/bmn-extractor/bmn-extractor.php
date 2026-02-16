<?php
declare(strict_types=1);

/**
 * Plugin Name: BMN Extractor
 * Plugin URI: https://bmnboston.com
 * Description: Bridge MLS data extraction pipeline
 * Version: 2.0.0-dev
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: BMN Boston
 * Author URI: https://bmnboston.com
 * License: Proprietary
 * Text Domain: bmn-extractor
 */

namespace BMN\Extractor;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Ensure bmn-platform is loaded.
if (!defined('BMN_PLATFORM_VERSION')) {
    add_action('admin_notices', function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('BMN Extractor requires the BMN Platform mu-plugin.', 'bmn-extractor');
        echo '</p></div>';
    });
    return;
}

// Constants.
define('BMN_EXTRACTOR_VERSION', '2.0.0-dev');
define('BMN_EXTRACTOR_PATH', plugin_dir_path(__FILE__));
define('BMN_EXTRACTOR_URL', plugin_dir_url(__FILE__));

// Autoloader.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Boot plugin when platform is ready.
add_action('bmn_platform_loaded', function (): void {
    // Plugin initialization will go here.
});
