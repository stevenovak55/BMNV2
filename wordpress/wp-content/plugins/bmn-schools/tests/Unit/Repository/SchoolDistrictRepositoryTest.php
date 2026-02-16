<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Repository;

use BMN\Platform\Geocoding\GeocodingService;
use BMN\Schools\Repository\SchoolDistrictRepository;
use PHPUnit\Framework\TestCase;

final class SchoolDistrictRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private GeocodingService $geocoding;
    private SchoolDistrictRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->geocoding = $this->createMock(GeocodingService::class);
        $this->repo = new SchoolDistrictRepository($this->wpdb, $this->geocoding);
    }

    public function testGetTableNameUsesCorrectPrefix(): void
    {
        $district = (object) ['id' => 1, 'name' => 'Test District'];
        $this->wpdb->get_row_result = $district;

        $this->repo->find(1);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('wp_bmn_school_districts', $sql);
    }

    public function testFindByNcesIdReturnsDistrict(): void
    {
        $expected = (object) ['id' => 1, 'nces_district_id' => '2501234'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByNcesId('2501234');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('nces_district_id', $sql);
    }

    public function testFindByNcesIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByNcesId('nonexistent');

        $this->assertNull($result);
    }

    public function testFindByCityReturnsDistrict(): void
    {
        $expected = (object) ['id' => 1, 'city' => 'Winchester'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByCity('Winchester');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('city', $sql);
    }

    public function testFindByCityReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByCity('Nonexistent');

        $this->assertNull($result);
    }

    public function testFindForPointMatchesPolygon(): void
    {
        $geojson = json_encode([
            'type' => 'Polygon',
            'coordinates' => [
                [[-71.10, 42.30], [-71.00, 42.30], [-71.00, 42.40], [-71.10, 42.40], [-71.10, 42.30]],
            ],
        ]);

        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'name' => 'Test District', 'boundary_geojson' => $geojson],
        ];

        $this->geocoding->expects($this->once())
            ->method('isPointInPolygon')
            ->willReturn(true);

        $result = $this->repo->findForPoint(42.35, -71.05);

        $this->assertNotNull($result);
        $this->assertSame(1, $result->id);
    }

    public function testFindForPointReturnsNullWhenNoMatch(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findForPoint(42.35, -71.05);

        $this->assertNull($result);
    }

    public function testGetRegionalMappingReturnsDistrictName(): void
    {
        $result = $this->repo->getRegionalMapping('Nahant');

        $this->assertSame('Swampscott', $result);
    }

    public function testGetRegionalMappingReturnsNullForUnknownCity(): void
    {
        $result = $this->repo->getRegionalMapping('UnknownCity');

        $this->assertNull($result);
    }

    public function testFindByNameReturnsDistrict(): void
    {
        $expected = (object) ['id' => 1, 'name' => 'Winchester'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByName('Winchester');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('name', $sql);
    }

    public function testFindByCountyReturnsDistricts(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'county' => 'Middlesex'],
            (object) ['id' => 2, 'county' => 'Middlesex'],
        ];

        $result = $this->repo->findByCounty('Middlesex');

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('county', $sql);
    }
}
