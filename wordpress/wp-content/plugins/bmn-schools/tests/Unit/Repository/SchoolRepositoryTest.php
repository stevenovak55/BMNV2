<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Repository;

use BMN\Platform\Geocoding\GeocodingService;
use BMN\Schools\Repository\SchoolRepository;
use PHPUnit\Framework\TestCase;

final class SchoolRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private GeocodingService $geocoding;
    private SchoolRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->geocoding = $this->createMock(GeocodingService::class);
        $this->repo = new SchoolRepository($this->wpdb, $this->geocoding);
    }

    public function testGetTableNameUsesCorrectPrefix(): void
    {
        $school = (object) ['id' => 1, 'name' => 'Test School'];
        $this->wpdb->get_row_result = $school;

        $this->repo->find(1);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('wp_bmn_schools', $sql);
    }

    public function testFindByNcesIdReturnsSchool(): void
    {
        $expected = (object) ['id' => 1, 'nces_school_id' => '250001'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByNcesId('250001');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('nces_school_id', $sql);
    }

    public function testFindByNcesIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByNcesId('nonexistent');

        $this->assertNull($result);
    }

    public function testFindByDistrictQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'district_id' => 5],
            (object) ['id' => 2, 'district_id' => 5],
        ];

        $result = $this->repo->findByDistrict(5);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('district_id', $sql);
    }

    public function testFindByDistrictWithLevelFilter(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findByDistrict(5, 'High');

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('district_id', $sql);
        $this->assertStringContainsString('level', $sql);
    }

    public function testFindByCityQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'city' => 'Winchester'],
        ];

        $result = $this->repo->findByCity('Winchester');

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('city', $sql);
    }

    public function testFindByCityWithLevelFilter(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findByCity('Winchester', 'Elementary');

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('level', $sql);
    }

    public function testFindNearbyUsesGeocodingService(): void
    {
        $this->geocoding->expects($this->once())
            ->method('buildRadiusCondition')
            ->with(42.36, -71.06, 2.0, 'latitude', 'longitude')
            ->willReturn('1=1');

        $this->wpdb->get_results_result = [];

        $this->repo->findNearby(42.36, -71.06, 2.0);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('distance', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testFindNearbyWithLevelFilter(): void
    {
        $this->geocoding->method('buildRadiusCondition')->willReturn('1=1');
        $this->wpdb->get_results_result = [];

        $this->repo->findNearby(42.36, -71.06, 2.0, 'High', 10);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('level', $sql);
    }

    public function testAutocompleteSearchesNameWithLike(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'name' => 'Winchester High', 'level' => 'High', 'city' => 'Winchester'],
        ];

        $result = $this->repo->autocomplete('Winch', 5);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('Winch', $sql);
    }

    public function testFindByIdsReturnsSchools(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1],
            (object) ['id' => 2],
        ];

        $result = $this->repo->findByIds([1, 2]);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('IN', $sql);
    }

    public function testFindByIdsReturnsEmptyForEmptyInput(): void
    {
        $result = $this->repo->findByIds([]);

        $this->assertSame([], $result);
        $this->assertCount(0, $this->wpdb->queries);
    }

    public function testFindInBoundingBoxQueriesLatLng(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findInBoundingBox(42.30, 42.40, -71.10, -71.00);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('latitude BETWEEN', $sql);
        $this->assertStringContainsString('longitude BETWEEN', $sql);
    }
}
