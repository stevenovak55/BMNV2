<?php

declare(strict_types=1);

namespace BMN\Platform\Geocoding;

use BMN\Platform\Cache\CacheService;

/**
 * Concrete implementation of GeocodingService.
 *
 * Provides Haversine distance calculation, point-in-polygon testing,
 * spatial SQL condition building for WordPress/MySQL, and address geocoding
 * via the Google Geocoding API.
 */
class SpatialService implements GeocodingService
{
    /**
     * Mean radius of the Earth in miles.
     */
    public const EARTH_RADIUS_MILES = 3959.0;

    /**
     * Mean radius of the Earth in kilometers.
     */
    public const EARTH_RADIUS_KM = 6371.0;

    /**
     * Cache TTL for geocoding results: 30 days in seconds.
     */
    private const GEOCODE_CACHE_TTL = 2592000;

    /**
     * Cache group for geocoding results.
     */
    private const GEOCODE_CACHE_GROUP = 'geography';

    /**
     * Optional cache service for geocoding results.
     */
    private ?CacheService $cache;

    /**
     * @param CacheService|null $cache Optional cache for geocoding result storage.
     */
    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * {@inheritDoc}
     */
    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lng1Rad = deg2rad($lng1);
        $lat2Rad = deg2rad($lat2);
        $lng2Rad = deg2rad($lng2);

        $dLat = $lat2Rad - $lat1Rad;
        $dLng = $lng2Rad - $lng1Rad;

        $a = sin($dLat / 2) ** 2
           + cos($lat1Rad) * cos($lat2Rad) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_MILES * asin(sqrt($a));
    }

    /**
     * {@inheritDoc}
     *
     * Uses the ray-casting algorithm: cast a horizontal ray from the test point
     * to infinity and count how many polygon edges it crosses. An odd count
     * means the point is inside the polygon.
     */
    public function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $count = count($polygon);

        if ($count < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = (float) $polygon[$i][0];
            $yi = (float) $polygon[$i][1];
            $xj = (float) $polygon[$j][0];
            $yj = (float) $polygon[$j][1];

            $intersect = (($yi > $lng) !== ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * {@inheritDoc}
     */
    public function buildRadiusCondition(
        float $lat,
        float $lng,
        float $radiusMiles,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string {
        global $wpdb;

        $latCol = $this->sanitizeColumnName($latColumn);
        $lngCol = $this->sanitizeColumnName($lngColumn);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->prepare(
            "(3959 * ACOS(COS(RADIANS(%f)) * COS(RADIANS({$latCol})) * COS(RADIANS({$lngCol}) - RADIANS(%f)) + SIN(RADIANS(%f)) * SIN(RADIANS({$latCol})))) <= %f",
            $lat,
            $lng,
            $lat,
            $radiusMiles
        );
    }

    /**
     * {@inheritDoc}
     */
    public function buildBoundsCondition(
        float $north,
        float $south,
        float $east,
        float $west,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string {
        global $wpdb;

        $latCol = $this->sanitizeColumnName($latColumn);
        $lngCol = $this->sanitizeColumnName($lngColumn);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->prepare(
            "({$latCol} BETWEEN %f AND %f AND {$lngCol} BETWEEN %f AND %f)",
            $south,
            $north,
            $west,
            $east
        );
    }

    /**
     * {@inheritDoc}
     *
     * Translates the ray-casting algorithm into a MySQL expression.
     * For each edge of the polygon, we build a sub-expression that tests
     * whether a horizontal ray from the point crosses that edge.
     * The total crossing count modulo 2 determines inside/outside.
     */
    public function buildPolygonCondition(
        array $polygon,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string {
        global $wpdb;

        $latCol = $this->sanitizeColumnName($latColumn);
        $lngCol = $this->sanitizeColumnName($lngColumn);

        $polygon = $this->closePolygon($polygon);
        $count   = count($polygon);
        $edges   = [];

        for ($i = 0; $i < $count - 1; $i++) {
            $lat1 = (float) $polygon[$i][0];
            $lng1 = (float) $polygon[$i][1];
            $lat2 = (float) $polygon[$i + 1][0];
            $lng2 = (float) $polygon[$i + 1][1];

            // Each edge contributes 1 if the ray crosses it, 0 otherwise.
            // The condition mirrors the ray-casting logic:
            //   1. The point's longitude is between the two edge endpoints' longitudes.
            //   2. The point's latitude is to the left of the edge at that longitude.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $edges[] = $wpdb->prepare(
                "CASE WHEN ((%f > {$lngCol}) != (%f > {$lngCol})) AND ({$latCol} < (%f - %f) * ({$lngCol} - %f) / (%f - %f) + %f) THEN 1 ELSE 0 END",
                $lng1,
                $lng2,
                $lat2,
                $lat1,
                $lng1,
                $lng2,
                $lng1,
                $lat1
            );
        }

        $crossingSum = implode(' + ', $edges);

        return "(MOD({$crossingSum}, 2) = 1)";
    }

    /**
     * {@inheritDoc}
     */
    public function geocodeAddress(string $address): ?array
    {
        $address = trim($address);

        if ($address === '') {
            return null;
        }

        $cacheKey = 'geocode_' . md5($address);

        // Check cache first.
        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey, self::GEOCODE_CACHE_GROUP);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Resolve API key.
        $apiKey = defined('BMN_GOOGLE_GEOCODING_KEY')
            ? BMN_GOOGLE_GEOCODING_KEY
            : get_option('bmn_google_geocoding_key', '');

        if (empty($apiKey)) {
            return null;
        }

        $url = add_query_arg(
            [
                'address' => $address,
                'key'     => $apiKey,
            ],
            'https://maps.googleapis.com/maps/api/geocode/json'
        );

        $response = wp_remote_get($url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (
            !is_array($body)
            || ($body['status'] ?? '') !== 'OK'
            || empty($body['results'][0])
        ) {
            return null;
        }

        $result   = $body['results'][0];
        $location = $result['geometry']['location'] ?? null;

        if ($location === null) {
            return null;
        }

        $geocoded = [
            'lat'               => (float) $location['lat'],
            'lng'               => (float) $location['lng'],
            'formatted_address' => (string) ($result['formatted_address'] ?? $address),
        ];

        // Cache the successful result.
        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $geocoded, self::GEOCODE_CACHE_TTL, self::GEOCODE_CACHE_GROUP);
        }

        return $geocoded;
    }

    /**
     * {@inheritDoc}
     */
    public function validateCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90.0 && $lat <= 90.0
            && $lng >= -180.0 && $lng <= 180.0;
    }

    /**
     * Validate that a polygon is well-formed.
     *
     * A valid polygon has at least 3 points, each point is a [lat, lng] array
     * with numeric values, and all coordinates pass validation.
     *
     * @param array $polygon Array of [lat, lng] pairs.
     * @return bool True if the polygon is valid.
     */
    public function validatePolygon(array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        foreach ($polygon as $point) {
            if (!is_array($point) || count($point) < 2) {
                return false;
            }

            if (!is_numeric($point[0]) || !is_numeric($point[1])) {
                return false;
            }

            if (!$this->validateCoordinates((float) $point[0], (float) $point[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Close a polygon by appending the first point to the end if needed.
     *
     * @param array $polygon Array of [lat, lng] pairs.
     * @return array The closed polygon.
     */
    public function closePolygon(array $polygon): array
    {
        if (empty($polygon)) {
            return $polygon;
        }

        $first = $polygon[0];
        $last  = $polygon[count($polygon) - 1];

        if ((float) $first[0] !== (float) $last[0] || (float) $first[1] !== (float) $last[1]) {
            $polygon[] = $first;
        }

        return $polygon;
    }

    /**
     * Sanitize a SQL column name to prevent injection.
     *
     * Only alphanumeric characters, underscores, and dots are permitted.
     * Everything else is stripped.
     *
     * @param string $column The column name to sanitize.
     * @return string The sanitized column name.
     */
    private function sanitizeColumnName(string $column): string
    {
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
    }
}
