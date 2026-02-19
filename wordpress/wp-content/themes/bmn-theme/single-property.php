<?php
/**
 * Single Property Detail Page
 *
 * Overrides MLD plugin template at priority 1001.
 * Plugin still handles rewrite rules and mls_number query var.
 *
 * @package bmn_theme
 * @version 2.2.0
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
$original_price = floatval($property['original_price'] ?? 0);
$status       = $property['status'] ?? 'Active';
$beds         = $property['beds'] ?? '';
$baths        = $property['baths'] ?? '';
$sqft         = $property['sqft'] ?? '';
$lot_size     = $property['lot_size'] ?? '';
$year_built   = $property['year_built'] ?? '';
$description  = $property['public_remarks'] ?? '';
$listing_id   = $property['listing_id'] ?? $mls_number;
$property_type = $property['property_sub_type'] ?? $property['property_type'] ?? '';
$is_rental    = stripos($property['property_type'] ?? '', 'lease') !== false;
$lat          = $property['latitude'] ?? '';
$lng          = $property['longitude'] ?? '';
$dom          = $property['dom'] ?? '';

// Exclusive listing detection
$is_exclusive = $property['is_exclusive'] ?? false;

// Price reduction detection
$price_reduced = ($original_price > 0 && $list_price > 0 && $original_price > $list_price);

// Virtual tour
$virtual_tour_url = $property['virtual_tour_url'] ?? '';

// Open houses (already formatted by API)
$open_houses = $property['open_houses'] ?? array();

// Agent / Office from API
$agent = $property['agent'] ?? array();
$office = $property['office'] ?? array();

// Listing dates
$list_date  = $property['list_date'] ?? '';
$close_date_str = $property['close_date'] ?? '';

// Showing instructions
$showing_instructions = $property['showing_instructions'] ?? '';

// Set page title (avoid duplicating city/state if already in address)
add_filter('pre_get_document_title', function () use ($address, $city, $state) {
    $title = $address;
    if ($city && stripos($address, $city) === false) {
        $title .= ", {$city}";
    }
    if ($state && stripos($address, $state) === false) {
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
                            <?php if ($is_exclusive) : ?>
                                <span class="inline-block mb-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">
                                    Exclusive Listing
                                </span>
                            <?php endif; ?>
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
                                <?php if ($is_rental) : ?>
                                    <span class="text-base font-normal text-gray-400">/mo</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($price_reduced) : ?>
                                <p class="text-sm text-gray-400 line-through">
                                    <?php echo esc_html(bmn_format_price($original_price)); ?>
                                </p>
                            <?php endif; ?>
                            <div class="flex items-center justify-end gap-1.5 mt-1">
                                <?php if ($is_rental) : ?>
                                    <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-purple-100 text-purple-700">
                                        For Rent
                                    </span>
                                <?php elseif ($status === 'Closed') : ?>
                                    <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                                        Sold
                                    </span>
                                <?php elseif ($status === 'Pending' || $status === 'Active Under Contract') : ?>
                                    <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">
                                        <?php echo esc_html($status); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($property_type) : ?>
                                    <span class="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">
                                        <?php echo esc_html($property_type); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
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
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html(number_format(floatval($lot_size), 2)); ?></p>
                                <p class="text-xs text-gray-500">Acres</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($year_built) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html($year_built); ?></p>
                                <p class="text-xs text-gray-500">Year Built</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($dom !== '' && $dom !== null) : ?>
                            <div class="text-center">
                                <p class="text-lg font-semibold text-gray-900"><?php echo esc_html($dom); ?></p>
                                <p class="text-xs text-gray-500">DOM</p>
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

                <!-- Virtual Tour -->
                <?php if (!empty($virtual_tour_url)) : ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-3">Virtual Tour</h2>
                        <?php
                        $can_embed = (
                            strpos($virtual_tour_url, 'matterport.com') !== false ||
                            strpos($virtual_tour_url, 'youtube.com') !== false ||
                            strpos($virtual_tour_url, 'youtube-nocookie.com') !== false ||
                            strpos($virtual_tour_url, 'vimeo.com') !== false
                        );
                        ?>
                        <?php if ($can_embed) : ?>
                            <div class="aspect-video rounded-lg overflow-hidden">
                                <iframe
                                    src="<?php echo esc_url($virtual_tour_url); ?>"
                                    class="w-full h-full"
                                    allowfullscreen
                                    loading="lazy"
                                    title="Virtual Tour"
                                ></iframe>
                            </div>
                        <?php else : ?>
                            <a href="<?php echo esc_url($virtual_tour_url); ?>"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 px-4 py-2.5 bg-navy-700 text-white text-sm font-medium rounded-lg hover:bg-navy-800 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                View Virtual Tour
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Open Houses -->
                <?php if (!empty($open_houses)) :
                    // Filter to upcoming open houses only
                    $upcoming = array_filter($open_houses, function ($oh) {
                        $end = $oh['end_time'] ?? '';
                        if (empty($end)) return false;
                        return strtotime($end) > current_time('timestamp');
                    });
                    if (!empty($upcoming)) :
                ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-3">Open Houses</h2>
                        <div class="space-y-3">
                            <?php foreach ($upcoming as $oh) :
                                $oh_date  = $oh['date'] ?? '';
                                $oh_start = $oh['start_time'] ?? '';
                                $oh_end   = $oh['end_time'] ?? '';
                                $oh_type  = $oh['type'] ?? '';

                                $formatted_date = $oh_date ? date('l, M j', strtotime($oh_date)) : '';
                                $formatted_start = $oh_start ? date('g:i A', strtotime($oh_start)) : '';
                                $formatted_end = $oh_end ? date('g:i A', strtotime($oh_end)) : '';
                            ?>
                                <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                                    <div class="flex-shrink-0 w-10 h-10 bg-green-50 text-green-700 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo esc_html($formatted_date); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo esc_html("{$formatted_start} - {$formatted_end}"); ?>
                                            <?php if ($oh_type) : ?>
                                                <span class="text-gray-400">&middot;</span>
                                                <?php echo esc_html($oh_type); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; endif; ?>

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

                <!-- Listing Office Info -->
                <?php if (!empty($office['name']) || !empty($agent['name'])) : ?>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-3">Listing Information</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                            <?php if (!empty($agent['name'])) : ?>
                                <div class="flex justify-between py-1.5 border-b border-gray-50">
                                    <span class="text-sm text-gray-500">Listing Agent</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo esc_html($agent['name']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($agent['phone'])) : ?>
                                <div class="flex justify-between py-1.5 border-b border-gray-50">
                                    <span class="text-sm text-gray-500">Agent Phone</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo esc_html($agent['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($office['name'])) : ?>
                                <div class="flex justify-between py-1.5 border-b border-gray-50">
                                    <span class="text-sm text-gray-500">Office</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo esc_html($office['name']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($office['phone'])) : ?>
                                <div class="flex justify-between py-1.5 border-b border-gray-50">
                                    <span class="text-sm text-gray-500">Office Phone</span>
                                    <span class="text-sm font-medium text-gray-900"><?php echo esc_html($office['phone']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- MLS Info Footer -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
                    <div class="space-y-1.5">
                        <p class="text-xs text-gray-400">
                            MLS# <?php echo esc_html($listing_id); ?>
                            &middot;
                            Data courtesy of MLS PIN
                        </p>
                        <?php if ($list_date || $close_date_str) : ?>
                            <p class="text-xs text-gray-400">
                                <?php if ($list_date) : ?>
                                    Listed: <?php echo esc_html(date('M j, Y', strtotime($list_date))); ?>
                                <?php endif; ?>
                                <?php if ($list_date && $close_date_str) : ?>
                                    &middot;
                                <?php endif; ?>
                                <?php if ($close_date_str) : ?>
                                    Closed: <?php echo esc_html(date('M j, Y', strtotime($close_date_str))); ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($showing_instructions)) : ?>
                            <p class="text-xs text-gray-500 mt-2">
                                <span class="font-medium">Showing Instructions:</span>
                                <?php echo esc_html($showing_instructions); ?>
                            </p>
                        <?php endif; ?>
                    </div>
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
