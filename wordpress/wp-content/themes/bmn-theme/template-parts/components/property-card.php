<?php
/**
 * Property Card Component
 *
 * Reusable property listing card for homepage, search results, favorites.
 *
 * Expected $args['listing'] array keys:
 *   url, photo, address, city, state, zip, price, beds, baths, sqft_raw, sqft, type, listing_id
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$listing = $args['listing'] ?? array();
if (empty($listing)) {
    return;
}

$url     = $listing['url'] ?? bmn_get_property_url($listing['listing_id'] ?? '');
$photo   = $listing['photo'] ?? '';
$address = $listing['address'] ?? '';
$city    = $listing['city'] ?? '';
$state   = $listing['state'] ?? 'MA';
$zip     = $listing['zip'] ?? '';
$price   = $listing['price'] ?? '';
$beds    = $listing['beds'] ?? '';
$baths   = $listing['baths'] ?? '';
$sqft    = $listing['sqft'] ?? '';
$type    = $listing['type'] ?? '';
?>

<a href="<?php echo esc_url($url); ?>"
   class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden card-hover block">
    <!-- Image -->
    <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
        <?php if ($photo) : ?>
            <img src="<?php echo esc_url($photo); ?>"
                 alt="<?php echo esc_attr($address); ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                 loading="lazy">
        <?php else : ?>
            <div class="flex items-center justify-center h-full text-gray-300">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            </div>
        <?php endif; ?>

        <!-- Price badge -->
        <?php if ($price) : ?>
            <span class="absolute bottom-3 left-3 bg-navy-700/90 text-white text-sm font-semibold px-3 py-1 rounded-lg backdrop-blur-sm">
                <?php echo esc_html($price); ?>
            </span>
        <?php endif; ?>

        <!-- Type badge -->
        <?php if ($type) : ?>
            <span class="absolute top-3 left-3 bg-white/90 text-gray-700 text-xs font-medium px-2 py-1 rounded-md backdrop-blur-sm">
                <?php echo esc_html($type); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="p-4">
        <h3 class="font-semibold text-gray-900 truncate group-hover:text-navy-700 transition-colors">
            <?php echo esc_html($address); ?>
        </h3>
        <p class="text-sm text-gray-500 mt-0.5">
            <?php echo esc_html(trim("$city, $state $zip")); ?>
        </p>

        <!-- Specs -->
        <div class="flex items-center gap-3 mt-3 text-sm text-gray-600">
            <?php if ($beds) : ?>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <?php echo esc_html($beds); ?> bd
                </span>
            <?php endif; ?>
            <?php if ($baths) : ?>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    <?php echo esc_html($baths); ?> ba
                </span>
            <?php endif; ?>
            <?php if ($sqft) : ?>
                <span class="flex items-center gap-1">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    <?php echo esc_html($sqft); ?> sqft
                </span>
            <?php endif; ?>
        </div>
    </div>
</a>
