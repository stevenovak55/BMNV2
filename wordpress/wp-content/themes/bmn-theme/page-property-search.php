<?php
/**
 * Template Name: Property Search
 *
 * Full-width property search with Redfin-style filter bar.
 * HTMX partial rendering for filter/pagination updates.
 *
 * @package bmn_theme
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sanitize all GET filter params
$filters = array(
    'city'             => sanitize_text_field($_GET['city'] ?? ''),
    'neighborhood'     => sanitize_text_field($_GET['neighborhood'] ?? ''),
    'address'          => sanitize_text_field($_GET['address'] ?? ''),
    'street'           => sanitize_text_field($_GET['street'] ?? ''),
    'min_price'        => sanitize_text_field($_GET['min_price'] ?? ''),
    'max_price'        => sanitize_text_field($_GET['max_price'] ?? ''),
    'beds'             => sanitize_text_field($_GET['beds'] ?? ''),
    'baths'            => sanitize_text_field($_GET['baths'] ?? ''),
    'property_type'    => sanitize_text_field($_GET['property_type'] ?? ''),
    'status'           => sanitize_text_field($_GET['status'] ?? 'Active'),
    'school_grade'     => sanitize_text_field($_GET['school_grade'] ?? ''),
    'price_reduced'    => sanitize_text_field($_GET['price_reduced'] ?? ''),
    'new_listing_days' => sanitize_text_field($_GET['new_listing_days'] ?? ''),
    'sort'             => sanitize_text_field($_GET['sort'] ?? 'newest'),
    'page'             => max(1, intval($_GET['paged'] ?? 1)),
    'per_page'         => 24,
    // Advanced filters
    'sqft_min'         => sanitize_text_field($_GET['sqft_min'] ?? ''),
    'sqft_max'         => sanitize_text_field($_GET['sqft_max'] ?? ''),
    'lot_size_min'     => sanitize_text_field($_GET['lot_size_min'] ?? ''),
    'lot_size_max'     => sanitize_text_field($_GET['lot_size_max'] ?? ''),
    'year_built_min'   => sanitize_text_field($_GET['year_built_min'] ?? ''),
    'year_built_max'   => sanitize_text_field($_GET['year_built_max'] ?? ''),
    'max_dom'          => sanitize_text_field($_GET['max_dom'] ?? ''),
    'garage'           => sanitize_text_field($_GET['garage'] ?? ''),
    'virtual_tour'     => sanitize_text_field($_GET['virtual_tour'] ?? ''),
    'fireplace'        => sanitize_text_field($_GET['fireplace'] ?? ''),
    'open_house'       => sanitize_text_field($_GET['open_house'] ?? ''),
    'exclusive'        => sanitize_text_field($_GET['exclusive'] ?? ''),
);

// Remove empty values before querying
$query_filters = array_filter($filters, function ($v) {
    return $v !== '' && $v !== null;
});

// Fetch initial results via internal REST API dispatch
$results = bmn_search_properties($query_filters);

// HTMX partial rendering: return only the results grid fragment
if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
    get_template_part('template-parts/search/results-grid', null, array(
        'listings' => $results['listings'],
        'total'    => $results['total'],
        'pages'    => $results['pages'],
        'page'     => $results['page'],
        'filters'  => $filters,
    ));
    exit;
}

// Build JSON-safe filter state for Alpine.js
$alpine_filters = array(
    'city'             => $filters['city'],
    'neighborhood'     => $filters['neighborhood'],
    'address'          => $filters['address'],
    'street'           => $filters['street'],
    'min_price'        => $filters['min_price'],
    'max_price'        => $filters['max_price'],
    'beds'             => $filters['beds'],
    'baths'            => $filters['baths'],
    'property_type'    => $filters['property_type'] ? explode(',', $filters['property_type']) : array(),
    'status'           => $filters['status'] ? explode(',', $filters['status']) : array('Active'),
    'school_grade'     => $filters['school_grade'],
    'price_reduced'    => $filters['price_reduced'],
    'new_listing_days' => $filters['new_listing_days'],
    'sort'             => $filters['sort'],
    'page'             => $results['page'],
    'total'            => $results['total'],
    'pages'            => $results['pages'],
    // Advanced
    'sqft_min'         => $filters['sqft_min'],
    'sqft_max'         => $filters['sqft_max'],
    'lot_size_min'     => $filters['lot_size_min'],
    'lot_size_max'     => $filters['lot_size_max'],
    'year_built_min'   => $filters['year_built_min'],
    'year_built_max'   => $filters['year_built_max'],
    'max_dom'          => $filters['max_dom'],
    'garage'           => $filters['garage'],
    'virtual_tour'     => $filters['virtual_tour'],
    'fireplace'        => $filters['fireplace'],
    'open_house'       => $filters['open_house'],
    'exclusive'        => $filters['exclusive'],
);

get_header();
?>

<main id="main" class="flex-1 bg-gray-50"
      x-data="filterState(<?php echo esc_attr(wp_json_encode($alpine_filters)); ?>)">

    <!-- Filter Bar -->
    <?php get_template_part('template-parts/search/filter-bar', null, array('view' => 'list')); ?>

    <!-- Results -->
    <div class="max-w-7xl mx-auto px-4 lg:px-8 py-6">
        <!-- Loading overlay -->
        <div x-show="loading" class="flex items-center justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-teal-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <div id="results-grid" x-show="!loading">
            <?php
            get_template_part('template-parts/search/results-grid', null, array(
                'listings' => $results['listings'],
                'total'    => $results['total'],
                'pages'    => $results['pages'],
                'page'     => $results['page'],
                'filters'  => $filters,
            ));
            ?>
        </div>
    </div>

    <!-- Save Search Modal -->
    <div x-data="saveSearchModal" x-show="saveSearchOpen || open" x-cloak>
        <div x-show="saveSearchOpen || open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black/50 z-50"
             @click="saveSearchOpen = false; close()">
        </div>
        <div x-show="saveSearchOpen || open"
             x-transition
             class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-2xl p-6 z-50 w-full max-w-md"
             @click.outside="saveSearchOpen = false; close()">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Save This Search</h3>
            <input type="text" x-model="name" placeholder="Name your search..."
                   class="w-full text-sm border-gray-200 rounded-lg focus:border-teal-600 focus:ring-teal-600 mb-3"
                   @keydown.enter="save(_getFilters())">
            <p x-show="error" x-text="error" class="text-sm text-red-600 mb-3"></p>
            <p x-show="success" class="text-sm text-green-600 mb-3">Search saved!</p>
            <div class="flex gap-3">
                <button @click="save(_getFilters())" :disabled="saving"
                        class="btn-search flex-1" x-text="saving ? 'Saving...' : 'Save Search'"></button>
                <button @click="saveSearchOpen = false; close()"
                        class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
