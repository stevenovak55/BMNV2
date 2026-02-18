<?php
/**
 * Theme Helper Functions
 *
 * Template helpers that wrap V2 plugin REST API calls.
 * All property data flows through /bmn/v1/ REST endpoints via rest_do_request().
 *
 * @package bmn_theme
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get newest property listings via V2 REST API
 *
 * @param int $limit Number of listings to return
 * @return array
 */
function bmn_get_newest_listings(int $limit = 8): array {
    $result = bmn_search_properties(array(
        'per_page' => $limit,
        'sort'     => 'newest',
        'status'   => 'Active',
    ));
    return $result['listings'];
}

/**
 * Get featured agents via V2 REST API
 *
 * @return array
 */
function bmn_get_featured_agents(): array {
    $request  = new WP_REST_Request('GET', '/bmn/v1/agents/featured');
    $response = rest_do_request($request);
    $data     = rest_get_server()->response_to_data($response, false);

    if ($response->is_error()) {
        return array();
    }

    return $data['data'] ?? array();
}

/**
 * Get featured neighborhoods
 *
 * @return array
 */
function bmn_get_neighborhoods(): array {
    // TODO: V2 neighborhoods endpoint when available
    return array();
}

/**
 * Get featured cities
 *
 * @return array
 */
function bmn_get_cities(): array {
    // TODO: V2 cities endpoint when available
    return array();
}

/**
 * Get testimonials
 *
 * @param int $limit Number of testimonials
 * @return array
 */
function bmn_get_testimonials(int $limit = 10): array {
    // TODO: V2 testimonials when available
    return array();
}

/**
 * Get neighborhood analytics data
 *
 * @return array
 */
function bmn_get_neighborhood_analytics(): array {
    // TODO: V2 analytics endpoint when available
    return array();
}

/**
 * Primary nav fallback when no WP menu is assigned
 */
function bmn_primary_nav_fallback(): void {
    $class = 'px-3 py-2 text-sm font-medium text-gray-700 hover:text-navy-700 hover:bg-navy-50 rounded-lg transition-colors';
    $items = array(
        'Search'  => bmn_get_search_url(),
        'About'   => home_url('/about/'),
        'Contact' => bmn_get_contact_url(),
    );
    echo '<ul class="flex items-center gap-1">';
    foreach ($items as $label => $url) {
        echo '<li><a href="' . esc_url($url) . '" class="' . $class . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Mobile nav fallback when no WP menu is assigned
 */
function bmn_mobile_nav_fallback(): void {
    $class = 'block px-3 py-2.5 text-base font-medium text-gray-700 hover:text-navy-700 hover:bg-navy-50 rounded-lg transition-colors';
    $items = array(
        'Search'  => bmn_get_search_url(),
        'About'   => home_url('/about/'),
        'Contact' => bmn_get_contact_url(),
    );
    echo '<ul class="space-y-1">';
    foreach ($items as $label => $url) {
        echo '<li><a href="' . esc_url($url) . '" class="' . $class . '">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Footer nav fallback when no WP menu is assigned
 */
function bmn_footer_nav_fallback(): void {
    $items = array(
        'Search Properties' => bmn_get_search_url(),
        'About Us'          => home_url('/about/'),
        'Contact'           => bmn_get_contact_url(),
        'Privacy Policy'    => home_url('/privacy-policy/'),
    );
    echo '<ul class="space-y-2">';
    foreach ($items as $label => $url) {
        echo '<li><a href="' . esc_url($url) . '" class="text-sm text-gray-400 hover:text-white transition-colors">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Build property detail URL using listing_id (MLS number, NOT listing_key)
 *
 * @param string $listing_id The MLS listing number
 * @return string
 */
function bmn_get_property_url(string $listing_id): string {
    return home_url('/property/' . $listing_id . '/');
}

/**
 * Build search URL with optional query params
 *
 * @param array $params Query parameters
 * @return string
 */
function bmn_get_search_url(array $params = array()): string {
    $base = home_url('/property-search/');

    if (!empty($params)) {
        $base = add_query_arg($params, $base);
    }

    return $base;
}

/**
 * Format price for display
 *
 * @param float $price
 * @return string
 */
function bmn_format_price(float $price): string {
    if ($price >= 1000000) {
        return '$' . number_format($price / 1000000, 2) . 'M';
    }
    return '$' . number_format($price, 0);
}

/**
 * Get autocomplete REST endpoint URL
 *
 * @return string
 */
function bmn_get_autocomplete_url(): string {
    return rest_url('bmn/v1/properties/autocomplete');
}

/**
 * Get user avatar URL
 *
 * @param int $user_id
 * @param int $size
 * @return string
 */
function bmn_get_user_avatar_url(int $user_id, int $size = 48): string {
    return get_avatar_url($user_id, array('size' => $size));
}

/**
 * Get available property types for search forms
 *
 * @return array
 */
function bmn_get_property_types(): array {
    return array(
        'Single Family Residence',
        'Condominium',
        'Multi Family',
        'Townhouse',
    );
}

/**
 * Get contact page URL
 *
 * @return string
 */
function bmn_get_contact_url(): string {
    return home_url('/contact/');
}

/**
 * Get user dashboard URL
 *
 * @param string $tab Optional tab hash (favorites, saved-searches, profile)
 * @return string
 */
function bmn_get_dashboard_url(string $tab = ''): string {
    $url = home_url('/my-dashboard/');
    if ($tab) {
        $url .= '#' . $tab;
    }
    return $url;
}

/**
 * Helper to render a homepage section template
 *
 * @param string $section_id Section identifier
 */
function bmn_get_homepage_section(string $section_id): void {
    get_template_part('template-parts/homepage/section', $section_id);
}

/**
 * Get full property details by listing_id (MLS number)
 *
 * Calls V2 REST API: GET /bmn/v1/properties/{listing_id}
 *
 * @param string $listing_id MLS listing number
 * @return array|null Property data or null if not found
 */
function bmn_get_property_details(string $listing_id): ?array {
    if (empty($listing_id)) {
        return null;
    }

    $request  = new WP_REST_Request('GET', '/bmn/v1/properties/' . $listing_id);
    $response = rest_do_request($request);
    $data     = rest_get_server()->response_to_data($response, false);

    if ($response->is_error()) {
        return null;
    }

    return $data['data'] ?? null;
}

/**
 * Get property photos by listing_id
 *
 * Extracts photo URLs from the V2 property detail response.
 * The detail endpoint already returns photos with url, category, and order.
 *
 * @param string $listing_id MLS listing number
 * @return array Array of photo URLs
 */
function bmn_get_property_photos(string $listing_id): array {
    $property = bmn_get_property_details($listing_id);
    if (!$property || empty($property['photos'])) {
        return array();
    }

    $urls = array();
    foreach ($property['photos'] as $photo) {
        if (is_array($photo) && !empty($photo['url'])) {
            $urls[] = $photo['url'];
        } elseif (is_string($photo)) {
            $urls[] = $photo;
        }
    }

    return $urls;
}

/**
 * Get property price history
 *
 * Extracts price_history from the V2 property detail response.
 * V2 detail endpoint returns price_history as an array of events.
 *
 * @param string $listing_id MLS listing number
 * @return array Array of price history events
 */
function bmn_get_property_price_history(string $listing_id): array {
    $property = bmn_get_property_details($listing_id);
    if (!$property || empty($property['price_history'])) {
        // Fallback: build basic history from property data
        return bmn_build_price_history_from_property($property);
    }

    $normalized = array();
    foreach ($property['price_history'] as $event) {
        $change_type = $event['change_type'] ?? $event['event_type'] ?? '';
        $new_value   = $event['new_value'] ?? $event['price'] ?? 0;
        $date        = $event['changed_at'] ?? $event['date'] ?? '';

        // Map change_type to display labels
        $label = 'Listed';
        if (stripos($change_type, 'price') !== false) {
            $label = 'Price Change';
        } elseif (stripos($change_type, 'sold') !== false || stripos($change_type, 'close') !== false) {
            $label = 'Sold';
        } elseif (stripos($change_type, 'status') !== false) {
            $label = 'Status Change';
        }

        $normalized[] = array(
            'date'       => $date,
            'price'      => floatval($new_value),
            'event_type' => $label,
        );
    }

    return $normalized;
}

/**
 * Build basic price history from property detail data
 *
 * @param array|null $property Property detail data
 * @return array
 */
function bmn_build_price_history_from_property(?array $property): array {
    if (!$property) {
        return array();
    }

    $history = array();
    $price          = floatval($property['price'] ?? 0);
    $original_price = floatval($property['original_price'] ?? 0);
    $close_price    = floatval($property['close_price'] ?? 0);
    $list_date      = $property['list_date'] ?? '';
    $close_date     = $property['close_date'] ?? '';

    // Sold event
    if ($close_date && $close_date !== '0000-00-00 00:00:00' && $close_price > 0) {
        $history[] = array(
            'date'       => $close_date,
            'price'      => $close_price,
            'event_type' => 'Sold',
        );
    }

    // Price change
    if ($original_price > 0 && $price > 0 && $original_price !== $price) {
        $history[] = array(
            'date'       => $list_date ?: current_time('Y-m-d'),
            'price'      => $price,
            'event_type' => 'Price Change',
        );
    }

    // Listed event
    if ($list_date) {
        $history[] = array(
            'date'       => $list_date,
            'price'      => $original_price > 0 ? $original_price : $price,
            'event_type' => 'Listed',
        );
    }

    return $history;
}

/**
 * Search properties via V2 REST API
 *
 * Uses rest_do_request() to call /bmn/v1/properties.
 * One service, two interfaces: iOS and Web both use the same endpoint.
 *
 * V2 response format: {success, data: [...], meta: {total, page, per_page, total_pages}}
 *
 * @param array $filters Search filters (city, min_price, max_price, beds, baths, status, etc.)
 * @return array {listings: array, total: int, pages: int, page: int}
 */
function bmn_search_properties(array $filters = array()): array {
    $defaults = array(
        'per_page' => 21,
        'page'     => 1,
        'sort'     => 'newest',
    );
    $params = wp_parse_args($filters, $defaults);

    $request = new WP_REST_Request('GET', '/bmn/v1/properties');
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $request->set_param($key, $value);
        }
    }

    $response = rest_do_request($request);
    $data     = rest_get_server()->response_to_data($response, false);

    if ($response->is_error()) {
        return array(
            'listings' => array(),
            'total'    => 0,
            'pages'    => 0,
            'page'     => intval($params['page']),
        );
    }

    // V2 format: {success: true, data: [...listings], meta: {total, page, per_page, total_pages}}
    $listings    = $data['data'] ?? array();
    $meta        = $data['meta'] ?? array();
    $total       = intval($meta['total'] ?? 0);
    $total_pages = intval($meta['total_pages'] ?? 0);

    return array(
        'listings' => is_array($listings) ? $listings : array(),
        'total'    => $total,
        'pages'    => $total_pages,
        'page'     => intval($params['page']),
    );
}
