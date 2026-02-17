<?php
/**
 * Property Specs Table
 *
 * 2-column responsive grid of property details.
 * Skips null/empty values. Reads from $property array (V2 API format).
 *
 * @package bmn_theme
 */

if (!defined('ABSPATH')) {
    exit;
}

$property = $args['property'] ?? array();
if (empty($property)) {
    return;
}

// Define spec groups with labels and V2 API property keys
$groups = array(
    'Interior' => array(
        'Bedrooms'    => $property['beds'] ?? null,
        'Bathrooms'   => $property['baths'] ?? null,
        'Full Baths'  => $property['baths_full'] ?? null,
        'Half Baths'  => $property['baths_half'] ?? null,
        'Sq Ft'       => !empty($property['sqft']) ? number_format(floatval($property['sqft'])) : null,
        'Rooms'       => $property['rooms_total'] ?? null,
        'Year Built'  => !empty($property['year_built']) ? $property['year_built'] : null,
        'Fireplaces'  => !empty($property['fireplaces_total']) ? $property['fireplaces_total'] : null,
    ),
    'Exterior' => array(
        'Lot Size'      => !empty($property['lot_size']) ? number_format(floatval($property['lot_size'])) . ' sq ft' : null,
        'Garage Spaces' => !empty($property['garage_spaces']) ? $property['garage_spaces'] : null,
        'Parking'       => !empty($property['parking_total']) ? $property['parking_total'] : null,
        'Property Type' => $property['property_sub_type'] ?? $property['property_type'] ?? null,
    ),
    'Financial' => array(
        'HOA Fee'      => !empty($property['association_fee']) ? '$' . number_format(floatval($property['association_fee'])) . '/mo' : null,
        'Tax Amount'   => !empty($property['tax_annual_amount']) ? '$' . number_format(floatval($property['tax_annual_amount'])) . '/yr' : null,
        'Tax Year'     => !empty($property['tax_year']) ? $property['tax_year'] : null,
    ),
);

// Check if there are any specs to show
$has_any = false;
foreach ($groups as $specs) {
    foreach ($specs as $value) {
        if ($value !== null && $value !== '' && $value !== 'N' && $value !== '0') {
            $has_any = true;
            break 2;
        }
    }
}

if (!$has_any) {
    return;
}
?>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Property Details</h2>

    <div class="space-y-6">
        <?php foreach ($groups as $group_name => $specs) :
            // Filter out empty values
            $visible_specs = array_filter($specs, function ($v) {
                return $v !== null && $v !== '' && $v !== 'N' && $v !== '0';
            });

            if (empty($visible_specs)) {
                continue;
            }

            // Format Y/N values
            foreach ($visible_specs as $label => &$value) {
                if ($value === 'Y' || $value === 'Yes' || $value === '1') {
                    $value = 'Yes';
                }
            }
            unset($value);
        ?>
            <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2"><?php echo esc_html($group_name); ?></h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                    <?php foreach ($visible_specs as $label => $value) : ?>
                        <div class="flex justify-between py-1.5 border-b border-gray-50">
                            <span class="text-sm text-gray-500"><?php echo esc_html($label); ?></span>
                            <span class="text-sm font-medium text-gray-900"><?php echo esc_html($value); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
