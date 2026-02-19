<?php
/**
 * Property Specs Table - Full Detail View
 *
 * 12 dynamic sections matching iOS FactSection pattern.
 * Sections only render if they have data.
 * Property type determines which sections are shown/hidden.
 *
 * @package bmn_theme
 * @version 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$property = $args['property'] ?? array();
if (empty($property)) {
    return;
}

// ── Property Type Detection (matching iOS PropertyTypeCategory.from()) ──

$property_type    = strtolower($property['property_type'] ?? '');
$property_subtype = strtolower($property['property_sub_type'] ?? '');

if (strpos($property_type, 'lease') !== false) {
    $category = 'rental';
} elseif (strpos($property_type, 'income') !== false || strpos($property_subtype, 'multi') !== false) {
    $category = 'multiFamilyInvestment';
} elseif (strpos($property_type, 'land') !== false) {
    $category = 'land';
} elseif (strpos($property_type, 'commercial') !== false) {
    $category = 'commercial';
} elseif (in_array($property_subtype, ['condominium', 'townhouse', 'cooperative', 'condo'])) {
    $category = 'condoTownhouse';
} else {
    $category = 'singleFamily';
}

// ── Section Visibility by Property Type (matching iOS) ──

$hidden_sections = array();
switch ($category) {
    case 'singleFamily':
        $hidden_sections = ['Investment Metrics', 'HOA & Community'];
        break;
    case 'condoTownhouse':
        $hidden_sections = ['Investment Metrics'];
        break;
    case 'rental':
        $hidden_sections = ['Investment Metrics', 'Financial & Tax', 'Disclosures'];
        break;
    case 'multiFamilyInvestment':
        $hidden_sections = ['HOA & Community'];
        break;
    case 'land':
        $hidden_sections = ['Interior Features', 'Exterior & Structure', 'Floor Layout', 'Parking & Garage', 'HOA & Community'];
        break;
    case 'commercial':
        $hidden_sections = ['Interior Features'];
        break;
}

// ── Helper: get a displayable value, skip nulls/empty/JSON empties ──

if (!function_exists('bmn_spec_val')) {
    function bmn_spec_val(array $property, string ...$keys): ?string {
        foreach ($keys as $key) {
            $val = $property[$key] ?? null;
            if ($val === null || $val === '' || $val === 0 || $val === '0') {
                continue;
            }
            // Handle booleans from API
            if (is_bool($val)) {
                return $val ? 'Yes' : null;
            }
            // Handle arrays (API may return decoded JSON)
            if (is_array($val)) {
                $val = array_filter($val, function ($v) { return $v !== '' && $v !== null; });
                if (empty($val)) continue;
                return implode(', ', $val);
            }
            $str = (string) $val;
            // Filter empty JSON arrays from TEXT columns
            if ($str === '[]' || $str === '[""]' || $str === 'null') {
                continue;
            }
            // Decode JSON array strings into comma-separated display
            if (isset($str[0]) && $str[0] === '[') {
                $decoded = json_decode($str, true);
                if (is_array($decoded)) {
                    $decoded = array_filter($decoded, function ($v) { return $v !== '' && $v !== null; });
                    if (empty($decoded)) continue;
                    return implode(', ', $decoded);
                }
            }
            return $str;
        }
        return null;
    }
}

if (!function_exists('bmn_spec_yn')) {
    function bmn_spec_yn(array $property, string $key): ?string {
        $val = $property[$key] ?? null;
        if ($val === null) {
            return null;
        }
        return $val ? 'Yes' : 'No';
    }
}

// ── Build All 12 Sections (using V2 API field names) ──

$sections = array();

// 1. Interior Features
$interior = array();
if ($v = bmn_spec_val($property, 'beds'))              $interior['Bedrooms'] = $v;
if ($v = bmn_spec_val($property, 'baths'))              $interior['Bathrooms'] = $v;
if ($v = bmn_spec_val($property, 'baths_full'))         $interior['Full Baths'] = $v;
if ($v = bmn_spec_val($property, 'baths_half'))         $interior['Half Baths'] = $v;
$sqft = $property['sqft'] ?? null;
if ($sqft && floatval($sqft) > 0) {
    $interior['Sq Ft'] = number_format(floatval($sqft));
}
$above = $property['above_grade_finished_area'] ?? null;
if ($above && floatval($above) > 0) {
    $interior['Above Grade Sq Ft'] = number_format(floatval($above));
}
$below = $property['below_grade_finished_area'] ?? null;
if ($below && floatval($below) > 0) {
    $interior['Below Grade Sq Ft'] = number_format(floatval($below));
}
if ($v = bmn_spec_val($property, 'rooms_total'))        $interior['Total Rooms'] = $v;
if ($v = bmn_spec_val($property, 'fireplaces_total'))   $interior['Fireplaces'] = $v;
if ($v = bmn_spec_val($property, 'flooring'))           $interior['Flooring'] = $v;
if ($v = bmn_spec_val($property, 'appliances'))         $interior['Appliances'] = $v;
if ($v = bmn_spec_val($property, 'laundry_features'))   $interior['Laundry'] = $v;
if ($v = bmn_spec_val($property, 'interior_features'))  $interior['Interior Features'] = $v;
if ($v = bmn_spec_val($property, 'security_features'))  $interior['Security'] = $v;
if ($v = bmn_spec_val($property, 'year_built'))         $interior['Year Built'] = $v;
if (!empty($interior)) $sections['Interior Features'] = $interior;

// 2. Exterior & Structure
$exterior = array();
if ($v = bmn_spec_val($property, 'construction_materials'))   $exterior['Construction'] = $v;
if ($v = bmn_spec_val($property, 'roof'))                     $exterior['Roof'] = $v;
if ($v = bmn_spec_val($property, 'foundation_details'))       $exterior['Foundation'] = $v;
if ($v = bmn_spec_val($property, 'architectural_style'))      $exterior['Style'] = $v;
if ($v = bmn_spec_val($property, 'property_condition'))       $exterior['Condition'] = $v;
if ($v = bmn_spec_val($property, 'basement'))                 $exterior['Basement'] = $v;
if ($v = bmn_spec_val($property, 'stories_total'))            $exterior['Stories'] = $v;
if ($v = bmn_spec_val($property, 'structure_type'))           $exterior['Structure Type'] = $v;
if (($v = bmn_spec_yn($property, 'property_attached_yn')) !== null) $exterior['Attached'] = $v;
if ($v = bmn_spec_val($property, 'exterior_features'))        $exterior['Exterior Features'] = $v;
if ($v = bmn_spec_val($property, 'patio_and_porch_features')) $exterior['Patio & Porch'] = $v;
if ($v = bmn_spec_val($property, 'fencing'))                  $exterior['Fencing'] = $v;
if (($v = bmn_spec_yn($property, 'pool_private_yn')) !== null) $exterior['Private Pool'] = $v;
if ($v = bmn_spec_val($property, 'pool_features'))            $exterior['Pool Features'] = $v;
if (($v = bmn_spec_yn($property, 'spa_yn')) !== null)         $exterior['Spa'] = $v;
if (($v = bmn_spec_yn($property, 'waterfront_yn')) !== null)  $exterior['Waterfront'] = $v;
if ($v = bmn_spec_val($property, 'waterfront_features'))      $exterior['Waterfront Features'] = $v;
if (($v = bmn_spec_yn($property, 'view_yn')) !== null)        $exterior['View'] = $v;
if ($v = bmn_spec_val($property, 'view_description'))         $exterior['View Description'] = $v;
if (!empty($exterior)) $sections['Exterior & Structure'] = $exterior;

// 3. Lot & Land
$lot = array();
$lot_acres = $property['lot_size'] ?? null;
$lot_sqft  = $property['lot_size_square_feet'] ?? null;
if ($lot_acres && floatval($lot_acres) > 0) {
    $lot['Lot Size'] = number_format(floatval($lot_acres), 2) . ' acres';
}
if ($lot_sqft && floatval($lot_sqft) > 0) {
    $lot['Lot Sq Ft'] = number_format(floatval($lot_sqft)) . ' sq ft';
}
if ($v = bmn_spec_val($property, 'lot_features'))     $lot['Lot Features'] = $v;
if ($v = bmn_spec_val($property, 'zoning'))            $lot['Zoning'] = $v;
if ($v = bmn_spec_val($property, 'parcel_number'))     $lot['Parcel Number'] = $v;
if (!empty($lot)) $sections['Lot & Land'] = $lot;

// 4. Parking & Garage
$parking = array();
if ($v = bmn_spec_val($property, 'garage_spaces'))     $parking['Garage Spaces'] = $v;
if ($v = bmn_spec_val($property, 'parking_total'))     $parking['Total Parking'] = $v;
if (($v = bmn_spec_yn($property, 'garage_yn')) !== null)          $parking['Garage'] = $v;
if (($v = bmn_spec_yn($property, 'attached_garage_yn')) !== null) $parking['Attached Garage'] = $v;
if ($v = bmn_spec_val($property, 'parking_features'))  $parking['Parking Features'] = $v;
if (!empty($parking)) $sections['Parking & Garage'] = $parking;

// 5. Utilities & Systems
$utilities = array();
if ($v = bmn_spec_val($property, 'heating'))           $utilities['Heating'] = $v;
if ($v = bmn_spec_val($property, 'cooling'))           $utilities['Cooling'] = $v;
if ($v = bmn_spec_val($property, 'water_source'))      $utilities['Water'] = $v;
if ($v = bmn_spec_val($property, 'sewer'))             $utilities['Sewer'] = $v;
if (!empty($utilities)) $sections['Utilities & Systems'] = $utilities;

// 6. HOA & Community
$hoa = array();
if (($v = bmn_spec_yn($property, 'association_yn')) !== null) $hoa['HOA'] = $v;
$assoc_fee = $property['association_fee'] ?? null;
if ($assoc_fee && floatval($assoc_fee) > 0) {
    $freq = $property['association_fee_frequency'] ?? 'Monthly';
    $hoa['HOA Fee'] = '$' . number_format(floatval($assoc_fee)) . '/' . strtolower(substr($freq, 0, 3));
}
if ($v = bmn_spec_val($property, 'community_features'))      $hoa['Community Features'] = $v;
if (($v = bmn_spec_yn($property, 'senior_community_yn')) !== null) $hoa['Senior Community'] = $v;
if (($v = bmn_spec_yn($property, 'pets_dogs_allowed')) !== null)   $hoa['Dogs Allowed'] = $v;
if (($v = bmn_spec_yn($property, 'pets_cats_allowed')) !== null)   $hoa['Cats Allowed'] = $v;
if (!empty($hoa)) $sections['HOA & Community'] = $hoa;

// 7. Financial & Tax
$financial = array();
$tax = $property['tax_annual_amount'] ?? null;
if ($tax && floatval($tax) > 0) {
    $financial['Annual Tax'] = '$' . number_format(floatval($tax));
}
if ($v = bmn_spec_val($property, 'tax_year'))                      $financial['Tax Year'] = $v;
$assessed = $property['tax_assessed_value'] ?? null;
if ($assessed && floatval($assessed) > 0) {
    $financial['Assessed Value'] = '$' . number_format(floatval($assessed));
}
if ($v = bmn_spec_val($property, 'buyer_agency_compensation'))     $financial['Buyer Agency Comp'] = $v;
if ($v = bmn_spec_val($property, 'contingency'))                   $financial['Contingency'] = $v;
if (!empty($financial)) $sections['Financial & Tax'] = $financial;

// 8. Investment Metrics
$investment = array();
if ($v = bmn_spec_val($property, 'number_of_units_total'))  $investment['Total Units'] = $v;
$gross = $property['gross_income'] ?? null;
if ($gross && floatval($gross) > 0) {
    $investment['Gross Income'] = '$' . number_format(floatval($gross));
}
$noi = $property['net_operating_income'] ?? null;
if ($noi && floatval($noi) > 0) {
    $investment['Net Operating Income'] = '$' . number_format(floatval($noi));
}
$rent = $property['total_actual_rent'] ?? null;
if ($rent && floatval($rent) > 0) {
    $investment['Total Actual Rent'] = '$' . number_format(floatval($rent));
}
if (!empty($investment)) $sections['Investment Metrics'] = $investment;

// 9. Disclosures (MA Compliance)
$disclosures = array();
if (($v = bmn_spec_yn($property, 'lead_paint')) !== null)          $disclosures['Lead Paint'] = $v;
if ($v = bmn_spec_val($property, 'title5'))                        $disclosures['Title V'] = $v;
if ($v = bmn_spec_val($property, 'disclosures'))                   $disclosures['Disclosures'] = $v;
if (!empty($disclosures)) $sections['Disclosures'] = $disclosures;

// 10. Floor Layout / Rooms
$rooms_data = $property['rooms'] ?? array();
if (!empty($rooms_data) && is_array($rooms_data)) {
    $sections['Floor Layout'] = '__rooms__';
}

// 11. Dates & Market
$dates = array();
$list_date = $property['list_date'] ?? null;
if ($list_date) $dates['List Date'] = date('M j, Y', strtotime($list_date));
$close_date = $property['close_date'] ?? null;
if ($close_date) $dates['Close Date'] = date('M j, Y', strtotime($close_date));
$off_market = $property['off_market_date'] ?? null;
if ($off_market) $dates['Off Market Date'] = date('M j, Y', strtotime($off_market));
if ($v = bmn_spec_val($property, 'dom'))               $dates['Days on Market'] = $v;
$orig_entry = $property['original_entry_timestamp'] ?? null;
if ($orig_entry) $dates['Originally Listed'] = date('M j, Y', strtotime($orig_entry));
if (!empty($dates)) $sections['Dates & Market'] = $dates;

// 12. Additional Details
$additional = array();
if ($v = bmn_spec_val($property, 'accessibility_features'))   $additional['Accessibility'] = $v;
if (($v = bmn_spec_yn($property, 'horse_yn')) !== null)       $additional['Horse Property'] = $v;
if (($v = bmn_spec_yn($property, 'home_warranty_yn')) !== null) $additional['Home Warranty'] = $v;
if ($v = bmn_spec_val($property, 'mls_area_major'))           $additional['MLS Area'] = $v;
if ($v = bmn_spec_val($property, 'mls_area_minor'))           $additional['MLS Sub-Area'] = $v;
if ($v = bmn_spec_val($property, 'building_name'))            $additional['Building Name'] = $v;
if ($v = bmn_spec_val($property, 'subdivision'))              $additional['Subdivision'] = $v;
if ($v = bmn_spec_val($property, 'school_district'))          $additional['School District'] = $v;
if ($v = bmn_spec_val($property, 'elementary_school'))        $additional['Elementary School'] = $v;
if ($v = bmn_spec_val($property, 'middle_school'))            $additional['Middle School'] = $v;
if ($v = bmn_spec_val($property, 'high_school'))              $additional['High School'] = $v;
if (!empty($additional)) $sections['Additional Details'] = $additional;

// ── Remove hidden sections for this property type ──

foreach ($hidden_sections as $hidden) {
    unset($sections[$hidden]);
}

// ── Check if anything to show ──

if (empty($sections)) {
    return;
}
?>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 lg:p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Property Details</h2>

    <div class="space-y-6">
        <?php foreach ($sections as $section_name => $specs) :
            if (empty($specs)) {
                continue;
            }

            // Special rendering for Rooms section
            if ($specs === '__rooms__') :
                $rooms = $property['rooms'] ?? array();
                if (empty($rooms)) continue;
        ?>
            <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2"><?php echo esc_html($section_name); ?></h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 pr-4 font-medium text-gray-500">Room</th>
                                <th class="text-left py-2 pr-4 font-medium text-gray-500">Level</th>
                                <th class="text-left py-2 pr-4 font-medium text-gray-500">Dimensions</th>
                                <th class="text-left py-2 font-medium text-gray-500">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room) :
                                $room = is_array($room) ? $room : (array) $room;
                            ?>
                                <tr class="border-b border-gray-50">
                                    <td class="py-1.5 pr-4 font-medium text-gray-900"><?php echo esc_html($room['room_type'] ?? ''); ?></td>
                                    <td class="py-1.5 pr-4 text-gray-600"><?php echo esc_html($room['room_level'] ?? ''); ?></td>
                                    <td class="py-1.5 pr-4 text-gray-600"><?php echo esc_html($room['room_dimensions'] ?? ''); ?></td>
                                    <td class="py-1.5 text-gray-600"><?php echo esc_html($room['room_description'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php
            else :
                // Standard key-value section
        ?>
            <div>
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2"><?php echo esc_html($section_name); ?></h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                    <?php foreach ($specs as $label => $value) : ?>
                        <div class="flex justify-between py-1.5 border-b border-gray-50">
                            <span class="text-sm text-gray-500"><?php echo esc_html($label); ?></span>
                            <span class="text-sm font-medium text-gray-900 text-right max-w-[60%]"><?php echo esc_html($value); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
