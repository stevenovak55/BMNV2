<?php
/**
 * Search Results Grid
 *
 * Rendered both on initial page load and as HTMX partial.
 * Normalizes API response keys to property-card.php format.
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$listings = $args['listings'] ?? array();
$total    = intval($args['total'] ?? 0);
$pages    = intval($args['pages'] ?? 0);
$page     = intval($args['page'] ?? 1);
$filters  = $args['filters'] ?? array();
?>

<!-- Sync server values into Alpine state -->
<div x-init="syncFromServer(<?php echo intval($total); ?>, <?php echo intval($pages); ?>, <?php echo intval($page); ?>)"></div>

<?php if (empty($listings)) : ?>
    <!-- Empty State -->
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <h3 class="text-lg font-semibold text-gray-700 mb-1">No Properties Found</h3>
        <p class="text-sm text-gray-500 max-w-sm">Try adjusting your filters or expanding your search area to find more properties.</p>
        <button @click="resetFilters()"
                class="mt-4 px-4 py-2 text-sm font-medium text-navy-700 bg-navy-50 rounded-lg hover:bg-navy-100 transition-colors">
            Reset Filters
        </button>
    </div>
<?php else : ?>
    <!-- Results Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($listings as $listing) :
            // Normalize API response keys to property-card.php format
            $listing = (array) $listing;

            // V2 API field names: listing_id, address, price, beds, baths, sqft, main_photo_url, status
            $card_data = array(
                'url'        => bmn_get_property_url($listing['listing_id'] ?? ''),
                'photo'      => $listing['main_photo_url'] ?? '',
                'address'    => $listing['address'] ?? '',
                'city'       => $listing['city'] ?? '',
                'state'      => $listing['state'] ?? 'MA',
                'zip'        => $listing['zip'] ?? '',
                'price'      => bmn_format_price(floatval($listing['price'] ?? 0)),
                'beds'       => $listing['beds'] ?? '',
                'baths'      => $listing['baths'] ?? '',
                'sqft'       => !empty($listing['sqft']) ? number_format(floatval($listing['sqft'])) : '',
                'type'       => $listing['property_sub_type'] ?? $listing['property_type'] ?? '',
                'listing_id' => $listing['listing_id'] ?? '',
            );

            get_template_part('template-parts/components/property-card', null, array('listing' => $card_data));
        endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1) :
        get_template_part('template-parts/search/pagination', null, array(
            'page'    => $page,
            'pages'   => $pages,
            'filters' => $filters,
        ));
    endif; ?>
<?php endif; ?>
