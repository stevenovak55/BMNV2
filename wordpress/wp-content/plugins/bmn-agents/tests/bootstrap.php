<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Agents
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the agents plugin autoloader and adds missing stubs.
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

// WP_User stub.
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

// Agents constants.
if (! defined('BMN_AGENTS_VERSION')) {
    define('BMN_AGENTS_VERSION', '2.0.0-dev');
}

if (! defined('BMN_AGENTS_PATH')) {
    define('BMN_AGENTS_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_AGENTS_URL')) {
    define('BMN_AGENTS_URL', 'https://bmnboston.com/wp-content/plugins/bmn-agents/');
}

// Agents autoloader.
$agentsAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($agentsAutoloader)) {
    require_once $agentsAutoloader;
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

// wp_insert_user stub.
if (! function_exists('wp_insert_user')) {
    function wp_insert_user(array $userdata): int|\WP_Error
    {
        static $nextId = 100;
        return $nextId++;
    }
}

// wp_update_user stub.
if (! function_exists('wp_update_user')) {
    function wp_update_user(array $userdata): int|\WP_Error
    {
        return (int) ($userdata['ID'] ?? 0);
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
        return 'https://bmnboston.com/wp-content/plugins/bmn-agents/';
    }
}

// trailingslashit stub.
if (! function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
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
