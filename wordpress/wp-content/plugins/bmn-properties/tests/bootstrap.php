<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Properties
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the properties plugin autoloader.
 */

// Load platform bootstrap (WordPress stubs + platform classes).
$platformBootstrap = dirname(__DIR__, 3) . '/mu-plugins/bmn-platform/tests/bootstrap.php';

if (file_exists($platformBootstrap)) {
    require_once $platformBootstrap;
} else {
    // Fallback: define minimum WP stubs inline.
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('BMN_PLATFORM_VERSION')) {
        define('BMN_PLATFORM_VERSION', '2.0.0-dev');
    }
}

// Properties constants.
if (! defined('BMN_PROPERTIES_VERSION')) {
    define('BMN_PROPERTIES_VERSION', '2.0.0-dev');
}

if (! defined('BMN_PROPERTIES_PATH')) {
    define('BMN_PROPERTIES_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_PROPERTIES_URL')) {
    define('BMN_PROPERTIES_URL', 'https://bmnboston.com/wp-content/plugins/bmn-properties/');
}

// Properties autoloader.
$propertiesAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($propertiesAutoloader)) {
    require_once $propertiesAutoloader;
}
