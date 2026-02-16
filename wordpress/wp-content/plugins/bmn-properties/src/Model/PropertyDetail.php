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
    /**
     * Transform the full detail data into a response array.
     *
     * @param object        $property    Database row from bmn_properties.
     * @param object[]      $photos      All media rows (ordered by order_index).
     * @param object|null   $agent       Agent row or null.
     * @param object|null   $office      Office row or null.
     * @param object[]      $openHouses  Upcoming open house rows.
     * @param object[]      $history     Price/status history rows.
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
            'unit_number'        => $property->unit_number ?? null,
            'city'               => $property->city ?? null,
            'state'              => $property->state_or_province ?? null,
            'zip'                => $property->postal_code ?? null,
            'county'             => $property->county_or_parish ?? null,
            'subdivision'        => $property->subdivision_name ?? null,

            // Pricing.
            'price'              => $property->list_price !== null ? (float) $property->list_price : null,
            'original_price'     => $property->original_list_price !== null ? (float) $property->original_list_price : null,
            'close_price'        => $property->close_price !== null ? (float) $property->close_price : null,
            'price_per_sqft'     => $property->price_per_sqft !== null ? (float) $property->price_per_sqft : null,

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
            'property_type'      => $property->property_type ?? null,
            'property_sub_type'  => $property->property_sub_type ?? null,
            'status'             => $property->standard_status ?? null,
            'is_archived'        => (bool) ($property->is_archived ?? false),

            // Location.
            'latitude'           => $property->latitude !== null ? (float) $property->latitude : null,
            'longitude'          => $property->longitude !== null ? (float) $property->longitude : null,

            // Dates.
            'list_date'          => $property->listing_contract_date ?? null,
            'close_date'         => $property->close_date ?? null,
            'dom'                => $property->days_on_market !== null ? (int) $property->days_on_market : null,

            // Description.
            'public_remarks'     => $property->public_remarks ?? null,
            'showing_instructions' => $property->showing_instructions ?? null,

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

            // Schools.
            'elementary_school'  => $property->elementary_school ?? null,
            'middle_school'      => $property->middle_or_junior_school ?? null,
            'high_school'        => $property->high_school ?? null,
            'school_district'    => $property->school_district ?? null,

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
