<?php

declare(strict_types=1);

namespace BMN\Platform\Geocoding;

/**
 * Geocoding service abstraction.
 *
 * Provides coordinate math, spatial SQL condition building,
 * and address-to-coordinate geocoding.
 */
interface GeocodingService
{
    /**
     * Calculate the distance in miles between two points using the Haversine formula.
     *
     * @param float $lat1 Latitude of the first point.
     * @param float $lng1 Longitude of the first point.
     * @param float $lat2 Latitude of the second point.
     * @param float $lng2 Longitude of the second point.
     * @return float Distance in miles.
     */
    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float;

    /**
     * Determine whether a point lies inside a polygon using ray casting.
     *
     * @param float $lat     Latitude of the point.
     * @param float $lng     Longitude of the point.
     * @param array $polygon Array of [lat, lng] pairs defining the polygon.
     * @return bool True if the point is inside the polygon.
     */
    public function isPointInPolygon(float $lat, float $lng, array $polygon): bool;

    /**
     * Build a SQL WHERE condition that filters rows within a radius of a point.
     *
     * @param float  $lat         Center latitude.
     * @param float  $lng         Center longitude.
     * @param float  $radiusMiles Radius in miles.
     * @param string $latColumn   Column name for latitude.
     * @param string $lngColumn   Column name for longitude.
     * @return string Prepared SQL condition.
     */
    public function buildRadiusCondition(
        float $lat,
        float $lng,
        float $radiusMiles,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string;

    /**
     * Build a SQL WHERE condition that filters rows within a bounding box.
     *
     * @param float  $north     Northern boundary latitude.
     * @param float  $south     Southern boundary latitude.
     * @param float  $east      Eastern boundary longitude.
     * @param float  $west      Western boundary longitude.
     * @param string $latColumn Column name for latitude.
     * @param string $lngColumn Column name for longitude.
     * @return string Prepared SQL condition.
     */
    public function buildBoundsCondition(
        float $north,
        float $south,
        float $east,
        float $west,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string;

    /**
     * Build a SQL WHERE condition that filters rows inside a polygon.
     *
     * @param array  $polygon   Array of [lat, lng] pairs defining the polygon.
     * @param string $latColumn Column name for latitude.
     * @param string $lngColumn Column name for longitude.
     * @return string Prepared SQL condition.
     */
    public function buildPolygonCondition(
        array $polygon,
        string $latColumn = 'latitude',
        string $lngColumn = 'longitude'
    ): string;

    /**
     * Geocode a street address to coordinates via Google Geocoding API.
     *
     * @param string $address The address to geocode.
     * @return array{lat: float, lng: float, formatted_address: string}|null Coordinates or null on failure.
     */
    public function geocodeAddress(string $address): ?array;

    /**
     * Validate that latitude and longitude are within valid ranges.
     *
     * @param float $lat Latitude to validate.
     * @param float $lng Longitude to validate.
     * @return bool True if coordinates are valid.
     */
    public function validateCoordinates(float $lat, float $lng): bool;
}
