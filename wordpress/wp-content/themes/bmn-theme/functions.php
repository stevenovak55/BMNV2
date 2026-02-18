<?php
/**
 * BMN Theme v2 Functions
 *
 * @package bmn_theme
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BMN_THEME_VERSION', '2.1.0');
define('BMN_THEME_DIR', get_stylesheet_directory());
define('BMN_THEME_URI', get_stylesheet_directory_uri());

/**
 * Load theme includes
 */
require_once BMN_THEME_DIR . '/inc/helpers.php';
require_once BMN_THEME_DIR . '/inc/class-section-manager.php';

/**
 * Theme setup
 */
function bmn_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array(
        'height'      => 80,
        'width'       => 320,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ));

    // Register nav menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'bmn-theme'),
        'footer'  => __('Footer Menu', 'bmn-theme'),
    ));

    // Custom image sizes
    add_image_size('property-card', 480, 320, true);
    add_image_size('property-card-lg', 640, 420, true);
    add_image_size('team-photo', 300, 300, true);
    add_image_size('hero-bg', 1920, 1080, true);
}
add_action('after_setup_theme', 'bmn_theme_setup');

/**
 * Enqueue Vite-built assets
 */
function bmn_enqueue_assets() {
    $dist_dir = BMN_THEME_DIR . '/assets/dist';
    $dist_uri = BMN_THEME_URI . '/assets/dist';
    $manifest_path = $dist_dir . '/.vite/manifest.json';

    // Development: Vite dev server
    if (defined('BMN_VITE_DEV') && BMN_VITE_DEV) {
        // Vite client for HMR
        wp_enqueue_script('vite-client', 'http://localhost:5173/@vite/client', array(), null);
        wp_enqueue_script('bmn-main', 'http://localhost:5173/assets/src/ts/main.ts', array(), null);
        wp_enqueue_style('bmn-style', 'http://localhost:5173/assets/src/scss/main.scss', array(), null);
        return;
    }

    // Production: read Vite manifest
    if (!file_exists($manifest_path)) {
        return;
    }

    $manifest = json_decode(file_get_contents($manifest_path), true);
    if (!$manifest) {
        return;
    }

    // Enqueue main JS
    $js_entry = $manifest['assets/src/ts/main.ts'] ?? null;
    if ($js_entry && isset($js_entry['file'])) {
        wp_enqueue_script(
            'bmn-main',
            $dist_uri . '/' . $js_entry['file'],
            array(),
            BMN_THEME_VERSION,
            true
        );

        // Add module type
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'bmn-main') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    // Enqueue main CSS
    $css_entry = $manifest['assets/src/scss/main.scss'] ?? null;
    if ($css_entry && isset($css_entry['file'])) {
        wp_enqueue_style(
            'bmn-style-compiled',
            $dist_uri . '/' . $css_entry['file'],
            array(),
            BMN_THEME_VERSION
        );
    }

    // Also enqueue CSS from JS entry (Vite extracts CSS from JS imports)
    if ($js_entry && !empty($js_entry['css'])) {
        foreach ($js_entry['css'] as $i => $css_file) {
            wp_enqueue_style(
                'bmn-main-css-' . $i,
                $dist_uri . '/' . $css_file,
                array(),
                BMN_THEME_VERSION
            );
        }
    }

    // Localize script with REST API URLs for client-side use
    wp_localize_script('bmn-main', 'bmnTheme', array(
        'restUrl'         => esc_url_raw(rest_url()),
        'autocompleteUrl' => esc_url_raw(rest_url('bmn/v1/properties/autocomplete')),
        'authApiUrl'      => esc_url_raw(rest_url('bmn/v1/auth')),
        'nonce'           => '', // Empty - public endpoints don't need nonces
        'homeUrl'         => esc_url_raw(home_url('/')),
        'searchUrl'       => esc_url_raw(bmn_get_search_url()),
        'dashboardUrl'    => esc_url_raw(bmn_get_dashboard_url()),
        'loginUrl'        => esc_url_raw(home_url('/login/')),
        'mapSearchUrl'    => esc_url_raw(home_url('/map-search/')),
    ));
}
add_action('wp_enqueue_scripts', 'bmn_enqueue_assets');

/**
 * Hide WordPress admin bar for all users
 */
function bmn_hide_admin_bar() {
    show_admin_bar(false);
}
add_action('after_setup_theme', 'bmn_hide_admin_bar');

/**
 * Initialize Section Manager
 */
function bmn_init_section_manager() {
    BMN_Section_Manager::init();
}
add_action('after_setup_theme', 'bmn_init_section_manager', 10);

/**
 * Register property detail URL rewrite rules
 *
 * V2: Theme handles /property/{listing_id}/ routing since
 * the bmn-properties plugin is REST-only.
 */
function bmn_property_rewrite_rules() {
    add_rewrite_rule(
        '^property/([^/]+)/?$',
        'index.php?mls_number=$matches[1]',
        'top'
    );
}
add_action('init', 'bmn_property_rewrite_rules');

/**
 * Register mls_number as a recognized query var
 */
function bmn_register_query_vars($vars) {
    $vars[] = 'mls_number';
    return $vars;
}
add_filter('query_vars', 'bmn_register_query_vars');

/**
 * Load single-property.php template when mls_number query var is present
 */
function bmn_property_template_override($template) {
    $mls_number = get_query_var('mls_number', '');
    if (!empty($mls_number)) {
        $theme_template = BMN_THEME_DIR . '/single-property.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
    }
    return $template;
}
add_filter('template_include', 'bmn_property_template_override', 100);

/**
 * Handle property contact form submissions (AJAX)
 */
function bmn_handle_contact_form() {
    $name     = sanitize_text_field($_POST['name'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $message  = sanitize_textarea_field($_POST['message'] ?? '');
    $address  = sanitize_text_field($_POST['property_address'] ?? '');
    $subj_field = sanitize_text_field($_POST['subject'] ?? '');
    $to_email = sanitize_email($_POST['agent_email'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        wp_send_json_error('Missing required fields.', 400);
    }

    if (empty($to_email)) {
        $to_email = get_theme_mod('bne_agent_email', 'mail@steve-novak.com');
    }

    if ($address) {
        $subject = 'Property Inquiry: ' . $address;
    } elseif ($subj_field) {
        $subject = $subj_field . ' - BMN Boston';
    } else {
        $subject = 'General Inquiry - BMN Boston';
    }

    $body = "Name: {$name}\nEmail: {$email}\n";
    if ($phone) {
        $body .= "Phone: {$phone}\n";
    }
    if ($address) {
        $body .= "Property: {$address}\n";
    }
    $body .= "\nMessage:\n{$message}";

    $headers = array('Reply-To: ' . $name . ' <' . $email . '>');

    $sent = wp_mail($to_email, $subject, $body, $headers);

    if ($sent) {
        wp_send_json_success('Message sent.');
    } else {
        wp_send_json_error('Failed to send message. Please try again.', 500);
    }
}
add_action('wp_ajax_bmn_contact_form', 'bmn_handle_contact_form');
add_action('wp_ajax_nopriv_bmn_contact_form', 'bmn_handle_contact_form');

/**
 * Localize additional page data for search and property pages
 */
function bmn_localize_page_data() {
    wp_localize_script('bmn-main', 'bmnPageData', array(
        'propertiesApiUrl' => esc_url_raw(rest_url('bmn/v1/properties')),
        'schoolsApiUrl'    => esc_url_raw(rest_url('bmn/v1/schools/nearby')),
        'propertyTypes'    => bmn_get_property_types(),
    ));
}
add_action('wp_enqueue_scripts', 'bmn_localize_page_data', 20);
