<?php
/**
 * BMN Boston Theme Functions
 *
 * @package BMN_Theme
 * @version 2.0.0-dev
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BMN_THEME_VERSION', '2.0.0-dev');
define('BMN_THEME_DIR', get_template_directory());
define('BMN_THEME_URI', get_template_directory_uri());
define('BMN_VITE_DEV_SERVER', 'http://localhost:5173');
define('BMN_VITE_ENTRY_DIR', 'assets/src');
define('BMN_VITE_DIST_DIR', 'assets/dist');

/**
 * Theme setup: register support for various WordPress features.
 */
function bmn_theme_setup(): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    register_nav_menus([
        'primary' => __('Primary Navigation', 'bmn-theme'),
        'footer'  => __('Footer Navigation', 'bmn-theme'),
    ]);
}
add_action('after_setup_theme', 'bmn_theme_setup');

/**
 * Detect whether the Vite dev server is running.
 *
 * Checks by looking for the dev server's response. The result is cached for the
 * duration of the request so we only probe the dev server once.
 *
 * @return bool True if the Vite dev server is reachable.
 */
function bmn_vite_dev_server_running(): bool {
    static $running = null;

    if ($running !== null) {
        return $running;
    }

    // Only attempt dev server detection in non-production environments.
    if (defined('WP_ENVIRONMENT_TYPE') && wp_get_environment_type() === 'production') {
        $running = false;
        return false;
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    $response = @file_get_contents(BMN_VITE_DEV_SERVER . '/@vite/client', false, stream_context_create([
        'http' => [
            'timeout' => 0.5,
        ],
    ]));

    $running = ($response !== false);
    return $running;
}

/**
 * Get the URL for a Vite-managed asset.
 *
 * In development the asset is served from the Vite dev server.  In production
 * the hashed filename is resolved from the Vite manifest.
 *
 * @param string $entry Relative path to the entry file inside the theme
 *                      (e.g. "assets/src/ts/main.ts").
 * @return string|null  The full URL to the asset, or null if it cannot be resolved.
 */
function bmn_vite_asset(string $entry): ?string {
    // --- Development: proxy through Vite dev server ---
    if (bmn_vite_dev_server_running()) {
        return BMN_VITE_DEV_SERVER . '/' . $entry;
    }

    // --- Production: read from manifest ---
    $manifest_path = BMN_THEME_DIR . '/' . BMN_VITE_DIST_DIR . '/.vite/manifest.json';

    if (! file_exists($manifest_path)) {
        // Fallback: try legacy manifest location (Vite < 5).
        $manifest_path = BMN_THEME_DIR . '/' . BMN_VITE_DIST_DIR . '/manifest.json';
    }

    if (! file_exists($manifest_path)) {
        return null;
    }

    static $manifest = null;
    if ($manifest === null) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $manifest = json_decode(file_get_contents($manifest_path), true);
    }

    if (! isset($manifest[$entry])) {
        return null;
    }

    return BMN_THEME_URI . '/' . BMN_VITE_DIST_DIR . '/' . $manifest[$entry]['file'];
}

/**
 * Output script/style tags for a Vite entry point.
 *
 * In development this also injects the Vite client script so that HMR works.
 *
 * @param string $entry Relative path to the entry file (e.g. "assets/src/ts/main.ts").
 */
function bmn_vite_enqueue(string $entry): void {
    if (bmn_vite_dev_server_running()) {
        // Vite client (HMR).
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_script('vite-client', BMN_VITE_DEV_SERVER . '/@vite/client', [], null);

        // The entry itself.
        $handle = 'bmn-' . sanitize_title(basename($entry, '.' . pathinfo($entry, PATHINFO_EXTENSION)));
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_enqueue_script($handle, BMN_VITE_DEV_SERVER . '/' . $entry, [], null);

        // Vite scripts must be loaded as ES modules.
        add_filter('script_loader_tag', function (string $tag, string $script_handle) use ($handle): string {
            if (in_array($script_handle, ['vite-client', $handle], true)) {
                $tag = str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);

        return;
    }

    // --- Production ---
    $url = bmn_vite_asset($entry);
    if ($url === null) {
        return;
    }

    $handle = 'bmn-' . sanitize_title(basename($entry, '.' . pathinfo($entry, PATHINFO_EXTENSION)));
    $extension = pathinfo($url, PATHINFO_EXTENSION);

    if ($extension === 'css') {
        wp_enqueue_style($handle, $url, [], BMN_THEME_VERSION);
    } else {
        wp_enqueue_script($handle, $url, [], BMN_THEME_VERSION, true);

        // Production scripts are also ES modules (Vite output).
        add_filter('script_loader_tag', function (string $tag, string $script_handle) use ($handle): string {
            if ($script_handle === $handle) {
                $tag = str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    // Also enqueue the CSS that Vite extracted from the JS entry.
    $manifest_path = BMN_THEME_DIR . '/' . BMN_VITE_DIST_DIR . '/.vite/manifest.json';
    if (! file_exists($manifest_path)) {
        $manifest_path = BMN_THEME_DIR . '/' . BMN_VITE_DIST_DIR . '/manifest.json';
    }
    if (file_exists($manifest_path)) {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (isset($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $index => $css_file) {
                wp_enqueue_style(
                    $handle . '-css-' . $index,
                    BMN_THEME_URI . '/' . BMN_VITE_DIST_DIR . '/' . $css_file,
                    [],
                    BMN_THEME_VERSION
                );
            }
        }
    }
}

/**
 * Enqueue front-end assets.
 */
function bmn_enqueue_assets(): void {
    bmn_vite_enqueue('assets/src/ts/main.ts');
    bmn_vite_enqueue('assets/src/scss/main.scss');
}
add_action('wp_enqueue_scripts', 'bmn_enqueue_assets');
