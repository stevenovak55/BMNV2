<?php

declare(strict_types=1);

namespace BMN\Schools\Repository;

use BMN\Platform\Database\Repository;
use BMN\Platform\Geocoding\GeocodingService;

/**
 * Repository for the bmn_schools table.
 */
class SchoolRepository extends Repository
{
    private readonly GeocodingService $geocoding;

    public function __construct(\wpdb $wpdb, GeocodingService $geocoding)
    {
        parent::__construct($wpdb);
        $this->geocoding = $geocoding;
    }

    protected function getTableName(): string
    {
        return 'bmn_schools';
    }

    /**
     * Find a school by NCES ID.
     */
    public function findByNcesId(string $ncesId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE nces_school_id = %s LIMIT 1",
                $ncesId
            )
        );

        return $result ?: null;
    }

    /**
     * Find schools by district, optionally filtered by level.
     *
     * @return object[]
     */
    public function findByDistrict(int $districtId, ?string $level = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE district_id = %d";
        $args = [$districtId];

        if ($level !== null) {
            $sql .= ' AND level = %s';
            $args[] = $level;
        }

        $sql .= ' ORDER BY name ASC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args)
        );
    }

    /**
     * Find schools by city, optionally filtered by level.
     *
     * @return object[]
     */
    public function findByCity(string $city, ?string $level = null): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE city = %s";
        $args = [$city];

        if ($level !== null) {
            $sql .= ' AND level = %s';
            $args[] = $level;
        }

        $sql .= ' ORDER BY name ASC';

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args)
        );
    }

    /**
     * Find schools near a geographic point.
     *
     * @return object[] Schools with a `distance` property added.
     */
    public function findNearby(
        float $lat,
        float $lng,
        float $radiusMiles = 2.0,
        ?string $level = null,
        int $limit = 30,
    ): array {
        $radiusCondition = $this->geocoding->buildRadiusCondition(
            $lat,
            $lng,
            $radiusMiles,
            'latitude',
            'longitude'
        );

        $haversine = sprintf(
            '(3959 * acos(cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude))))',
            $lat,
            $lng,
            $lat
        );

        $sql = "SELECT *, {$haversine} AS distance FROM {$this->table} WHERE {$radiusCondition}";
        $args = [];

        if ($level !== null) {
            $sql .= ' AND level = %s';
            $args[] = $level;
        }

        $sql .= " ORDER BY distance ASC LIMIT %d";
        $args[] = $limit;

        if ($args !== []) {
            $sql = $this->wpdb->prepare($sql, ...$args);
        }

        return $this->wpdb->get_results($sql);
    }

    /**
     * Autocomplete search for school names.
     *
     * @return object[]
     */
    public function autocomplete(string $term, int $limit = 10): array
    {
        // Escape LIKE wildcards in the search term.
        $escapedTerm = addcslashes($term, '_%\\');

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, name, level, city FROM {$this->table} WHERE name LIKE %s ORDER BY name ASC LIMIT %d",
                '%' . $escapedTerm . '%',
                $limit
            )
        );
    }

    /**
     * Find schools by an array of IDs.
     *
     * @param int[] $ids
     * @return object[]
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE id IN ({$placeholders})",
                ...$ids
            )
        );
    }

    /**
     * Find all schools within a bounding box (used by batch filter queries).
     *
     * @return object[]
     */
    public function findInBoundingBox(
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
        ?string $level = null,
    ): array {
        $sql = "SELECT * FROM {$this->table} WHERE latitude BETWEEN %f AND %f AND longitude BETWEEN %f AND %f";
        $args = [$minLat, $maxLat, $minLng, $maxLng];

        if ($level !== null) {
            $sql .= ' AND level = %s';
            $args[] = $level;
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare($sql, ...$args)
        );
    }
}
