<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Exclusive Listings
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the Exclusive plugin autoloader and adds missing stubs.
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

// WP_REST_Request stub — extended version with set_body/get_json_params
// for JSON body parsing in controller tests. Must be defined before
// platform bootstrap so its version (without body methods) is skipped.
if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string, mixed> */
        private array $params = [];
        private string $method;
        private string $route;
        /** @var array<string, string> */
        private array $headers = [];
        private string $body = '';

        public function __construct(string $method = 'GET', string $route = '')
        {
            $this->method = $method;
            $this->route  = $route;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        /** @return array<string, mixed> */
        public function get_params(): array
        {
            return $this->params;
        }

        public function get_method(): string
        {
            return $this->method;
        }

        public function get_route(): string
        {
            return $this->route;
        }

        public function get_header(string $key): ?string
        {
            return $this->headers[strtolower($key)] ?? null;
        }

        public function set_header(string $key, string $value): void
        {
            $this->headers[strtolower($key)] = $value;
        }

        public function set_body(string $body): void
        {
            $this->body = $body;
        }

        public function get_body(): string
        {
            return $this->body;
        }

        /** @return array<string, mixed> */
        public function get_json_params(): array
        {
            if ($this->body === '') {
                return [];
            }
            $decoded = json_decode($this->body, true);
            return is_array($decoded) ? $decoded : [];
        }
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

// Exclusive constants.
if (! defined('BMN_EXCLUSIVE_VERSION')) {
    define('BMN_EXCLUSIVE_VERSION', '2.0.0-dev');
}

if (! defined('BMN_EXCLUSIVE_PATH')) {
    define('BMN_EXCLUSIVE_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_EXCLUSIVE_URL')) {
    define('BMN_EXCLUSIVE_URL', 'https://bmnboston.com/wp-content/plugins/bmn-exclusive/');
}

// Exclusive autoloader.
$exclusiveAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($exclusiveAutoloader)) {
    require_once $exclusiveAutoloader;
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
        return 'https://bmnboston.com/wp-content/plugins/bmn-exclusive/';
    }
}

// trailingslashit stub.
if (! function_exists('trailingslashit')) {
    function trailingslashit(string $value): string
    {
        return rtrim($value, '/\\') . '/';
    }
}

// get_option stub.
if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['wp_options'][$option] ?? $default;
    }
}

// update_option stub.
if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value, string|bool $autoload = 'yes'): bool
    {
        $GLOBALS['wp_options'][$option] = $value;
        return true;
    }
}
