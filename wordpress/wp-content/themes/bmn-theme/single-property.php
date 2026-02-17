<?php
/**
 * Single Property Detail Page
 *
 * Overrides MLD plugin template at priority 1001.
 * Plugin still handles rewrite rules and mls_number query var.
 *
 * @package bmn_theme
 * @version 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract listing_id from query var
$mls_number = get_query_var('mls_number', '');

// Handle descriptive slug format (e.g., "58-oak-street-reading-3-bed-2-bath-for-sale-73469723")
// Extract trailing numeric ID from slug
if ($mls_number && !ctype_digit($mls_number)) {
    if (preg_match('/(\d{5,})$/', $mls_number, $matches)) {
        $mls_number = $matches[1];
    }
}

// Fetch property data (V2 detail endpoint includes photos and history)
$property = bmn_get_property_details($mls_number);

// Extract photos from detail response (avoids duplicate API call)
$photos = array();
if ($property && !empty($property['photos'])) {
    foreach ($property['photos'] as $photo) {
        $photos[] = is_array($photo) ? ($photo['url'] ?? '') : $photo;
    }
    $photos = array_filter($photos);
}

$history = $property ? bmn_get_property_price_history($mls_number) : array();

// 404 if property not found
if (!$property) {
    get_header();
    ?>
    <main id="main" class="flex-1 bg-gray-50">
        <div class="container mx-auto px-4 lg:px-8 py-16 text-center">
            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <h1 class="text-2xl font-bold text-gray-700 mb-2">Property Not Found</h1>
            <p class="text-gray-500 mb-6">The property you're looking for may no longer be available.</p>
            <a href="<?php echo esc_url(bmn_get_search_url()); ?>" class="btn-primary">
                Search Properties
            </a>
        </div>
    </main>
    <?php
    get_footer();
    return;
}

// Build property display values (V2 API field names)
$address      = $property['address'] ?? '';
$city         = $property['city'] ?? '';
$state        = $property['state'] ?? 'MA';
$zip          = $property['zip'] ?? '';
$list_price   = floatval($property['price'] ?? 0);
$close_price  = floatval($property['close_price'] ?? 0);
$price        = $close_price > 0 ? $close_price : $list_price;
$status       = $property['status'] ?? 'Active';
$beds         = $property['beds'] ?? '';
$baths        = $property['baths'] ?? '';
$sqft         = $property['sqft'] ?? '';
$lot_size     = $property['lot_size'] ?? '';
$year_built   = $property['year_built'] ?? '';
$description  = $property['public_remarks'] ?? '';
$listing_id   = $property['listing_id'] ?? $mls_number;
$lat          = $property['latitude'] ?? '';
$lng          = $property['longitude'] ?? '';

// Set page title
add_filter('pre_get_document_title', function () use ($address, $city, $state) {
    $title = $address;
    if ($city) {
        $title .= ", {$city}";
    }
    if ($state) {
        $title .= ", {$state}";
    }
    return $title . ' | ' . get_bloginfo('name');
});

get_header();
?>

<main id="main" class="flex-1 bg-gray-50">

    <!-- Photo Gallery (full width) -->
    <?php if (!empty($photos)) :
        get_template_part('template-parts/property/photo-gallery', null, array(
            'photos' => $photos,
        ));
    endif; ?>

    <!-- Property Content -->
    <div class="container mx-auto px-4 lg:px-8 py-6 lg:py-8">
        <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">

            <!-- Main Content Column -->
            <div class="flex-1 min-w-0 space-y-6">

                <!-- Address / Price Header Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div>
                            <h1 class="text-xl lg:text-2xl font-bold text-gray-900">
                                <?php echo esc_html($address); ?>
                            </h1>
                            <p class="text-gray-500 mt-0.5">
                                <?php echo esc_html(trim("{$city}, {$state} {$zip}")); ?>
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-2xl lg:text-3xl font-bold text-navy-700">
                                <?php echo esc_html(bmn_format_price($price)); ?>
                            </p>
                            <?php if ($status !== 'Active') : ?>
                                <span class="inline-block mt-1 px-2.5 py-0.5 text-xs font-semibold rounded-full
                                    <?php echo $status === 'Closed' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                    <?php echo esc_html($status); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Specs -->
                    <div class="flex flex-wrap gap-4 mt-4 pt-4 border-t border-gray-100">
                        <?php if ($beds) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html($beds); ?></p>
                                <p class="text-xs text-gray-500">Beds</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($baths) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html($baths); ?></p>
                                <p class="text-xs text-gray-500">Baths</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($sqft) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html(number_format(floatval($sqft))); ?></p>
                                <p class="text-xs text-gray-500">Sq Ft</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($lot_size) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html(number_format(floatval($lot_size))); ?></p>
                                <p class="text-xs text-gray-500">Lot Sq Ft</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($year_built) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html($year_built); ?></p>
                                <p class="text-xs text-gray-500">Year Built</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <?php if ($description) : ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-3">Description</h2>
                        <div class="prose prose-sm max-w-none text-gray-600">
                            <?php echo wpautop(esc_html($description)); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Specs Table -->
                <?php get_template_part('template-parts/property/specs-table', null, array('property' => $property)); ?>

                <!-- Price History -->
                <?php if (!empty($history)) :
                    get_template_part('template-parts/property/price-history', null, array('history' => $history));
                endif; ?>

                <!-- Nearby Schools -->
                <?php if ($lat && $lng) :
                    get_template_part('template-parts/property/nearby-schools', null, array(
                        'lat' => $lat,
                        'lng' => $lng,
                    ));
                endif; ?>

                <!-- MLS Info -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                    <p class="text-xs text-gray-400">
                        MLS# <?php echo esc_html($listing_id); ?>
                        &middot;
                        Data courtesy of MLS PIN
                    </p>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="lg:w-80 flex-shrink-0">
                <div class="lg:sticky lg:top-24 space-y-6">
                    <?php get_template_part('template-parts/property/agent-card', null, array(
                        'property' => $property,
                        'address'  => $address,
                    )); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
