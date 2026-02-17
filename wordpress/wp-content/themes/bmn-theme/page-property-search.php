<?php
/**
 * Template Name: Property Search
 *
 * Property search page with HTMX partial rendering.
 * Full page loads render everything; HTMX filter/pagination requests
 * return only the results-grid fragment.
 *
 * @package bmn_theme
 * @version 2.1.0
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
    'per_page'         => 21,
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
);

get_header();
?>

<main id="main" class="flex-1 bg-gray-50"
      x-data="filterState(<?php echo esc_attr(wp_json_encode($alpine_filters)); ?>)">

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="container mx-auto px-4 lg:px-8 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Property Search</h1>
                    <p class="text-sm text-gray-500 mt-1" x-text="totalLabel"></p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Mobile filter toggle -->
                    <button @click="mobileFiltersOpen = !mobileFiltersOpen"
                            class="lg:hidden flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filters
                    </button>
                    <!-- Sort dropdown -->
                    <select x-model="sort" @change="submitFilters()"
                            class="text-sm border-gray-200 rounded-lg focus:border-navy-500 focus:ring-navy-500">
                        <option value="newest">Newest First</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="beds_desc">Most Bedrooms</option>
                        <option value="sqft_desc">Largest</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Content: Sidebar + Results -->
    <div class="container mx-auto px-4 lg:px-8 py-6">
        <div class="flex gap-6">

            <!-- Filter Sidebar -->
            <?php get_template_part('template-parts/search/filter-sidebar', null, array('filters' => $filters)); ?>

            <!-- Results Grid -->
            <div class="flex-1 min-w-0">
                <!-- Loading overlay -->
                <div x-show="loading" class="flex items-center justify-center py-12">
                    <svg class="animate-spin h-8 w-8 text-navy-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
        </div>
    </div>
</main>

<?php get_footer(); ?>
