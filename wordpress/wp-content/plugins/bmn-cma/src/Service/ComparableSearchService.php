<?php

declare(strict_types=1);

namespace BMN\CMA\Service;

use wpdb;

/**
 * Searches the extractor's bmn_properties table for comparable sales.
 *
 * Uses Haversine distance formula for geographic proximity matching.
 */
class ComparableSearchService
{
    private readonly wpdb $wpdb;
    private readonly string $propertiesTable;

    /** @var array<string, mixed> Default search filters. */
    private const DEFAULT_FILTERS = [
        'radius_miles' => 3,
        'min_comps'    => 5,
        'max_comps'    => 20,
        'months_back'  => 12,
        'status'       => 'Closed',
    ];

    /** @var int[] Expanding radius tiers in miles. */
    private const RADIUS_TIERS = [1, 2, 3, 5, 10];

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->propertiesTable = $this->wpdb->prefix . 'bmn_properties';
    }

    /**
     * Find comparable properties based on subject property and filters.
     *
     * @param array $subject Must contain: latitude, longitude, listing_id
     * @param array $filters Optional overrides for default filters
     * @return array Rows from bmn_properties with distance_miles appended
     */
    public function findComparables(array $subject, array $filters = []): array
    {
        $lat = (float) ($subject['latitude'] ?? 0);
        $lng = (float) ($subject['longitude'] ?? 0);
        $listingId = (string) ($subject['listing_id'] ?? '');

        if ($lat === 0.0 || $lng === 0.0 || $listingId === '') {
            return [];
        }

        $filters = array_merge(self::DEFAULT_FILTERS, $filters);

        $radiusMiles = (float) $filters['radius_miles'];
        $maxComps = (int) $filters['max_comps'];
        $monthsBack = (int) $filters['months_back'];
        $status = (string) $filters['status'];

        // Haversine distance formula.
        $haversine = "(3959 * ACOS(COS(RADIANS(%f)) * COS(RADIANS(latitude)) * COS(RADIANS(longitude) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS(latitude))))";

        $where = [];
        $params = [$lat, $lng, $lat]; // For Haversine formula.

        // Status filter.
        $where[] = 'standard_status = %s';
        $params[] = $status;

        // Time filter using current_time('mysql') for timezone safety.
        $where[] = 'close_date >= DATE_SUB(%s, INTERVAL %d MONTH)';
        $params[] = current_time('mysql');
        $params[] = $monthsBack;

        // Exclude the subject property.
        $where[] = 'listing_id != %s';
        $params[] = $listingId;

        // Optional property type filter.
        if (!empty($filters['property_type'])) {
            $where[] = 'property_type = %s';
            $params[] = (string) $filters['property_type'];
        }

        // Optional bedrooms filter (+/- 2).
        if (!empty($subject['bedrooms_total'])) {
            $beds = (int) $subject['bedrooms_total'];
            $where[] = 'bedrooms_total BETWEEN %d AND %d';
            $params[] = $beds - 2;
            $params[] = $beds + 2;
        }

        // Optional bathrooms filter (+/- 2).
        if (!empty($subject['bathrooms_total'])) {
            $baths = (int) $subject['bathrooms_total'];
            $where[] = 'bathrooms_total BETWEEN %d AND %d';
            $params[] = $baths - 2;
            $params[] = $baths + 2;
        }

        // Optional sqft filter (+/- 30%).
        if (!empty($subject['living_area'])) {
            $sqft = (int) $subject['living_area'];
            $where[] = 'living_area BETWEEN %d AND %d';
            $params[] = (int) ($sqft * 0.7);
            $params[] = (int) ($sqft * 1.3);
        }

        // Optional year built filter (+/- 15 years).
        if (!empty($subject['year_built'])) {
            $year = (int) $subject['year_built'];
            $where[] = 'year_built BETWEEN %d AND %d';
            $params[] = $year - 15;
            $params[] = $year + 15;
        }

        $whereClause = implode(' AND ', $where);

        // Add radius constraint and params for HAVING.
        $params[] = $radiusMiles;
        $params[] = $maxComps;

        $sql = $this->wpdb->prepare(
            "SELECT *, {$haversine} AS distance_miles
             FROM {$this->propertiesTable}
             WHERE {$whereClause}
             HAVING distance_miles <= %f
             ORDER BY distance_miles ASC
             LIMIT %d",
            ...$params
        );

        return $this->wpdb->get_results($sql) ?? [];
    }

    /**
     * Expand search through radius tiers until minimum comparables are found.
     *
     * @param array $subject Subject property data
     * @param array $filters Search filters
     * @param int $currentCount Current number of comparables found
     * @return array Comparable results
     */
    public function expandSearch(array $subject, array $filters, int $currentCount): array
    {
        $minComps = (int) ($filters['min_comps'] ?? self::DEFAULT_FILTERS['min_comps']);

        if ($currentCount >= $minComps) {
            return $this->findComparables($subject, $filters);
        }

        foreach (self::RADIUS_TIERS as $radius) {
            $expandedFilters = array_merge($filters, ['radius_miles' => $radius]);
            $results = $this->findComparables($subject, $expandedFilters);

            if (count($results) >= $minComps) {
                return $results;
            }
        }

        // Return whatever we found at the largest radius.
        $tiers = self::RADIUS_TIERS;
        return $this->findComparables($subject, array_merge($filters, [
            'radius_miles' => end($tiers) ?: 10,
        ]));
    }
}
