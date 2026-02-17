<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN CMA
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the CMA plugin autoloader and adds missing stubs.
 */

// WP_User stub — must be defined before platform bootstrap so that
// wp_set_current_user / wp_get_current_user return WP_User instances
// (RestController::getCurrentUser() has a ?WP_User return type).
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

// Override wp_set/get_current_user to return WP_User objects.
if (! function_exists('wp_set_current_user')) {
    function wp_set_current_user(int $id, string $name = ''): WP_User
    {
        $user = new WP_User($id);
        $user->user_login = $name;
        $user->roles = ['subscriber'];
        $GLOBALS['current_user'] = $user;
        return $user;
    }
}

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user(): WP_User
    {
        if (isset($GLOBALS['current_user']) && $GLOBALS['current_user'] instanceof WP_User) {
            return $GLOBALS['current_user'];
        }
        return new WP_User(0);
    }
}

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

// CMA constants.
if (! defined('BMN_CMA_VERSION')) {
    define('BMN_CMA_VERSION', '2.0.0-dev');
}

if (! defined('BMN_CMA_PATH')) {
    define('BMN_CMA_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_CMA_URL')) {
    define('BMN_CMA_URL', 'https://bmnboston.com/wp-content/plugins/bmn-cma/');
}

// CMA autoloader.
$cmaAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($cmaAutoloader)) {
    require_once $cmaAutoloader;
}

// WordPress dbDelta stub for migration testing.
if (! function_exists('dbDelta')) {
    function dbDelta(string|array $queries = '', bool $execute = true): array
    {
        return [];
    }
}

// wp_json_encode stub.
if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
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
        return 'https://bmnboston.com/wp-content/plugins/bmn-cma/';
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
