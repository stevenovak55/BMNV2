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
// 4b. WordPress option/transient stubs (in-memory for testing)
// ---------------------------------------------------------------

/** @var array<string, mixed> In-memory options store for testing. */
$GLOBALS['wp_options'] = $GLOBALS['wp_options'] ?? [];

/** @var array<string, array{value: mixed, expiration: int}> In-memory transient store. */
$GLOBALS['wp_transients'] = $GLOBALS['wp_transients'] ?? [];

if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['wp_options'][$option] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value, string|bool $autoload = 'yes'): bool
    {
        $GLOBALS['wp_options'][$option] = $value;
        return true;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $option): bool
    {
        unset($GLOBALS['wp_options'][$option]);
        return true;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['wp_transients'][$transient] = [
            'value'      => $value,
            'expiration' => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        if (! isset($GLOBALS['wp_transients'][$transient])) {
            return false;
        }
        $entry = $GLOBALS['wp_transients'][$transient];
        if ($entry['expiration'] > 0 && $entry['expiration'] < time()) {
            unset($GLOBALS['wp_transients'][$transient]);
            return false;
        }
        return $entry['value'];
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        unset($GLOBALS['wp_transients'][$transient]);
        return true;
    }
}

if (! function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw'): string
    {
        return match ($show) {
            'url', 'wpurl', 'siteurl', 'home' => 'https://bmnboston.com',
            'name' => 'BMN Boston',
            'admin_email' => 'admin@bmnboston.com',
            'charset' => 'UTF-8',
            default => '',
        };
    }
}

if (! function_exists('is_email')) {
    function is_email(string $email): string|false
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
    }
}

if (! function_exists('wp_hash_password')) {
    function wp_hash_password(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}

if (! function_exists('wp_check_password')) {
    function wp_check_password(string $password, string $hash, int|string $user_id = ''): bool
    {
        return password_verify($password, $hash);
    }
}

if (! function_exists('wp_mail')) {
    /** @var list<array{to: string|string[], subject: string, message: string, headers: string|string[], attachments: string|string[]}> */
    $GLOBALS['wp_mail_log'] = $GLOBALS['wp_mail_log'] ?? [];

    function wp_mail(
        string|array $to,
        string $subject,
        string $message,
        string|array $headers = '',
        string|array $attachments = []
    ): bool {
        $GLOBALS['wp_mail_log'][] = [
            'to'          => $to,
            'subject'     => $subject,
            'message'     => $message,
            'headers'     => $headers,
            'attachments' => $attachments,
        ];
        return true;
    }
}

if (! function_exists('wp_set_current_user')) {
    function wp_set_current_user(int $id, string $name = ''): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_login = $name;
        $user->roles = ['subscriber'];
        $GLOBALS['current_user'] = $user;
        return $user;
    }
}

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object
    {
        if (isset($GLOBALS['current_user'])) {
            return $GLOBALS['current_user'];
        }
        $user = new \stdClass();
        $user->ID = 0;
        $user->user_login = '';
        $user->roles = [];
        return $user;
    }
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) (wp_get_current_user()->ID ?? 0);
    }
}

if (! function_exists('get_user_by')) {
    function get_user_by(string $field, int|string $value): object|false
    {
        // Return a stub user for testing if set in globals.
        $key = "wp_user_{$field}_{$value}";
        return $GLOBALS[$key] ?? false;
    }
}

if (! function_exists('get_userdata')) {
    function get_userdata(int $user_id): object|false
    {
        return get_user_by('id', $user_id);
    }
}

if (! function_exists('register_rest_route')) {
    /** @var array<string, array> Registered REST routes for testing. */
    $GLOBALS['wp_rest_routes'] = $GLOBALS['wp_rest_routes'] ?? [];

    function register_rest_route(string $namespace, string $route, array $args = [], bool $override = false): bool
    {
        $GLOBALS['wp_rest_routes']["{$namespace}/{$route}"] = $args;
        return true;
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}

if (! function_exists('wp_remote_get')) {
    function wp_remote_get(string $url, array $args = []): array|\WP_Error
    {
        // Stub: return from globals if set, otherwise empty response.
        $key = 'wp_remote_response_' . md5($url);
        return $GLOBALS[$key] ?? ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return $response['body'] ?? '';
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int|string
    {
        return $response['response']['code'] ?? 200;
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string
    {
        return 'https://bmnboston.com' . $path;
    }
}

if (! function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false): mixed
    {
        $metaKey = "wp_usermeta_{$user_id}_{$key}";
        if (isset($GLOBALS[$metaKey])) {
            return $single ? $GLOBALS[$metaKey] : [$GLOBALS[$metaKey]];
        }
        return $single ? '' : [];
    }
}

if (! function_exists('add_query_arg')) {
    function add_query_arg(array|string $key, string $value = '', string $url = ''): string
    {
        if (is_array($key)) {
            $url = $value ?: (isset($url) && $url !== '' ? $url : '');
            $queryString = http_build_query($key);
            $separator = str_contains($url, '?') ? '&' : '?';
            return $url . $separator . $queryString;
        }
        $queryString = http_build_query([$key => $value]);
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . $queryString;
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
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

if (! class_exists('wpdb')) {
    /**
     * Minimal stub of WordPress wpdb for unit testing.
     *
     * Provides the interface needed by DatabaseService, QueryBuilder,
     * LoggingService, and migration classes.
     */
    class wpdb
    {
        public string $prefix = 'wp_';
        public string $charset = 'utf8mb4';
        public string $last_error = '';
        public int $insert_id = 0;

        /** @var list<array{sql: string, args: array}> Query log for assertions. */
        public array $queries = [];

        /** @var mixed|null Canned return value for get_var(). */
        public mixed $get_var_result = null;

        /** @var array|null Canned return value for get_results(). */
        public ?array $get_results_result = null;

        /** @var object|null Canned return value for get_row(). */
        public ?object $get_row_result = null;

        /** @var int|bool Canned return value for query(). */
        public int|bool $query_result = 1;

        /** @var bool Canned return value for insert(). */
        public bool $insert_result = true;

        public function prepare(string $query, mixed ...$args): string
        {
            if ($args === []) {
                return $query;
            }
            // Simple placeholder replacement for testing.
            $replacements = [];
            foreach ($args as $arg) {
                if (is_int($arg)) {
                    $replacements[] = (string) $arg;
                } elseif (is_float($arg)) {
                    $replacements[] = sprintf('%.6f', $arg);
                } else {
                    $replacements[] = "'" . addslashes((string) $arg) . "'";
                }
            }
            $i = 0;
            return preg_replace_callback('/%[sdf]/', function () use (&$i, $replacements) {
                return $replacements[$i++] ?? '?';
            }, $query);
        }

        public function query(string $query): int|bool
        {
            $this->queries[] = ['sql' => $query, 'args' => []];
            return $this->query_result;
        }

        public function get_var(?string $query = null, int $x = 0, int $y = 0): mixed
        {
            if ($query !== null) {
                $this->queries[] = ['sql' => $query, 'args' => []];
            }
            return $this->get_var_result;
        }

        public function get_results(?string $query = null, string $output = 'OBJECT'): ?array
        {
            if ($query !== null) {
                $this->queries[] = ['sql' => $query, 'args' => []];
            }
            return $this->get_results_result;
        }

        public function get_row(?string $query = null, string $output = 'OBJECT', int $y = 0): ?object
        {
            if ($query !== null) {
                $this->queries[] = ['sql' => $query, 'args' => []];
            }
            return $this->get_row_result;
        }

        public function insert(string $table, array $data, array|string|null $format = null): int|false
        {
            $this->queries[] = ['sql' => "INSERT INTO {$table}", 'args' => $data];
            $this->insert_id = ($this->insert_id ?: 0) + 1;
            return $this->insert_result ? 1 : false;
        }

        public function update(string $table, array $data, array $where, array|string|null $format = null, array|string|null $where_format = null): int|false
        {
            $this->queries[] = ['sql' => "UPDATE {$table}", 'args' => array_merge($data, $where)];
            return 1;
        }

        public function delete(string $table, array $where, array|string|null $where_format = null): int|false
        {
            $this->queries[] = ['sql' => "DELETE FROM {$table}", 'args' => $where];
            return 1;
        }

        public function get_col(?string $query = null, int $x = 0): array
        {
            if ($query !== null) {
                $this->queries[] = ['sql' => $query, 'args' => []];
            }
            return $this->get_col_result ?? [];
        }

        /** @var array Canned return value for get_col(). */
        public array $get_col_result = [];

        public function get_charset_collate(): string
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    }
}
