<?php
/**
 * Homepage Section Manager
 *
 * Manages homepage section ordering, enable/disable state,
 * stored in wp_options. Compatible with v1 section data format.
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BMN_Section_Manager {

    const OPTION_NAME = 'bne_homepage_sections';

    /**
     * Built-in section definitions (16 sections)
     */
    private static array $builtin_sections = array(
        'hero' => array(
            'name'        => 'Hero Section',
            'description' => 'Agent photo, contact info, and autocomplete search',
        ),
        'listings' => array(
            'name'        => 'Newest Listings',
            'description' => 'Grid of 8 most recent listings',
        ),
        'neighborhoods' => array(
            'name'        => 'Featured Neighborhoods',
            'description' => 'Neighborhood cards with live listing counts',
        ),
        'cities' => array(
            'name'        => 'Featured Cities',
            'description' => 'City cards with images and listing counts',
        ),
        'cma-request' => array(
            'name'        => 'CMA Request Form',
            'description' => 'Lead capture for home valuation requests',
        ),
        'property-alerts' => array(
            'name'        => 'Property Alerts',
            'description' => 'Alert signup with filters and frequency',
        ),
        'schedule-showing' => array(
            'name'        => 'Schedule Tour',
            'description' => 'Tour booking with type selection',
        ),
        'mortgage-calc' => array(
            'name'        => 'Mortgage Calculator',
            'description' => 'Interactive mortgage payment calculator',
        ),
        'about' => array(
            'name'        => 'About Us',
            'description' => 'Title, stats, and CTA',
        ),
        'team' => array(
            'name'        => 'Our Team',
            'description' => 'Team member cards with contact links',
        ),
        'testimonials' => array(
            'name'        => 'Client Testimonials',
            'description' => 'Carousel of client reviews',
        ),
        'promo-video' => array(
            'name'        => 'Promotional Video',
            'description' => 'YouTube/Vimeo/self-hosted video embed',
        ),
        'services' => array(
            'name'        => 'Our Services',
            'description' => 'Service offerings grid',
        ),
        'blog' => array(
            'name'        => 'Latest Blog Posts',
            'description' => 'Three most recent posts',
        ),
        'analytics' => array(
            'name'        => 'Neighborhood Analytics',
            'description' => 'Active listings, median price, DOM, YoY change',
        ),
        'market-analytics' => array(
            'name'        => 'City Market Insights',
            'description' => 'Tabbed city selector with market stats',
        ),
    );

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_action('customize_save_after', array(__CLASS__, 'sync_from_customizer'));
    }

    /**
     * Get built-in section definitions
     */
    public static function get_builtin_definitions(): array {
        return self::$builtin_sections;
    }

    /**
     * Get all sections in configured order
     */
    public static function get_sections(): array {
        $sections = get_option(self::OPTION_NAME, array());

        if (empty($sections)) {
            return self::get_default_sections();
        }

        return $sections;
    }

    /**
     * Get default section configuration (all enabled, default order)
     */
    public static function get_default_sections(): array {
        $sections = array();

        foreach (self::$builtin_sections as $id => $definition) {
            $sections[] = array(
                'id'            => $id,
                'type'          => 'builtin',
                'name'          => $definition['name'],
                'enabled'       => true,
                'override_html' => '',
            );
        }

        return $sections;
    }

    /**
     * Get enabled sections in order
     */
    public static function get_enabled_sections(): array {
        return array_filter(self::get_sections(), function ($section) {
            return !empty($section['enabled']);
        });
    }

    /**
     * Save sections configuration
     */
    public static function save_sections(array $sections): bool {
        $sanitized = self::sanitize_sections($sections);
        if ($sanitized === false) {
            return false;
        }
        return update_option(self::OPTION_NAME, $sanitized);
    }

    /**
     * Sanitize sections data
     *
     * @return array|false
     */
    public static function sanitize_sections(array $sections) {
        $sanitized = array();

        foreach ($sections as $section) {
            if (!isset($section['id'], $section['type'])) {
                continue;
            }

            $clean = array(
                'id'      => sanitize_key($section['id']),
                'type'    => in_array($section['type'], array('builtin', 'custom')) ? $section['type'] : 'builtin',
                'enabled' => !empty($section['enabled']),
            );

            if (isset($section['name'])) {
                $clean['name'] = sanitize_text_field($section['name']);
            } elseif ($clean['type'] === 'builtin' && isset(self::$builtin_sections[$clean['id']])) {
                $clean['name'] = self::$builtin_sections[$clean['id']]['name'];
            } else {
                $clean['name'] = 'Unnamed Section';
            }

            if ($clean['type'] === 'custom') {
                $clean['html'] = isset($section['html']) ? wp_kses_post($section['html']) : '';
            } else {
                $clean['override_html'] = isset($section['override_html']) ? wp_kses_post($section['override_html']) : '';
            }

            $sanitized[] = $clean;
        }

        return $sanitized;
    }

    /**
     * Reorder sections
     */
    public static function reorder_sections(array $order): bool {
        $sections = self::get_sections();
        $by_id = array();

        foreach ($sections as $section) {
            $by_id[$section['id']] = $section;
        }

        $reordered = array();
        foreach ($order as $id) {
            $id = sanitize_key($id);
            if (isset($by_id[$id])) {
                $reordered[] = $by_id[$id];
                unset($by_id[$id]);
            }
        }

        // Append any not in the order list
        foreach ($by_id as $section) {
            $reordered[] = $section;
        }

        return self::save_sections($reordered);
    }

    /**
     * Sync from Customizer after save
     */
    public static function sync_from_customizer($wp_customize): void {
        $customizer_value = get_theme_mod('bne_homepage_section_order', '');

        if (empty($customizer_value)) {
            return;
        }

        $customizer_data = json_decode($customizer_value, true);
        if (!is_array($customizer_data)) {
            return;
        }

        $sections = self::get_sections();
        $by_id = array();
        foreach ($sections as $section) {
            $by_id[$section['id']] = $section;
        }

        $updated = array();
        foreach ($customizer_data as $item) {
            if (!isset($item['id'])) {
                continue;
            }
            $id = sanitize_key($item['id']);
            if (isset($by_id[$id])) {
                $section = $by_id[$id];
                $section['enabled'] = !empty($item['enabled']);
                $updated[] = $section;
                unset($by_id[$id]);
            }
        }

        foreach ($by_id as $section) {
            $updated[] = $section;
        }

        self::save_sections($updated);
        remove_theme_mod('bne_homepage_section_order');
    }
}
