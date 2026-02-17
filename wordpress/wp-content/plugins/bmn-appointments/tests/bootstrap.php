<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Appointments
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the appointments plugin autoloader and adds missing stubs.
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

// WP_User stub (same as bmn-users bootstrap).
if (! class_exists('WP_User')) {
    class WP_User
    {
        public int $ID = 0;
        public string $user_login = '';
        public string $user_email = '';
        public string $user_pass = '';
        public string $display_name = '';
        public string $first_name = '';
        public string $last_name = '';
        public array $roles = [];

        public function __construct(int $id = 0)
        {
            $this->ID = $id;
        }

        public function __get(string $name): mixed
        {
            return $this->$name ?? '';
        }

        public function __isset(string $name): bool
        {
            return isset($this->$name);
        }
    }
}

// Appointments constants.
if (! defined('BMN_APPOINTMENTS_VERSION')) {
    define('BMN_APPOINTMENTS_VERSION', '2.0.0-dev');
}

if (! defined('BMN_APPOINTMENTS_PATH')) {
    define('BMN_APPOINTMENTS_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_APPOINTMENTS_URL')) {
    define('BMN_APPOINTMENTS_URL', 'https://bmnboston.com/wp-content/plugins/bmn-appointments/');
}

// Appointments autoloader.
$appointmentsAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($appointmentsAutoloader)) {
    require_once $appointmentsAutoloader;
}

// WordPress dbDelta stub for migration testing.
if (! function_exists('dbDelta')) {
    function dbDelta(string|array $queries = '', bool $execute = true): array
    {
        return [];
    }
}

// wp_timezone stub.
if (! function_exists('wp_timezone')) {
    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('America/New_York');
    }
}

// wp_next_scheduled stub.
if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []): int|false
    {
        return false;
    }
}

// wp_schedule_event stub.
if (! function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = [], bool $wp_error = false): bool
    {
        return true;
    }
}

// wp_remote_request stub for GoogleCalendarClient.
if (! function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []): array|\WP_Error
    {
        $key = 'wp_remote_response_' . md5($url);
        return $GLOBALS[$key] ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
}

// wp_remote_post stub (may already be defined by platform bootstrap).
if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = []): array|\WP_Error
    {
        $key = 'wp_remote_response_' . md5($url);
        return $GLOBALS[$key] ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
}

// plugin_dir_path stub.
if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return trailingslashit(dirname($file));
    }
}

// plugin_dir_url stub.
if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return 'https://bmnboston.com/wp-content/plugins/bmn-appointments/';
    }
}

// trailingslashit stub.
if (! function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
    }
}
