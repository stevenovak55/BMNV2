<?php

declare(strict_types=1);

namespace BMN\Properties\Model;

/**
 * Formats full property detail data into the API response shape.
 *
 * Includes everything in PropertyListItem plus: remarks, agent, office,
 * open houses, price history, tax info, association info, photos, etc.
 */
final class PropertyDetail
{
    private static function emptyToNull(?string $value): ?string
    {
        return ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') ? null : $value;
    }

    private static function zeroToNull(mixed $value): mixed
    {
        return ($value === null || $value === 0 || $value === '0' || $value === 0.0 || $value === '0.00') ? null : $value;
    }

    /**
     * Transform the full detail data into a response array.
     *
     * @param object        $property    Database row from bmn_properties.
     * @param object[]      $photos      All media rows (ordered by order_index).
     * @param object|null   $agent       Agent row or null.
     * @param object|null   $office      Office row or null.
     * @param object[]      $openHouses  Upcoming open house rows.
     * @param object[]      $history     Price/status history rows.
     * @param object[]      $rooms       Room rows from bmn_rooms.
     *
     * @return array<string, mixed>
     */
    public static function fromData(
        object $property,
        array $photos = [],
        ?object $agent = null,
        ?object $office = null,
        array $openHouses = [],
        array $history = [],
        array $rooms = [],
    ): array {
        $photoUrls = array_map(
            static fn (object $p): array => [
                'url'      => $p->media_url,
                'category' => $p->media_category ?? null,
                'order'    => (int) ($p->order_index ?? 0),
            ],
            $photos
        );

        $nextOpenHouse = $openHouses[0] ?? null;

        return [
            // Core identification.
            'listing_id'         => $property->listing_id,
            'listing_key'        => $property->listing_key,

            // Address.
            'address'            => $property->unparsed_address ?? null,
            'street_number'      => $property->street_number ?? null,
            'street_name'        => $property->street_name ?? null,
            'unit_number'        => self::emptyToNull($property->unit_number ?? null),
            'city'               => $property->city ?? null,
            'state'              => $property->state_or_province ?? null,
            'zip'                => $property->postal_code ?? null,
            'county'             => self::emptyToNull($property->county_or_parish ?? null),
            'subdivision'        => self::emptyToNull($property->subdivision_name ?? null),

            // Pricing.
            'price'              => $property->list_price !== null ? (float) $property->list_price : null,
            'original_price'     => self::zeroToNull($property->original_list_price !== null ? (float) $property->original_list_price : null),
            'close_price'        => self::zeroToNull($property->close_price !== null ? (float) $property->close_price : null),
            'price_per_sqft'     => self::zeroToNull($property->price_per_sqft !== null ? (float) $property->price_per_sqft : null),

            // Details.
            'beds'               => $property->bedrooms_total !== null ? (int) $property->bedrooms_total : null,
            'baths'              => $property->bathrooms_total !== null ? (int) $property->bathrooms_total : null,
            'baths_full'         => $property->bathrooms_full !== null ? (int) $property->bathrooms_full : null,
            'baths_half'         => $property->bathrooms_half !== null ? (int) $property->bathrooms_half : null,
            'sqft'               => $property->living_area !== null ? (int) $property->living_area : null,
            'lot_size'           => $property->lot_size_acres !== null ? (float) $property->lot_size_acres : null,
            'year_built'         => $property->year_built !== null ? (int) $property->year_built : null,
            'rooms_total'        => $property->rooms_total !== null ? (int) $property->rooms_total : null,
            'garage_spaces'      => $property->garage_spaces !== null ? (int) $property->garage_spaces : null,
            'parking_total'      => $property->parking_total !== null ? (int) $property->parking_total : null,
            'fireplaces_total'   => $property->fireplaces_total !== null ? (int) $property->fireplaces_total : null,

            // Property classification.
            'property_type'      => self::emptyToNull($property->property_type ?? null),
            'property_sub_type'  => self::emptyToNull($property->property_sub_type ?? null),
            'status'             => $property->standard_status ?? null,
            'is_archived'        => (bool) ($property->is_archived ?? false),

            // Location.
            'latitude'           => $property->latitude !== null ? (float) $property->latitude : null,
            'longitude'          => $property->longitude !== null ? (float) $property->longitude : null,

            // Dates.
            'list_date'          => self::emptyToNull($property->listing_contract_date ?? null),
            'close_date'         => self::emptyToNull($property->close_date ?? null),
            'dom'                => $property->days_on_market !== null ? (int) $property->days_on_market : null,

            // Description.
            'public_remarks'     => self::emptyToNull($property->public_remarks ?? null),
            'showing_instructions' => self::emptyToNull($property->showing_instructions ?? null),

            // Virtual tour.
            'virtual_tour_url'   => $property->virtual_tour_url_unbranded ?? null,

            // Photos.
            'main_photo_url'     => $property->main_photo_url ?? null,
            'photos'             => $photoUrls,
            'photo_count'        => $property->photo_count !== null ? (int) $property->photo_count : count($photos),

            // Tax / Association.
            'tax_annual_amount'  => $property->tax_annual_amount !== null ? (float) $property->tax_annual_amount : null,
            'tax_year'           => $property->tax_year !== null ? (int) $property->tax_year : null,
            'association_fee'    => $property->association_fee !== null ? (float) $property->association_fee : null,
            'association_yn'     => isset($property->association_yn) ? (bool) $property->association_yn : null,

            // Structure & Building.
            'stories_total'           => $property->stories_total !== null ? (int) $property->stories_total : null,
            'basement'                => self::emptyToNull($property->basement ?? null),
            'construction_materials'  => self::emptyToNull($property->construction_materials ?? null),
            'roof'                    => self::emptyToNull($property->roof ?? null),
            'foundation_details'      => self::emptyToNull($property->foundation_details ?? null),
            'architectural_style'     => self::emptyToNull($property->architectural_style ?? null),
            'property_condition'      => self::emptyToNull($property->property_condition ?? null),
            'structure_type'          => self::emptyToNull($property->structure_type ?? null),
            'above_grade_finished_area' => self::zeroToNull($property->above_grade_finished_area !== null ? (int) $property->above_grade_finished_area : null),
            'below_grade_finished_area' => self::zeroToNull($property->below_grade_finished_area !== null ? (int) $property->below_grade_finished_area : null),
            'building_area_total'     => self::zeroToNull($property->building_area_total !== null ? (int) $property->building_area_total : null),
            'building_name'           => self::emptyToNull($property->building_name ?? null),

            // Systems & Utilities.
            'heating'                 => self::emptyToNull($property->heating ?? null),
            'cooling'                 => self::emptyToNull($property->cooling ?? null),
            'water_source'            => self::emptyToNull($property->water_source ?? null),
            'sewer'                   => self::emptyToNull($property->sewer ?? null),

            // Interior features.
            'flooring'                => self::emptyToNull($property->flooring ?? null),
            'appliances'              => self::emptyToNull($property->appliances ?? null),
            'laundry_features'        => self::emptyToNull($property->laundry_features ?? null),
            'interior_features'       => self::emptyToNull($property->interior_features ?? null),
            'security_features'       => self::emptyToNull($property->security_features ?? null),

            // Exterior features.
            'exterior_features'       => self::emptyToNull($property->exterior_features ?? null),
            'patio_and_porch_features' => self::emptyToNull($property->patio_and_porch_features ?? null),
            'fencing'                 => self::emptyToNull($property->fencing ?? null),
            'pool_features'           => self::emptyToNull($property->pool_features ?? null),
            'waterfront_features'     => self::emptyToNull($property->waterfront_features ?? null),
            'view_description'        => self::emptyToNull($property->view_description ?? null),
            'parking_features'        => self::emptyToNull($property->parking_features ?? null),
            'lot_features'            => self::emptyToNull($property->lot_features ?? null),
            'community_features'      => self::emptyToNull($property->community_features ?? null),
            'accessibility_features'  => self::emptyToNull($property->accessibility_features ?? null),

            // Boolean flags.
            'pool_private_yn'         => isset($property->pool_private_yn) ? (bool) $property->pool_private_yn : null,
            'waterfront_yn'           => isset($property->waterfront_yn) ? (bool) $property->waterfront_yn : null,
            'view_yn'                 => isset($property->view_yn) ? (bool) $property->view_yn : null,
            'spa_yn'                  => isset($property->spa_yn) ? (bool) $property->spa_yn : null,
            'fireplace_yn'            => isset($property->fireplace_yn) ? (bool) $property->fireplace_yn : null,
            'cooling_yn'              => isset($property->cooling_yn) ? (bool) $property->cooling_yn : null,
            'heating_yn'              => isset($property->heating_yn) ? (bool) $property->heating_yn : null,
            'garage_yn'               => isset($property->garage_yn) ? (bool) $property->garage_yn : null,
            'attached_garage_yn'      => isset($property->attached_garage_yn) ? (bool) $property->attached_garage_yn : null,
            'senior_community_yn'     => isset($property->senior_community_yn) ? (bool) $property->senior_community_yn : null,
            'horse_yn'                => isset($property->horse_yn) ? (bool) $property->horse_yn : null,
            'home_warranty_yn'        => isset($property->home_warranty_yn) ? (bool) $property->home_warranty_yn : null,
            'property_attached_yn'    => isset($property->property_attached_yn) ? (bool) $property->property_attached_yn : null,
            'pets_dogs_allowed'       => isset($property->pets_dogs_allowed) ? (bool) $property->pets_dogs_allowed : null,
            'pets_cats_allowed'       => isset($property->pets_cats_allowed) ? (bool) $property->pets_cats_allowed : null,

            // Investment / Multi-family.
            'number_of_units_total'   => $property->number_of_units_total !== null ? (int) $property->number_of_units_total : null,
            'gross_income'            => self::zeroToNull($property->gross_income !== null ? (float) $property->gross_income : null),
            'net_operating_income'    => self::zeroToNull($property->net_operating_income !== null ? (float) $property->net_operating_income : null),
            'total_actual_rent'       => self::zeroToNull($property->total_actual_rent !== null ? (float) $property->total_actual_rent : null),

            // Financial.
            'tax_assessed_value'      => self::zeroToNull($property->tax_assessed_value !== null ? (float) $property->tax_assessed_value : null),
            'association_fee_frequency' => self::emptyToNull($property->association_fee_frequency ?? null),
            'buyer_agency_compensation' => self::emptyToNull($property->buyer_agency_compensation ?? null),
            'contingency'             => self::emptyToNull($property->contingency ?? null),

            // MA Compliance / Disclosures.
            'lead_paint'              => isset($property->lead_paint) ? (bool) $property->lead_paint : null,
            'title5'                  => self::emptyToNull($property->title5 ?? null),
            'disclosures'             => self::emptyToNull($property->disclosures ?? null),

            // Location / Zoning.
            'zoning'                  => self::emptyToNull($property->zoning ?? null),
            'parcel_number'           => self::emptyToNull($property->parcel_number ?? null),
            'mls_area_major'          => self::emptyToNull($property->mls_area_major ?? null),
            'mls_area_minor'          => self::emptyToNull($property->mls_area_minor ?? null),
            'lot_size_square_feet'    => self::zeroToNull($property->lot_size_square_feet !== null ? (float) $property->lot_size_square_feet : null),

            // Additional dates.
            'off_market_date'         => self::emptyToNull($property->off_market_date ?? null),
            'expiration_date'         => self::emptyToNull($property->expiration_date ?? null),
            'original_entry_timestamp' => self::emptyToNull($property->original_entry_timestamp ?? null),

            // Schools.
            'elementary_school'  => self::emptyToNull($property->elementary_school ?? null),
            'middle_school'      => self::emptyToNull($property->middle_or_junior_school ?? null),
            'high_school'        => self::emptyToNull($property->high_school ?? null),
            'school_district'    => self::emptyToNull($property->school_district ?? null),

            // Rooms.
            'rooms' => array_map(static fn (object $r): array => [
                'room_type'       => $r->room_type ?? null,
                'room_level'      => $r->room_level ?? null,
                'room_dimensions' => $r->room_dimensions ?? null,
                'room_area'       => $r->room_area !== null ? (float) $r->room_area : null,
                'room_description' => $r->room_description ?? null,
            ], $rooms),

            // Agent.
            'agent'              => $agent !== null ? [
                'name'    => $agent->full_name ?? null,
                'email'   => $agent->email ?? null,
                'phone'   => $agent->phone ?? null,
                'mls_id'  => $agent->agent_mls_id ?? null,
            ] : null,

            // Office.
            'office'             => $office !== null ? [
                'name'    => $office->office_name ?? null,
                'phone'   => $office->phone ?? null,
                'address' => $office->address ?? null,
                'city'    => $office->city ?? null,
                'state'   => $office->state_or_province ?? null,
                'zip'     => $office->postal_code ?? null,
            ] : null,

            // Open houses.
            'has_open_house'     => $nextOpenHouse !== null,
            'next_open_house'    => $nextOpenHouse !== null ? [
                'date'       => $nextOpenHouse->open_house_date,
                'start_time' => $nextOpenHouse->open_house_start_time,
                'end_time'   => $nextOpenHouse->open_house_end_time,
            ] : null,
            'open_houses'        => array_map(static fn (object $oh): array => [
                'date'       => $oh->open_house_date,
                'start_time' => $oh->open_house_start_time,
                'end_time'   => $oh->open_house_end_time,
                'type'       => $oh->open_house_type ?? null,
                'remarks'    => $oh->open_house_remarks ?? null,
            ], $openHouses),

            // Price history.
            'price_history'      => array_map(static fn (object $h): array => [
                'change_type' => $h->change_type,
                'field'       => $h->field_name,
                'old_value'   => $h->old_value,
                'new_value'   => $h->new_value,
                'changed_at'  => $h->changed_at,
            ], $history),

            // Exclusive flag.
            'is_exclusive'       => is_numeric($property->listing_id) && (int) $property->listing_id < 1000000,
        ];
    }
}
