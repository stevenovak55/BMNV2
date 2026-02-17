<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Geocoding;

use BMN\Platform\Geocoding\SpatialService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SpatialService.
 *
 * Tests Haversine distance calculation, point-in-polygon detection,
 * spatial SQL condition building, coordinate validation, polygon
 * utilities, and geocoding edge cases.
 */
class SpatialServiceTest extends TestCase
{
    private SpatialService $spatial;

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new \wpdb();

        $this->spatial = new SpatialService();
    }

    // ------------------------------------------------------------------
    // 1. Haversine distance: Boston to NYC
    // ------------------------------------------------------------------

    public function testHaversineDistanceBostonToNYC(): void
    {
        // Boston: 42.3601, -71.0589
        // NYC:    40.7128, -74.0060
        $distance = $this->spatial->haversineDistance(42.3601, -71.0589, 40.7128, -74.0060);

        // Expected ~190 miles, allow ±5 miles.
        $this->assertEqualsWithDelta(190.0, $distance, 5.0, 'Boston to NYC should be approximately 190 miles.');
    }

    // ------------------------------------------------------------------
    // 2. Haversine distance: same point is zero
    // ------------------------------------------------------------------

    public function testHaversineDistanceSamePointIsZero(): void
    {
        $distance = $this->spatial->haversineDistance(42.3601, -71.0589, 42.3601, -71.0589);

        $this->assertSame(0.0, $distance, 'Distance from a point to itself should be exactly zero.');
    }

    // ------------------------------------------------------------------
    // 3. isPointInPolygon: inside returns true
    // ------------------------------------------------------------------

    public function testIsPointInPolygonInsideReturnsTrue(): void
    {
        // Simple square polygon around Boston (42.3601, -71.0589).
        $polygon = [
            [42.40, -71.10],  // NW corner
            [42.40, -71.00],  // NE corner
            [42.30, -71.00],  // SE corner
            [42.30, -71.10],  // SW corner
        ];

        $this->assertTrue(
            $this->spatial->isPointInPolygon(42.3601, -71.0589, $polygon),
            'Boston should be inside the square polygon around it.'
        );
    }

    // ------------------------------------------------------------------
    // 4. isPointInPolygon: outside returns false
    // ------------------------------------------------------------------

    public function testIsPointInPolygonOutsideReturnsFalse(): void
    {
        // Same square polygon around Boston.
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
            [42.30, -71.00],
            [42.30, -71.10],
        ];

        // NYC is well outside this polygon.
        $this->assertFalse(
            $this->spatial->isPointInPolygon(40.7128, -74.0060, $polygon),
            'NYC should be outside the Boston polygon.'
        );
    }

    // ------------------------------------------------------------------
    // 5. isPointInPolygon: fewer than 3 points returns false
    // ------------------------------------------------------------------

    public function testIsPointInPolygonLessThan3PointsReturnsFalse(): void
    {
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
        ];

        $this->assertFalse(
            $this->spatial->isPointInPolygon(42.3601, -71.0589, $polygon),
            'A polygon with fewer than 3 points should always return false.'
        );
    }

    // ------------------------------------------------------------------
    // 6. buildRadiusCondition contains ACOS
    // ------------------------------------------------------------------

    public function testBuildRadiusConditionContainsAcos(): void
    {
        $condition = $this->spatial->buildRadiusCondition(42.3601, -71.0589, 10.0);

        $this->assertStringContainsString('ACOS', $condition, 'Radius condition should use the ACOS function.');
    }

    // ------------------------------------------------------------------
    // 7. buildBoundsCondition contains BETWEEN
    // ------------------------------------------------------------------

    public function testBuildBoundsConditionContainsBetween(): void
    {
        $condition = $this->spatial->buildBoundsCondition(42.40, 42.30, -71.00, -71.10);

        $this->assertStringContainsString('BETWEEN', $condition, 'Bounds condition should use BETWEEN.');
    }

    // ------------------------------------------------------------------
    // 8. buildPolygonCondition contains MOD
    // ------------------------------------------------------------------

    public function testBuildPolygonConditionContainsMod(): void
    {
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
            [42.30, -71.00],
            [42.30, -71.10],
        ];

        $condition = $this->spatial->buildPolygonCondition($polygon);

        $this->assertStringContainsString('MOD', $condition, 'Polygon condition should use MOD for ray-casting parity.');
    }

    // ------------------------------------------------------------------
    // 9. validateCoordinates: valid returns true
    // ------------------------------------------------------------------

    public function testValidateCoordinatesValidReturnsTrue(): void
    {
        $this->assertTrue(
            $this->spatial->validateCoordinates(42.3601, -71.0589),
            'Boston coordinates should be valid.'
        );
    }

    // ------------------------------------------------------------------
    // 10. validateCoordinates: invalid latitude returns false
    // ------------------------------------------------------------------

    public function testValidateCoordinatesInvalidLatReturnsFalse(): void
    {
        $this->assertFalse(
            $this->spatial->validateCoordinates(91.0, -71.0589),
            'Latitude 91 is out of range and should be invalid.'
        );
    }

    // ------------------------------------------------------------------
    // 11. validateCoordinates: invalid longitude returns false
    // ------------------------------------------------------------------

    public function testValidateCoordinatesInvalidLngReturnsFalse(): void
    {
        $this->assertFalse(
            $this->spatial->validateCoordinates(42.3601, 181.0),
            'Longitude 181 is out of range and should be invalid.'
        );
    }

    // ------------------------------------------------------------------
    // 12. validatePolygon: valid polygon
    // ------------------------------------------------------------------

    public function testValidatePolygonWithValidPolygon(): void
    {
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
            [42.30, -71.00],
        ];

        $this->assertTrue(
            $this->spatial->validatePolygon($polygon),
            'A polygon with 3 valid coordinate pairs should be valid.'
        );
    }

    // ------------------------------------------------------------------
    // 13. validatePolygon: too few points
    // ------------------------------------------------------------------

    public function testValidatePolygonWithTooFewPoints(): void
    {
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
        ];

        $this->assertFalse(
            $this->spatial->validatePolygon($polygon),
            'A polygon with fewer than 3 points should be invalid.'
        );
    }

    // ------------------------------------------------------------------
    // 14. closePolygon: appends first point when not closed
    // ------------------------------------------------------------------

    public function testClosePolygonAppendsFirstPoint(): void
    {
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
            [42.30, -71.00],
        ];

        $closed = $this->spatial->closePolygon($polygon);

        $this->assertCount(4, $closed, 'Closing an open polygon should add one point.');
        $this->assertSame($closed[0], $closed[3], 'The last point should match the first point.');
    }

    // ------------------------------------------------------------------
    // 15. closePolygon: already closed polygon unchanged
    // ------------------------------------------------------------------

    public function testClosePolygonAlreadyClosedNoChange(): void
    {
        $polygon = [
            [42.40, -71.10],
            [42.40, -71.00],
            [42.30, -71.00],
            [42.40, -71.10],  // Already matches first point.
        ];

        $closed = $this->spatial->closePolygon($polygon);

        $this->assertCount(4, $closed, 'An already-closed polygon should not gain an extra point.');
    }

    // ------------------------------------------------------------------
    // 18. buildSpatialBoundsCondition: uses MBRContains
    // ------------------------------------------------------------------

    public function testBuildSpatialBoundsConditionContainsMBRContains(): void
    {
        $condition = $this->spatial->buildSpatialBoundsCondition(42.40, 42.30, -71.00, -71.10);

        $this->assertStringContainsString('MBRContains', $condition, 'Spatial bounds should use MBRContains.');
        $this->assertStringContainsString('ST_GeomFromText', $condition, 'Spatial bounds should use ST_GeomFromText.');
        $this->assertStringContainsString('POLYGON', $condition, 'Spatial bounds should create a POLYGON.');
        $this->assertStringContainsString('coordinates', $condition, 'Spatial bounds should target coordinates column.');
    }

    // ------------------------------------------------------------------
    // 19. buildSpatialBoundsCondition: custom column name
    // ------------------------------------------------------------------

    public function testBuildSpatialBoundsConditionCustomColumn(): void
    {
        $condition = $this->spatial->buildSpatialBoundsCondition(42.40, 42.30, -71.00, -71.10, 'geo_point');

        $this->assertStringContainsString('geo_point', $condition);
    }

    // ------------------------------------------------------------------
    // 20. buildSpatialRadiusCondition: uses ST_Distance_Sphere
    // ------------------------------------------------------------------

    public function testBuildSpatialRadiusConditionContainsDistanceSphere(): void
    {
        $condition = $this->spatial->buildSpatialRadiusCondition(42.3601, -71.0589, 10.0);

        $this->assertStringContainsString('ST_Distance_Sphere', $condition, 'Spatial radius should use ST_Distance_Sphere.');
        $this->assertStringContainsString('coordinates', $condition, 'Spatial radius should target coordinates column.');
    }

    // ------------------------------------------------------------------
    // 21. buildSpatialRadiusCondition: converts miles to meters
    // ------------------------------------------------------------------

    public function testBuildSpatialRadiusConditionConvertsToMeters(): void
    {
        $condition = $this->spatial->buildSpatialRadiusCondition(42.3601, -71.0589, 1.0);

        // 1 mile = 1609.344 meters
        $this->assertStringContainsString('1609.344', $condition, 'Should convert 1 mile to 1609.344 meters.');
    }

    // ------------------------------------------------------------------
    // 16. geocodeAddress: empty string returns null
    // ------------------------------------------------------------------

    public function testGeocodeAddressReturnsNullForEmptyString(): void
    {
        $result = $this->spatial->geocodeAddress('');

        $this->assertNull($result, 'Geocoding an empty string should return null.');
    }

    // ------------------------------------------------------------------
    // 17. geocodeAddress: returns null without API key
    // ------------------------------------------------------------------

    public function testGeocodeAddressReturnsNullWithoutApiKey(): void
    {
        // Ensure no API key is available (constant not defined, option not set).
        unset($GLOBALS['wp_options']['bmn_google_geocoding_key']);

        $result = $this->spatial->geocodeAddress('123 Main St, Boston, MA');

        $this->assertNull($result, 'Geocoding should return null when no API key is configured.');
    }
}
