<?php

declare(strict_types=1);

namespace BMN\Properties\Model;

/**
 * Formats a database row into the list-view response shape.
 *
 * This is a pure data transformer — no dependencies.
 */
final class PropertyListItem
{
    /**
     * Transform a database row into a list-item array.
     *
     * @param object       $row         Database row from bmn_properties.
     * @param object[]     $photos      Media rows for this listing.
     * @param object|null  $nextOpenHouse Next upcoming open house, or null.
     *
     * @return array<string, mixed>
     */
    public static function fromRow(object $row, array $photos = [], ?object $nextOpenHouse = null): array
    {
        $photoUrls = array_map(
            static fn (object $p): string => $p->media_url,
            $photos
        );

        return [
            'listing_id'       => $row->listing_id,
            'listing_key'      => $row->listing_key,
            'address'          => $row->unparsed_address ?? null,
            'street_number'    => $row->street_number ?? null,
            'street_name'      => $row->street_name ?? null,
            'unit_number'      => $row->unit_number ?? null,
            'city'             => $row->city ?? null,
            'state'            => $row->state_or_province ?? null,
            'zip'              => $row->postal_code ?? null,
            'price'            => $row->list_price !== null ? (float) $row->list_price : null,
            'original_price'   => $row->original_list_price !== null ? (float) $row->original_list_price : null,
            'beds'             => $row->bedrooms_total !== null ? (int) $row->bedrooms_total : null,
            'baths'            => $row->bathrooms_total !== null ? (int) $row->bathrooms_total : null,
            'baths_full'       => isset($row->bathrooms_full) && $row->bathrooms_full !== null ? (int) $row->bathrooms_full : null,
            'baths_half'       => isset($row->bathrooms_half) && $row->bathrooms_half !== null ? (int) $row->bathrooms_half : null,
            'sqft'             => $row->living_area !== null ? (int) $row->living_area : null,
            'property_type'    => $row->property_type ?? null,
            'property_sub_type' => $row->property_sub_type ?? null,
            'status'           => $row->standard_status ?? null,
            'latitude'         => $row->latitude !== null ? (float) $row->latitude : null,
            'longitude'        => $row->longitude !== null ? (float) $row->longitude : null,
            'list_date'        => $row->listing_contract_date ?? null,
            'dom'              => $row->days_on_market !== null ? (int) $row->days_on_market : null,
            'main_photo_url'   => $row->main_photo_url ?? null,
            'photos'           => $photoUrls,
            'year_built'       => $row->year_built !== null ? (int) $row->year_built : null,
            'lot_size'         => $row->lot_size_acres !== null ? (float) $row->lot_size_acres : null,
            'garage_spaces'    => $row->garage_spaces !== null ? (int) $row->garage_spaces : null,
            'has_open_house'   => $nextOpenHouse !== null,
            'next_open_house'  => $nextOpenHouse !== null ? [
                'date'       => $nextOpenHouse->open_house_date,
                'start_time' => $nextOpenHouse->open_house_start_time,
                'end_time'   => $nextOpenHouse->open_house_end_time,
            ] : null,
            'is_exclusive'     => is_numeric($row->listing_id) && (int) $row->listing_id < 1000000,
            'grouping_address' => self::buildGroupingAddress($row),
        ];
    }

    /**
     * Build a grouping address for V1 API parity.
     *
     * Combines street_number + street_name (without unit) so that
     * multi-unit properties at the same address group together.
     */
    private static function buildGroupingAddress(object $row): ?string
    {
        $parts = array_filter([
            $row->street_number ?? null,
            $row->street_name ?? null,
        ]);

        return $parts !== [] ? implode(' ', $parts) : null;
    }
}
