<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Schools
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the schools plugin autoloader.
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

// Schools constants.
if (! defined('BMN_SCHOOLS_VERSION')) {
    define('BMN_SCHOOLS_VERSION', '2.0.0-dev');
}

if (! defined('BMN_SCHOOLS_PATH')) {
    define('BMN_SCHOOLS_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_SCHOOLS_URL')) {
    define('BMN_SCHOOLS_URL', 'https://bmnboston.com/wp-content/plugins/bmn-schools/');
}

// Schools autoloader.
$schoolsAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($schoolsAutoloader)) {
    require_once $schoolsAutoloader;
}

// Add esc_like method to wpdb stub if missing (used by SchoolRepository::autocomplete).
if (class_exists('wpdb') && ! method_exists('wpdb', 'esc_like')) {
    // Cannot add method to existing class; tests that need esc_like
    // should use a mock or extend wpdb in the test.
}

// WordPress dbDelta stub for migration testing.
if (! function_exists('dbDelta')) {
    function dbDelta(string|array $queries = '', bool $execute = true): array
    {
        return [];
    }
}
