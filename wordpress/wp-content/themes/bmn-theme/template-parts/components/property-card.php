<?php
/**
 * Property Card Component — Unified Design
 *
 * Reusable property listing card for homepage, search results, favorites.
 * Features: status badge, DOM label, favorite heart, teal accents.
 *
 * Expected $args['listing'] array keys:
 *   url, photo, address, city, state, zip, price, beds, baths, sqft, type,
 *   listing_id, status, dom
 *
 * @package bmn_theme
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$listing = $args['listing'] ?? array();
if (empty($listing)) {
    return;
}

$url        = $listing['url'] ?? bmn_get_property_url($listing['listing_id'] ?? '');
$photo      = $listing['photo'] ?? '';
$address    = $listing['address'] ?? '';
$city       = $listing['city'] ?? '';
$state      = $listing['state'] ?? 'MA';
$zip        = $listing['zip'] ?? '';
$price      = $listing['price'] ?? '';
$beds       = $listing['beds'] ?? '';
$baths      = $listing['baths'] ?? '';
$sqft       = $listing['sqft'] ?? '';
$type       = $listing['type'] ?? '';
$listing_id = $listing['listing_id'] ?? '';
$status     = $listing['status'] ?? 'Active';
$dom        = $listing['dom'] ?? '';

// Status badge colors
$status_colors = array(
    'Active'                => 'bg-green-100 text-green-800',
    'Pending'               => 'bg-yellow-100 text-yellow-800',
    'Active Under Contract' => 'bg-yellow-100 text-yellow-800',
    'Closed'                => 'bg-red-100 text-red-800',
    'Sold'                  => 'bg-red-100 text-red-800',
);
$status_class = $status_colors[$status] ?? 'bg-gray-100 text-gray-800';

// DOM label
$dom_int = intval($dom);
$is_new  = $dom_int > 0 && $dom_int < 7;
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

        <!-- Status badge (top-left) -->
        <?php if ($status && $status !== 'Active') : ?>
            <span class="absolute top-3 left-3 <?php echo esc_attr($status_class); ?> text-xs font-semibold px-2 py-0.5 rounded-md backdrop-blur-sm">
                <?php echo esc_html($status); ?>
            </span>
        <?php endif; ?>

        <!-- DOM badge (top-left, below status if present) -->
        <?php if ($is_new) : ?>
            <span class="absolute <?php echo ($status && $status !== 'Active') ? 'top-10' : 'top-3'; ?> left-3 bg-green-500 text-white text-xs font-semibold px-2 py-0.5 rounded-md">
                New
            </span>
        <?php endif; ?>

        <!-- Favorite heart (top-right) -->
        <?php if ($listing_id) : ?>
            <button class="fav-heart"
                    :class="(_favVersion, favStore?.isFavorite('<?php echo esc_js($listing_id); ?>')) ? 'is-favorite' : ''"
                    @click.prevent.stop="favStore?.toggle('<?php echo esc_js($listing_id); ?>')"
                    title="Save to favorites">
                <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>
        <?php endif; ?>

        <!-- Price badge -->
        <?php if ($price) : ?>
            <span class="absolute bottom-3 left-3 bg-teal-700/90 text-white text-sm font-semibold px-3 py-1 rounded-lg backdrop-blur-sm">
                <?php echo esc_html($price); ?>
            </span>
        <?php endif; ?>

        <!-- Type badge -->
        <?php if ($type) : ?>
            <span class="absolute bottom-3 right-3 bg-white/90 text-gray-700 text-xs font-medium px-2 py-1 rounded-md backdrop-blur-sm">
                <?php echo esc_html($type); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <div class="p-4">
        <h3 class="font-semibold text-gray-900 truncate group-hover:text-teal-700 transition-colors">
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
            <?php if ($dom_int > 0 && !$is_new) : ?>
                <span class="text-xs text-gray-400 ml-auto"><?php echo intval($dom_int); ?>d</span>
            <?php endif; ?>
        </div>
    </div>
</a>
