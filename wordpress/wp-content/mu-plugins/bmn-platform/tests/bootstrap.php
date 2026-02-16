<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Platform
 *
 * Sets up the minimum WordPress stubs so that unit tests can run
 * without a full WordPress installation. Integration tests that
 * load the real WordPress environment will skip these stubs because
 * each function/class is only defined if it does not already exist.
 */

// ---------------------------------------------------------------
// 1. WordPress constants
// ---------------------------------------------------------------

if (! defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (! defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (! defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (! defined('WPMU_PLUGIN_DIR')) {
    define('WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins');
}

if (! defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// ---------------------------------------------------------------
// 2. BMN Platform constant
// ---------------------------------------------------------------

if (! defined('BMN_PLATFORM_VERSION')) {
    define('BMN_PLATFORM_VERSION', '2.0.0-dev');
}

if (! defined('BMN_PLATFORM_DIR')) {
    define('BMN_PLATFORM_DIR', dirname(__DIR__));
}

// ---------------------------------------------------------------
// 3. Composer autoloader
// ---------------------------------------------------------------

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (! file_exists($autoloader)) {
    fwrite(
        STDERR,
        "Composer autoloader not found. Run `composer install` in:\n  " . dirname(__DIR__) . "\n"
    );
    exit(1);
}

require_once $autoloader;

// ---------------------------------------------------------------
// 4. WordPress function stubs
// ---------------------------------------------------------------

if (! function_exists('current_time')) {
    /**
     * Retrieve the current time based on specified type.
     *
     * @param string $type 'timestamp', 'mysql', or a PHP date format string.
     * @return int|string
     */
    function current_time(string $type): int|string
    {
        return match ($type) {
            'timestamp', 'U' => time(),
            'mysql' => date('Y-m-d H:i:s'),
            default => date($type),
        };
    }
}

if (! function_exists('esc_html__')) {
    /**
     * Retrieve the translation of $text (stub: returns text as-is).
     */
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('__')) {
    /**
     * Retrieve the translation of $text (stub: returns text as-is).
     */
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html')) {
    /**
     * Escaping for HTML blocks (stub: returns text as-is).
     */
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('add_action')) {
    /** @var array<string, list<array{callback: callable, priority: int, accepted_args: int}>> */
    $GLOBALS['wp_actions'] = $GLOBALS['wp_actions'] ?? [];

    /**
     * Hooks a function to a specific action (stub: stores for testing).
     */
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $GLOBALS['wp_actions'][$hook][] = [
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (! function_exists('add_filter')) {
    /**
     * Hooks a function to a specific filter (stub: no-op).
     */
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        // No-op for unit tests.
    }
}

if (! function_exists('apply_filters')) {
    /**
     * Calls the callback functions that have been added to a filter hook (stub: returns $value unchanged).
     */
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (! function_exists('do_action')) {
    /**
     * Calls the callback functions that have been added to an action hook (stub: no-op).
     */
    function do_action(string $hook, mixed ...$args): void
    {
        // No-op for unit tests.
    }
}

if (! function_exists('wp_json_encode')) {
    /**
     * Encode a variable into JSON, with some sanity checks (stub: wraps json_encode).
     */
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('absint')) {
    /**
     * Return a non-negative integer.
     */
    function absint(mixed $value): int
    {
        return abs(intval($value));
    }
}

if (! function_exists('sanitize_text_field')) {
    /**
     * Sanitizes a string from user input or from the database (stub: trim + strip_tags).
     */
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (! function_exists('esc_sql')) {
    /**
     * Escapes data for use in a MySQL query (stub: wraps addslashes).
     */
    function esc_sql(string $data): string
    {
        return addslashes($data);
    }
}

if (! function_exists('wp_parse_args')) {
    /**
     * Merges user-defined arguments into defaults array (stub).
     *
     * @param array|string $args
     * @param array        $defaults
     * @return array
     */
    function wp_parse_args(array|string $args, array $defaults = []): array
    {
        if (is_string($args)) {
            parse_str($args, $parsed);
            $args = $parsed;
        }
        return array_merge($defaults, $args);
    }
}

// ---------------------------------------------------------------
// 5. WordPress class stubs
// ---------------------------------------------------------------

if (! class_exists('WP_REST_Response')) {
    /**
     * Minimal stub of WordPress WP_REST_Response for unit testing.
     */
    class WP_REST_Response
    {
        private mixed $data;
        private int $status;
        /** @var array<string, string> */
        private array $headers = [];

        /**
         * @param mixed               $data
         * @param int                  $status
         * @param array<string,string> $headers
         */
        public function __construct(mixed $data = null, int $status = 200, array $headers = [])
        {
            $this->data    = $data;
            $this->status  = $status;
            $this->headers = $headers;
        }

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $status): void
        {
            $this->status = $status;
        }

        /** @return array<string, string> */
        public function get_headers(): array
        {
            return $this->headers;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }
    }
}

if (! class_exists('WP_REST_Request')) {
    /**
     * Minimal stub of WordPress WP_REST_Request for unit testing.
     */
    class WP_REST_Request
    {
        /** @var array<string, mixed> */
        private array $params = [];
        private string $method;
        private string $route;
        /** @var array<string, string> */
        private array $headers = [];

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
    }
}

if (! class_exists('WP_Error')) {
    /**
     * Minimal stub of WordPress WP_Error for unit testing.
     */
    class WP_Error
    {
        private string $code;
        private string $message;
        /** @var array<string, mixed> */
        private array $data;

        public function __construct(string $code = '', string $message = '', mixed $data = '')
        {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = is_array($data) ? $data : [];
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /** @return array<string, mixed> */
        public function get_error_data(): array
        {
            return $this->data;
        }
    }
}
