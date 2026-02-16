<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap for BMN Extractor
 *
 * Loads the platform bootstrap (WP stubs + platform autoloader),
 * then loads the extractor autoloader.
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

// Extractor constants.
if (! defined('BMN_EXTRACTOR_VERSION')) {
    define('BMN_EXTRACTOR_VERSION', '2.0.0-dev');
}

if (! defined('BMN_EXTRACTOR_PATH')) {
    define('BMN_EXTRACTOR_PATH', dirname(__DIR__) . '/');
}

if (! defined('BMN_EXTRACTOR_URL')) {
    define('BMN_EXTRACTOR_URL', 'https://bmnboston.com/wp-content/plugins/bmn-extractor/');
}

// WordPress time constants.
if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Extractor autoloader.
$extractorAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($extractorAutoloader)) {
    require_once $extractorAutoloader;
}

// WP-Cron stubs for CronManager tests.
if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []): int|false
    {
        return $GLOBALS['wp_scheduled_events'][$hook] ?? false;
    }
}

if (! function_exists('wp_schedule_event')) {
    /** @var array<string, int> */
    $GLOBALS['wp_scheduled_events'] = $GLOBALS['wp_scheduled_events'] ?? [];

    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool
    {
        $GLOBALS['wp_scheduled_events'][$hook] = $timestamp;
        return true;
    }
}

if (! function_exists('wp_unschedule_event')) {
    function wp_unschedule_event(int $timestamp, string $hook, array $args = []): bool
    {
        unset($GLOBALS['wp_scheduled_events'][$hook]);
        return true;
    }
}

if (! function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook(string $hook, array $args = []): int
    {
        unset($GLOBALS['wp_scheduled_events'][$hook]);
        return 1;
    }
}

if (! function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
    {
        $GLOBALS['wp_scheduled_events'][$hook] = $timestamp;
        return true;
    }
}

// Admin function stubs.
if (! function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, string $icon_url = '', ?int $position = null): string
    {
        return $menu_slug;
    }
}

if (! function_exists('add_submenu_page')) {
    function add_submenu_page(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, ?callable $callback = null, ?int $position = null): string|false
    {
        return $menu_slug;
    }
}

if (! function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, bool|array $in_footer = false): void {}
}

if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all'): void {}
}

if (! function_exists('wp_localize_script')) {
    function wp_localize_script(string $handle, string $object_name, array $l10n): bool
    {
        return true;
    }
}

if (! function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        return 'https://bmnboston.com/wp-admin/' . $path;
    }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        return 'test_nonce_' . $action;
    }
}

if (! function_exists('check_ajax_referer')) {
    function check_ajax_referer(string $action = '', string $query_arg = '', bool $die = true): int|false
    {
        return 1;
    }
}

if (! function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        return $GLOBALS['wp_current_user_can'] ?? true;
    }
}

if (! function_exists('wp_send_json_success')) {
    function wp_send_json_success(mixed $data = null, ?int $status_code = null): void
    {
        $GLOBALS['wp_json_response'] = ['success' => true, 'data' => $data];
    }
}

if (! function_exists('wp_send_json_error')) {
    function wp_send_json_error(mixed $data = null, ?int $status_code = null): void
    {
        $GLOBALS['wp_json_response'] = ['success' => false, 'data' => $data];
    }
}

if (! function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void
    {
        // No-op in tests.
    }
}

if (! function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return dirname($file) . '/';
    }
}

if (! function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return 'https://bmnboston.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (! function_exists('wp_timezone')) {
    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('America/New_York');
    }
}

// Stub for dbDelta (used by MigrationRunner).
if (! function_exists('dbDelta')) {
    function dbDelta(string|array $queries = '', bool $execute = true): array
    {
        return [];
    }
}

// Create the wp-admin/includes directory structure that MigrationRunner requires.
$upgradeFile = ABSPATH . 'wp-admin/includes/upgrade.php';
if (! file_exists($upgradeFile)) {
    @mkdir(dirname($upgradeFile), 0777, true);
    file_put_contents($upgradeFile, "<?php\n// Stub for tests.\n");
}

// Error log capture for tests.
$GLOBALS['error_log_messages'] = [];

// HTTP response queue for BridgeApiClient testing.
// Tests can set $GLOBALS['wp_remote_default_response'] as a catch-all
// or $GLOBALS['wp_remote_response_' . md5($url)] for per-URL responses.
$GLOBALS['wp_remote_response_queue'] = [];

// Override wp_remote_get to support a default response fallback.
// The platform bootstrap already defines wp_remote_get, but we need to
// provide a way for tests to set a catch-all response. We wrap it by
// checking a default response global AFTER checking the per-URL global.
// Since the function is already defined, we use a global hook pattern instead.
$GLOBALS['wp_remote_get_interceptor'] = null;
