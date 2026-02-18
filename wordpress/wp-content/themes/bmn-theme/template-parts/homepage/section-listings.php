<?php
/**
 * Homepage: Newest Listings Section
 *
 * @package bmn_theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$listings = bmn_get_newest_listings(8);
?>

<section class="py-12 md:py-16 lg:py-20 bg-white" aria-labelledby="listings-title">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="text-center mb-8 md:mb-12">
            <h2 id="listings-title" class="section-title">Newest Listings</h2>
            <p class="section-subtitle mx-auto">Fresh on the market &mdash; explore our latest properties</p>
        </div>

        <?php if (!empty($listings)) : ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($listings as $listing) :
                    $listing = (array) $listing;
                    // Normalize API keys to property-card format
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
                        'status'     => $listing['status'] ?? 'Active',
                        'dom'        => $listing['dom'] ?? $listing['days_on_market'] ?? '',
                    );
                    get_template_part('template-parts/components/property-card', null, array('listing' => $card_data));
                endforeach; ?>
            </div>

            <div class="text-center mt-8">
                <a href="<?php echo esc_url(bmn_get_search_url()); ?>" class="btn-secondary">
                    View All Listings
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        <?php else : ?>
            <p class="text-center text-gray-500">No listings available at the moment.</p>
        <?php endif; ?>
    </div>
</section>
