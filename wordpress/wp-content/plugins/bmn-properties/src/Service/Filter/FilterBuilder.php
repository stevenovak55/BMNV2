<?php

declare(strict_types=1);

namespace BMN\Properties\Service\Filter;

use BMN\Platform\Geocoding\GeocodingService;

/**
 * Core SQL engine for property search.
 *
 * Takes a filter array from the request, builds a prepared WHERE clause
 * and ORDER BY clause, and returns a FilterResult value object.
 *
 * All dynamic values use $wpdb->prepare() for safety.
 */
class FilterBuilder
{
    private \wpdb $wpdb;
    private GeocodingService $geocoding;
    private StatusResolver $statusResolver;
    private SortResolver $sortResolver;

    public function __construct(
        \wpdb $wpdb,
        GeocodingService $geocoding,
        StatusResolver $statusResolver,
        SortResolver $sortResolver,
    ) {
        $this->wpdb = $wpdb;
        $this->geocoding = $geocoding;
        $this->statusResolver = $statusResolver;
        $this->sortResolver = $sortResolver;
    }

    /**
     * Build a FilterResult from the given filter array.
     *
     * @param array<string, mixed> $filters Key-value pairs from the request.
     * @return FilterResult
     */
    public function build(array $filters): FilterResult
    {
        // Direct lookup bypasses all other filters.
        if ($this->hasDirectLookup($filters)) {
            return $this->buildDirectLookup($filters);
        }

        $conditions = [];

        // Status (default: Active).
        $conditions[] = $this->buildStatusCondition($filters);

        // Location filters.
        $this->addLocationConditions($filters, $conditions);

        // Type filters.
        $this->addTypeConditions($filters, $conditions);

        // Price filters.
        $this->addPriceConditions($filters, $conditions);

        // Room filters.
        $this->addRoomConditions($filters, $conditions);

        // Size filters.
        $this->addSizeConditions($filters, $conditions);

        // Time filters.
        $this->addTimeConditions($filters, $conditions);

        // Parking filters.
        $this->addParkingConditions($filters, $conditions);

        // Amenity filters.
        $this->addAmenityConditions($filters, $conditions);

        // Special filters.
        $this->addSpecialConditions($filters, $conditions);

        // Geo filters.
        $this->addGeoConditions($filters, $conditions);

        // Detect school filters.
        $schoolCriteria = $this->extractSchoolCriteria($filters);
        $hasSchoolFilters = $schoolCriteria !== [];

        $where = implode(' AND ', $conditions);
        $orderBy = $this->sortResolver->resolve($filters['sort'] ?? null);

        return new FilterResult(
            where: $where,
            orderBy: $orderBy,
            isDirectLookup: false,
            hasSchoolFilters: $hasSchoolFilters,
            schoolCriteria: $schoolCriteria,
            overfetchMultiplier: $hasSchoolFilters ? 10 : 1,
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Escape special characters for a LIKE query.
     */
    private function escapeLike(string $value): string
    {
        if (method_exists($this->wpdb, 'esc_like')) {
            return $this->wpdb->esc_like($value);
        }

        return addcslashes($value, '_%\\');
    }

    // ------------------------------------------------------------------
    // Direct Lookup
    // ------------------------------------------------------------------

    private function hasDirectLookup(array $filters): bool
    {
        return !empty($filters['mls_number']) || !empty($filters['address']);
    }

    private function buildDirectLookup(array $filters): FilterResult
    {
        $conditions = [];

        if (!empty($filters['mls_number'])) {
            $conditions[] = $this->wpdb->prepare('listing_id = %s', $filters['mls_number']);
        }

        if (!empty($filters['address'])) {
            $term = '%' . $this->escapeLike($filters['address']) . '%';
            $conditions[] = $this->wpdb->prepare('unparsed_address LIKE %s', $term);
        }

        // Direct lookup searches both active and archived.
        $where = implode(' AND ', $conditions);

        return new FilterResult(
            where: $where,
            orderBy: 'listing_contract_date DESC',
            isDirectLookup: true,
            hasSchoolFilters: false,
            schoolCriteria: [],
            overfetchMultiplier: 1,
        );
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    private function buildStatusCondition(array $filters): string
    {
        $status = $filters['status'] ?? 'Active';

        return $this->statusResolver->resolve($status);
    }

    // ------------------------------------------------------------------
    // Location
    // ------------------------------------------------------------------

    private function addLocationConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['city'])) {
            $cities = array_map('trim', explode(',', $filters['city']));
            $placeholders = implode(',', array_fill(0, count($cities), '%s'));
            $conditions[] = $this->wpdb->prepare(
                "city IN ({$placeholders})",
                ...$cities
            );
        }

        if (!empty($filters['zip'])) {
            $zips = array_map('trim', explode(',', $filters['zip']));
            $placeholders = implode(',', array_fill(0, count($zips), '%s'));
            $conditions[] = $this->wpdb->prepare(
                "postal_code IN ({$placeholders})",
                ...$zips
            );
        }

        if (!empty($filters['neighborhood'])) {
            $term = $filters['neighborhood'];
            $conditions[] = $this->wpdb->prepare(
                '(subdivision_name = %s OR mls_area_major = %s OR mls_area_minor = %s)',
                $term,
                $term,
                $term
            );
        }

        if (!empty($filters['street_name'])) {
            $term = '%' . $this->escapeLike($filters['street_name']) . '%';
            $conditions[] = $this->wpdb->prepare('street_name LIKE %s', $term);
        }
    }

    // ------------------------------------------------------------------
    // Geo
    // ------------------------------------------------------------------

    private function addGeoConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['bounds'])) {
            $parts = array_map('floatval', explode(',', $filters['bounds']));
            if (count($parts) === 4) {
                [$south, $west, $north, $east] = $parts;
                $conditions[] = $this->geocoding->buildSpatialBoundsCondition(
                    $north,
                    $south,
                    $east,
                    $west,
                    'coordinates'
                );
            }
        }

        if (!empty($filters['polygon'])) {
            $polygon = is_array($filters['polygon']) ? $filters['polygon'] : json_decode($filters['polygon'], true);
            if (is_array($polygon) && count($polygon) >= 3) {
                $conditions[] = $this->geocoding->buildSpatialPolygonCondition(
                    $polygon,
                    'coordinates'
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Type
    // ------------------------------------------------------------------

    private function addTypeConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['property_type'])) {
            $conditions[] = $this->wpdb->prepare('property_type = %s', $filters['property_type']);
        }

        if (!empty($filters['property_sub_type'])) {
            $types = array_map('trim', explode(',', $filters['property_sub_type']));
            if (count($types) === 1) {
                $conditions[] = $this->wpdb->prepare('property_sub_type = %s', $types[0]);
            } else {
                $placeholders = implode(',', array_fill(0, count($types), '%s'));
                $conditions[] = $this->wpdb->prepare(
                    "property_sub_type IN ({$placeholders})",
                    ...$types
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Price
    // ------------------------------------------------------------------

    private function addPriceConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['min_price'])) {
            $conditions[] = $this->wpdb->prepare('list_price >= %d', (int) $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $conditions[] = $this->wpdb->prepare('list_price <= %d', (int) $filters['max_price']);
        }

        if (!empty($filters['price_reduced'])) {
            $conditions[] = 'original_list_price > list_price';
        }
    }

    // ------------------------------------------------------------------
    // Rooms
    // ------------------------------------------------------------------

    private function addRoomConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['beds'])) {
            $conditions[] = $this->wpdb->prepare('bedrooms_total >= %d', (int) $filters['beds']);
        }

        if (!empty($filters['baths'])) {
            $conditions[] = $this->wpdb->prepare('bathrooms_total >= %d', (int) $filters['baths']);
        }
    }

    // ------------------------------------------------------------------
    // Size
    // ------------------------------------------------------------------

    private function addSizeConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['sqft_min'])) {
            $conditions[] = $this->wpdb->prepare('living_area >= %d', (int) $filters['sqft_min']);
        }

        if (!empty($filters['sqft_max'])) {
            $conditions[] = $this->wpdb->prepare('living_area <= %d', (int) $filters['sqft_max']);
        }

        if (!empty($filters['lot_size_min'])) {
            $lotMin = $this->normalizeLotSize((float) $filters['lot_size_min']);
            $conditions[] = $this->wpdb->prepare('lot_size_acres >= %f', $lotMin);
        }

        if (!empty($filters['lot_size_max'])) {
            $lotMax = $this->normalizeLotSize((float) $filters['lot_size_max']);
            $conditions[] = $this->wpdb->prepare('lot_size_acres <= %f', $lotMax);
        }
    }

    /**
     * Auto-convert lot size to acres if value > 100 (assumed sqft).
     */
    private function normalizeLotSize(float $value): float
    {
        if ($value > 100) {
            return $value / 43560.0;
        }

        return $value;
    }

    // ------------------------------------------------------------------
    // Time
    // ------------------------------------------------------------------

    private function addTimeConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['year_built_min'])) {
            $conditions[] = $this->wpdb->prepare('year_built >= %d', (int) $filters['year_built_min']);
        }

        if (!empty($filters['year_built_max'])) {
            $conditions[] = $this->wpdb->prepare('year_built <= %d', (int) $filters['year_built_max']);
        }

        if (!empty($filters['max_dom'])) {
            $conditions[] = $this->wpdb->prepare('days_on_market <= %d', (int) $filters['max_dom']);
        }

        if (!empty($filters['min_dom'])) {
            $conditions[] = $this->wpdb->prepare('days_on_market >= %d', (int) $filters['min_dom']);
        }

        if (!empty($filters['new_listing_days'])) {
            $days = (int) $filters['new_listing_days'];
            $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($days * 86400));
            $conditions[] = $this->wpdb->prepare('listing_contract_date >= %s', $cutoff);
        }
    }

    // ------------------------------------------------------------------
    // Parking
    // ------------------------------------------------------------------

    private function addParkingConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['garage_spaces_min'])) {
            $conditions[] = $this->wpdb->prepare('garage_spaces >= %d', (int) $filters['garage_spaces_min']);
        }

        if (!empty($filters['parking_total_min'])) {
            $conditions[] = $this->wpdb->prepare('parking_total >= %d', (int) $filters['parking_total_min']);
        }
    }

    // ------------------------------------------------------------------
    // Amenity
    // ------------------------------------------------------------------

    private function addAmenityConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['has_virtual_tour'])) {
            $conditions[] = 'virtual_tour_url_unbranded IS NOT NULL';
        }

        if (!empty($filters['has_garage'])) {
            $conditions[] = 'garage_spaces > 0';
        }

        if (!empty($filters['has_fireplace'])) {
            $conditions[] = 'fireplaces_total > 0';
        }
    }

    // ------------------------------------------------------------------
    // Special
    // ------------------------------------------------------------------

    private function addSpecialConditions(array $filters, array &$conditions): void
    {
        if (!empty($filters['open_house_only'])) {
            $openHouseTable = $this->wpdb->prefix . 'bmn_open_houses';
            $conditions[] = "listing_key IN (SELECT listing_key FROM {$openHouseTable} WHERE open_house_date >= CURDATE())";
        }

        if (!empty($filters['exclusive_only'])) {
            $conditions[] = 'CAST(listing_id AS UNSIGNED) < 1000000';
        }
    }

    // ------------------------------------------------------------------
    // School (detection only — post-query via Phase 5 hook)
    // ------------------------------------------------------------------

    private function extractSchoolCriteria(array $filters): array
    {
        $criteria = [];

        $schoolKeys = ['school_grade', 'school_district', 'elementary_school', 'middle_school', 'high_school'];

        foreach ($schoolKeys as $key) {
            if (!empty($filters[$key])) {
                $criteria[$key] = $filters[$key];
            }
        }

        return $criteria;
    }
}
