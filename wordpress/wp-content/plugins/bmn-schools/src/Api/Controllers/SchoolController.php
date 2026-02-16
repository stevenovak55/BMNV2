<?php

declare(strict_types=1);

namespace BMN\Schools\Api\Controllers;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolRankingService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for school endpoints.
 *
 * 7 routes: schools list, school detail, nearby schools, top schools,
 * property schools, districts list, district detail.
 */
final class SchoolController extends RestController
{
    protected string $resource = 'schools';

    private readonly SchoolRepository $schoolRepo;
    private readonly SchoolDistrictRepository $districtRepo;
    private readonly SchoolRankingRepository $rankingRepo;
    private readonly SchoolDataRepository $dataRepo;
    private readonly SchoolRankingService $rankingService;
    private readonly GeocodingService $geocoding;
    private readonly CacheService $cache;

    public function __construct(
        SchoolRepository $schoolRepo,
        SchoolDistrictRepository $districtRepo,
        SchoolRankingRepository $rankingRepo,
        SchoolDataRepository $dataRepo,
        SchoolRankingService $rankingService,
        GeocodingService $geocoding,
        CacheService $cache,
    ) {
        parent::__construct(null);
        $this->schoolRepo = $schoolRepo;
        $this->districtRepo = $districtRepo;
        $this->rankingRepo = $rankingRepo;
        $this->dataRepo = $dataRepo;
        $this->rankingService = $rankingService;
        $this->geocoding = $geocoding;
        $this->cache = $cache;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'index',
                'auth'     => false,
            ],
            [
                'path'     => '/nearby',
                'method'   => 'GET',
                'callback' => 'nearby',
                'auth'     => false,
            ],
            [
                'path'     => '/top',
                'method'   => 'GET',
                'callback' => 'top',
                'auth'     => false,
            ],
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'GET',
                'callback' => 'show',
                'auth'     => false,
            ],
        ];
    }

    /**
     * Register routes including non-schools-resource routes.
     */
    public function registerRoutes(): void
    {
        // Standard schools routes.
        parent::registerRoutes();

        // Property schools (different resource path).
        register_rest_route($this->namespace, 'properties/(?P<listing_id>[a-zA-Z0-9]+)/schools', [
            'methods'             => 'GET',
            'callback'            => [$this, 'propertySchools'],
            'permission_callback' => '__return_true',
        ]);

        // Districts.
        register_rest_route($this->namespace, 'districts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'districtIndex'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, 'districts/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'districtShow'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ------------------------------------------------------------------
    // Schools endpoints
    // ------------------------------------------------------------------

    /**
     * GET /bmn/v1/schools — List schools (filterable).
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));
        $city = $request->get_param('city');
        $level = $request->get_param('level');
        $type = $request->get_param('type');
        $districtId = $request->get_param('district_id');

        $criteria = [];
        if ($city !== null) {
            $criteria['city'] = $city;
        }
        if ($level !== null) {
            $criteria['level'] = $level;
        }
        if ($type !== null) {
            $criteria['school_type'] = $type;
        }
        if ($districtId !== null) {
            $criteria['district_id'] = (int) $districtId;
        }

        $total = $this->schoolRepo->count($criteria);
        $offset = ($page - 1) * $perPage;
        $schools = $this->schoolRepo->findBy($criteria, $perPage, $offset, 'name', 'ASC');

        $year = $this->rankingRepo->getLatestDataYear();
        $data = array_map(fn (object $school) => $this->formatSchoolListItem($school, $year), $schools);

        return ApiResponse::paginated($data, $total, $page, $perPage);
    }

    /**
     * GET /bmn/v1/schools/{id} — School detail.
     */
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $school = $this->schoolRepo->find($id);

        if ($school === null) {
            return ApiResponse::error('School not found.', 404);
        }

        $year = $this->rankingRepo->getLatestDataYear();
        $ranking = $this->rankingRepo->getRanking($id, $year);
        $demographics = $this->dataRepo->getDemographics($id, $year);
        $highlights = $this->rankingService->getSchoolHighlights($id, $year);

        // District info.
        $district = null;
        if ($school->district_id) {
            $districtObj = $this->districtRepo->find((int) $school->district_id);
            if ($districtObj) {
                $district = [
                    'id' => (int) $districtObj->id,
                    'name' => $districtObj->name,
                ];
            }
        }

        $grades = trim(($school->grades_low ?? '') . '-' . ($school->grades_high ?? ''), '-');

        $data = [
            'id' => (int) $school->id,
            'name' => $school->name,
            'level' => $school->level,
            'type' => $school->school_type,
            'grades' => $grades ?: null,
            'address' => $school->address,
            'city' => $school->city,
            'state' => $school->state,
            'zip' => $school->zip,
            'phone' => $school->phone,
            'website' => $school->website,
            'district' => $district,
            'ranking' => $ranking ? [
                'composite_score' => (float) $ranking->composite_score,
                'letter_grade' => $ranking->letter_grade,
                'percentile_rank' => (float) $ranking->percentile_rank,
                'state_rank' => (int) $ranking->state_rank,
                'category' => $ranking->category,
            ] : null,
            'scores' => $ranking ? [
                'mcas' => $ranking->mcas_score !== null ? (float) $ranking->mcas_score : null,
                'graduation' => $ranking->graduation_score !== null ? (float) $ranking->graduation_score : null,
                'growth' => $ranking->growth_score !== null ? (float) $ranking->growth_score : null,
                'attendance' => $ranking->attendance_score !== null ? (float) $ranking->attendance_score : null,
                'ap' => $ranking->ap_score !== null ? (float) $ranking->ap_score : null,
                'masscore' => $ranking->masscore_score !== null ? (float) $ranking->masscore_score : null,
                'ratio' => $ranking->ratio_score !== null ? (float) $ranking->ratio_score : null,
                'spending' => $ranking->spending_score !== null ? (float) $ranking->spending_score : null,
            ] : null,
            'highlights' => $highlights,
            'demographics' => $demographics ? [
                'total_students' => (int) $demographics->total_students,
                'avg_class_size' => $demographics->avg_class_size !== null ? (float) $demographics->avg_class_size : null,
                'teacher_count' => $demographics->teacher_count !== null ? (int) $demographics->teacher_count : null,
            ] : null,
        ];

        return ApiResponse::success($data);
    }

    /**
     * GET /bmn/v1/schools/nearby — Nearby schools.
     */
    public function nearby(WP_REST_Request $request): WP_REST_Response
    {
        $lat = $request->get_param('lat');
        $lng = $request->get_param('lng');

        if ($lat === null || $lng === null) {
            return ApiResponse::error('Parameters lat and lng are required.', 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = (float) ($request->get_param('radius') ?? 2.0);
        $level = $request->get_param('level');
        $limit = min(50, max(1, (int) ($request->get_param('limit') ?? 20)));

        $schools = $this->schoolRepo->findNearby($lat, $lng, $radius, $level, $limit);
        $year = $this->rankingRepo->getLatestDataYear();

        $data = array_map(function (object $school) use ($year): array {
            $item = $this->formatSchoolListItem($school, $year);
            $item['distance'] = isset($school->distance) ? round((float) $school->distance, 2) : null;
            return $item;
        }, $schools);

        return ApiResponse::success($data);
    }

    /**
     * GET /bmn/v1/schools/top — Top-ranked schools.
     */
    public function top(WP_REST_Request $request): WP_REST_Response
    {
        $limit = min(50, max(1, (int) ($request->get_param('limit') ?? 10)));
        $category = $request->get_param('category');
        $year = $request->get_param('year') ? (int) $request->get_param('year') : null;

        $schools = $this->rankingRepo->getTopSchools($limit, $category, $year);

        $data = array_map(static fn (object $school): array => [
            'id' => (int) $school->school_id,
            'name' => $school->name ?? null,
            'level' => $school->level ?? null,
            'city' => $school->city ?? null,
            'type' => $school->school_type ?? null,
            'ranking' => [
                'composite_score' => (float) $school->composite_score,
                'letter_grade' => $school->letter_grade,
                'percentile_rank' => (float) $school->percentile_rank,
                'state_rank' => (int) $school->state_rank,
            ],
        ], $schools);

        return ApiResponse::success($data);
    }

    /**
     * GET /bmn/v1/properties/{listing_id}/schools — Schools for a property.
     */
    public function propertySchools(WP_REST_Request $request): WP_REST_Response
    {
        $listingId = $request->get_param('listing_id');

        if ($listingId === null || $listingId === '') {
            return ApiResponse::error('Listing ID is required.', 400);
        }

        // Look up property coordinates.
        // Use apply_filters to let the properties plugin provide coordinates.
        $property = apply_filters('bmn_get_property', null, $listingId);

        if ($property === null || ! isset($property->latitude, $property->longitude)) {
            return ApiResponse::error('Property not found or missing coordinates.', 404);
        }

        $lat = (float) $property->latitude;
        $lng = (float) $property->longitude;
        $schools = $this->schoolRepo->findNearby($lat, $lng, 2.0, null, 30);
        $year = $this->rankingRepo->getLatestDataYear();

        // Group by level.
        $grouped = ['elementary' => [], 'middle' => [], 'high' => []];

        foreach ($schools as $school) {
            $ranking = $this->rankingRepo->getRanking((int) $school->id, $year);
            $highlights = $this->rankingService->getSchoolHighlights((int) $school->id, $year);

            $item = [
                'id' => (int) $school->id,
                'name' => $school->name,
                'distance' => isset($school->distance) ? round((float) $school->distance, 2) : null,
                'ranking' => $ranking ? [
                    'composite_score' => (float) $ranking->composite_score,
                    'letter_grade' => $ranking->letter_grade,
                    'percentile_rank' => (float) $ranking->percentile_rank,
                    'state_rank' => (int) $ranking->state_rank,
                ] : null,
                'highlights' => $highlights,
            ];

            $levelKey = strtolower($school->level);
            if (isset($grouped[$levelKey])) {
                $grouped[$levelKey][] = $item;
            }
        }

        return ApiResponse::success($grouped);
    }

    // ------------------------------------------------------------------
    // District endpoints
    // ------------------------------------------------------------------

    /**
     * GET /bmn/v1/districts — List districts.
     */
    public function districtIndex(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 25)));
        $city = $request->get_param('city');
        $county = $request->get_param('county');

        $criteria = [];
        if ($city !== null) {
            $criteria['city'] = $city;
        }
        if ($county !== null) {
            $criteria['county'] = $county;
        }

        $total = $this->districtRepo->count($criteria);
        $offset = ($page - 1) * $perPage;
        $districts = $this->districtRepo->findBy($criteria, $perPage, $offset, 'name', 'ASC');

        $year = $this->rankingRepo->getLatestDataYear();

        $data = array_map(function (object $district) use ($year): array {
            $ranking = $this->rankingRepo->getDistrictRanking((int) $district->id, $year);

            return [
                'id' => (int) $district->id,
                'name' => $district->name,
                'city' => $district->city,
                'county' => $district->county,
                'total_schools' => (int) $district->total_schools,
                'total_students' => (int) $district->total_students,
                'ranking' => $ranking ? [
                    'composite_score' => (float) $ranking->composite_score,
                    'letter_grade' => $ranking->letter_grade ?? null,
                    'percentile_rank' => $ranking->percentile_rank !== null ? (float) $ranking->percentile_rank : null,
                ] : null,
            ];
        }, $districts);

        return ApiResponse::paginated($data, $total, $page, $perPage);
    }

    /**
     * GET /bmn/v1/districts/{id} — District detail.
     */
    public function districtShow(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $district = $this->districtRepo->find($id);

        if ($district === null) {
            return ApiResponse::error('District not found.', 404);
        }

        $year = $this->rankingRepo->getLatestDataYear();
        $ranking = $this->rankingRepo->getDistrictRanking($id, $year);
        $schoolCount = $this->schoolRepo->count(['district_id' => $id]);

        $data = [
            'id' => (int) $district->id,
            'name' => $district->name,
            'type' => $district->type,
            'city' => $district->city,
            'county' => $district->county,
            'website' => $district->website,
            'phone' => $district->phone,
            'total_schools' => (int) $district->total_schools,
            'total_students' => (int) $district->total_students,
            'ranking' => $ranking ? [
                'composite_score' => (float) $ranking->composite_score,
                'letter_grade' => $ranking->letter_grade ?? null,
                'percentile_rank' => $ranking->percentile_rank !== null ? (float) $ranking->percentile_rank : null,
                'state_rank' => $ranking->state_rank !== null ? (int) $ranking->state_rank : null,
                'schools_count' => (int) $ranking->schools_count,
                'schools_with_data' => (int) $ranking->schools_with_data,
                'elementary_avg' => $ranking->elementary_avg !== null ? (float) $ranking->elementary_avg : null,
                'middle_avg' => $ranking->middle_avg !== null ? (float) $ranking->middle_avg : null,
                'high_avg' => $ranking->high_avg !== null ? (float) $ranking->high_avg : null,
            ] : null,
            'school_count' => $schoolCount,
        ];

        return ApiResponse::success($data);
    }

    // ------------------------------------------------------------------
    // Formatting
    // ------------------------------------------------------------------

    private function formatSchoolListItem(object $school, int $year): array
    {
        $ranking = $this->rankingRepo->getRanking((int) $school->id, $year);

        $districtName = null;
        if (isset($school->district_id) && $school->district_id) {
            $district = $this->districtRepo->find((int) $school->district_id);
            $districtName = $district ? $district->name : null;
        }

        return [
            'id' => (int) $school->id,
            'name' => $school->name,
            'level' => $school->level,
            'type' => $school->school_type,
            'city' => $school->city,
            'district' => $districtName,
            'ranking' => $ranking ? [
                'composite_score' => (float) $ranking->composite_score,
                'letter_grade' => $ranking->letter_grade,
                'percentile_rank' => (float) $ranking->percentile_rank,
                'state_rank' => (int) $ranking->state_rank,
            ] : null,
        ];
    }
}
