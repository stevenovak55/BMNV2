<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Api;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Schools\Api\Controllers\SchoolController;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolRankingService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class SchoolControllerTest extends TestCase
{
    private SchoolRepository $schoolRepo;
    private SchoolDistrictRepository $districtRepo;
    private SchoolRankingRepository $rankingRepo;
    private SchoolDataRepository $dataRepo;
    private SchoolRankingService $rankingService;
    private GeocodingService $geocoding;
    private CacheService $cache;
    private SchoolController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];

        $this->schoolRepo = $this->createMock(SchoolRepository::class);
        $this->districtRepo = $this->createMock(SchoolDistrictRepository::class);
        $this->rankingRepo = $this->createMock(SchoolRankingRepository::class);
        $this->dataRepo = $this->createMock(SchoolDataRepository::class);
        $this->rankingService = $this->createMock(SchoolRankingService::class);
        $this->geocoding = $this->createMock(GeocodingService::class);
        $this->cache = $this->createMock(CacheService::class);

        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);

        $this->controller = new SchoolController(
            $this->schoolRepo,
            $this->districtRepo,
            $this->rankingRepo,
            $this->dataRepo,
            $this->rankingService,
            $this->geocoding,
            $this->cache,
        );
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public function testRegistersSchoolsRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/schools', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersSchoolDetailRoute(): void
    {
        $this->controller->registerRoutes();

        $found = false;
        foreach (array_keys($GLOBALS['wp_rest_routes']) as $route) {
            if (str_contains($route, 'schools') && str_contains($route, 'id')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'School detail route should be registered');
    }

    public function testRegistersNearbyRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/schools/nearby', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersTopRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/schools/top', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersDistrictsRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/districts', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersPropertySchoolsRoute(): void
    {
        $this->controller->registerRoutes();

        $found = false;
        foreach (array_keys($GLOBALS['wp_rest_routes']) as $route) {
            if (str_contains($route, 'properties') && str_contains($route, 'schools')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Property schools route should be registered');
    }

    public function testAllSchoolRoutesArePublic(): void
    {
        $this->controller->registerRoutes();

        foreach ($GLOBALS['wp_rest_routes'] as $route => $args) {
            $callback = $args['permission_callback'];
            $this->assertSame(
                '__return_true',
                $callback,
                "Route {$route} should be public"
            );
        }
    }

    // ------------------------------------------------------------------
    // GET /schools
    // ------------------------------------------------------------------

    public function testIndexReturnsSchoolList(): void
    {
        $this->schoolRepo->method('count')->willReturn(2);
        $this->schoolRepo->method('findBy')->willReturn([
            (object) ['id' => 1, 'name' => 'School A', 'level' => 'High', 'school_type' => 'public', 'city' => 'Winchester', 'district_id' => null],
            (object) ['id' => 2, 'name' => 'School B', 'level' => 'Elementary', 'school_type' => 'public', 'city' => 'Lexington', 'district_id' => null],
        ]);
        $this->rankingRepo->method('getRanking')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/schools');
        $response = $this->controller->index($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(2, $data['meta']['total']);
    }

    public function testIndexWithCityFilter(): void
    {
        $this->schoolRepo->method('count')->willReturn(1);
        $this->schoolRepo->method('findBy')->willReturn([
            (object) ['id' => 1, 'name' => 'School A', 'level' => 'High', 'school_type' => 'public', 'city' => 'Winchester', 'district_id' => null],
        ]);
        $this->rankingRepo->method('getRanking')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/schools');
        $request->set_param('city', 'Winchester');

        $response = $this->controller->index($request);
        $data = $response->get_data();
        $this->assertCount(1, $data['data']);
    }

    // ------------------------------------------------------------------
    // GET /schools/{id}
    // ------------------------------------------------------------------

    public function testShowReturnsSchoolDetail(): void
    {
        $this->schoolRepo->method('find')->willReturn((object) [
            'id' => 1, 'name' => 'Winchester High', 'level' => 'High',
            'school_type' => 'public', 'grades_low' => '9', 'grades_high' => '12',
            'address' => '80 Skillings Rd', 'city' => 'Winchester', 'state' => 'MA',
            'zip' => '01890', 'phone' => '781-721-7020', 'website' => 'https://whs.winchester.k12.ma.us',
            'district_id' => 5,
        ]);
        $this->districtRepo->method('find')->willReturn(
            (object) ['id' => 5, 'name' => 'Winchester']
        );
        $this->rankingRepo->method('getRanking')->willReturn((object) [
            'composite_score' => 87.5, 'letter_grade' => 'A', 'percentile_rank' => 82.0,
            'state_rank' => 15, 'category' => 'public_high',
            'mcas_score' => 82.3, 'graduation_score' => 96.1, 'growth_score' => 55.2,
            'attendance_score' => 91.0, 'ap_score' => 78.5, 'masscore_score' => 85.0,
            'ratio_score' => 72.0, 'spending_score' => 68.0,
        ]);
        $this->dataRepo->method('getDemographics')->willReturn((object) [
            'total_students' => 1200, 'avg_class_size' => 18.5, 'teacher_count' => 95,
        ]);
        $this->rankingService->method('getSchoolHighlights')->willReturn([
            'Strong AP Programs', 'High Graduation Rate',
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/schools/1');
        $request->set_param('id', '1');

        $response = $this->controller->show($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertSame('Winchester High', $data['data']['name']);
        $this->assertSame('9-12', $data['data']['grades']);
        $this->assertSame(87.5, $data['data']['ranking']['composite_score']);
        $this->assertSame('A', $data['data']['ranking']['letter_grade']);
        $this->assertSame(82.3, $data['data']['scores']['mcas']);
        $this->assertCount(2, $data['data']['highlights']);
        $this->assertSame(1200, $data['data']['demographics']['total_students']);
        $this->assertSame('Winchester', $data['data']['district']['name']);
    }

    public function testShowReturns404ForMissingSchool(): void
    {
        $this->schoolRepo->method('find')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/schools/999');
        $request->set_param('id', '999');

        $response = $this->controller->show($request);
        $this->assertSame(404, $response->get_status());
    }

    // ------------------------------------------------------------------
    // GET /schools/nearby
    // ------------------------------------------------------------------

    public function testNearbyReturnsSchoolsWithDistance(): void
    {
        $school = (object) [
            'id' => 1, 'name' => 'Nearby School', 'level' => 'High',
            'school_type' => 'public', 'city' => 'Winchester', 'district_id' => null,
            'distance' => 0.8,
        ];
        $this->schoolRepo->method('findNearby')->willReturn([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/schools/nearby');
        $request->set_param('lat', '42.36');
        $request->set_param('lng', '-71.06');

        $response = $this->controller->nearby($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(0.8, $data['data'][0]['distance']);
    }

    public function testNearbyReturns400WithoutCoordinates(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/schools/nearby');

        $response = $this->controller->nearby($request);
        $this->assertSame(400, $response->get_status());
    }

    // ------------------------------------------------------------------
    // GET /schools/top
    // ------------------------------------------------------------------

    public function testTopReturnsRankedSchools(): void
    {
        $this->rankingRepo->method('getTopSchools')->willReturn([
            (object) [
                'school_id' => 1, 'name' => 'Top School', 'level' => 'High',
                'city' => 'Winchester', 'school_type' => 'public',
                'composite_score' => 95.0, 'letter_grade' => 'A+',
                'percentile_rank' => 98.0, 'state_rank' => 1,
            ],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/schools/top');
        $response = $this->controller->top($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(95.0, $data['data'][0]['ranking']['composite_score']);
    }

    // ------------------------------------------------------------------
    // GET /districts
    // ------------------------------------------------------------------

    public function testDistrictIndexReturnsList(): void
    {
        $this->districtRepo->method('count')->willReturn(1);
        $this->districtRepo->method('findBy')->willReturn([
            (object) ['id' => 1, 'name' => 'Winchester', 'city' => 'Winchester', 'county' => 'Middlesex', 'total_schools' => 5, 'total_students' => 4000],
        ]);
        $this->rankingRepo->method('getDistrictRanking')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/districts');
        $response = $this->controller->districtIndex($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Winchester', $data['data'][0]['name']);
    }

    // ------------------------------------------------------------------
    // GET /districts/{id}
    // ------------------------------------------------------------------

    public function testDistrictShowReturnsDetail(): void
    {
        $this->districtRepo->method('find')->willReturn((object) [
            'id' => 1, 'name' => 'Winchester', 'type' => 'Regular',
            'city' => 'Winchester', 'county' => 'Middlesex',
            'website' => 'https://winchester.k12.ma.us', 'phone' => '781-721-7000',
            'total_schools' => 5, 'total_students' => 4000,
        ]);
        $this->rankingRepo->method('getDistrictRanking')->willReturn((object) [
            'composite_score' => 85.0, 'letter_grade' => 'A', 'percentile_rank' => 80.0,
            'state_rank' => 10, 'schools_count' => 5, 'schools_with_data' => 5,
            'elementary_avg' => 82.0, 'middle_avg' => 84.0, 'high_avg' => 88.0,
        ]);
        $this->schoolRepo->method('count')->willReturn(5);

        $request = new WP_REST_Request('GET', '/bmn/v1/districts/1');
        $request->set_param('id', '1');

        $response = $this->controller->districtShow($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertSame('Winchester', $data['data']['name']);
        $this->assertSame(85.0, $data['data']['ranking']['composite_score']);
        $this->assertSame(82.0, $data['data']['ranking']['elementary_avg']);
    }

    public function testDistrictShowReturns404ForMissing(): void
    {
        $this->districtRepo->method('find')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/districts/999');
        $request->set_param('id', '999');

        $response = $this->controller->districtShow($request);
        $this->assertSame(404, $response->get_status());
    }
}
