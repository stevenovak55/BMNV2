<?php

declare(strict_types=1);

namespace BMN\Flip\Controller;

use BMN\Flip\Service\ArvService;
use BMN\Flip\Service\FlipAnalysisService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class FlipController extends RestController
{
    protected string $resource = 'flip';

    private readonly FlipAnalysisService $analysisService;
    private readonly ArvService $arvService;

    public function __construct(
        FlipAnalysisService $analysisService,
        ArvService $arvService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->analysisService = $analysisService;
        $this->arvService = $arvService;
    }

    protected function getRoutes(): array
    {
        return [
            // 1. POST /flip/analyze — Analyze a single property.
            [
                'path'     => '/analyze',
                'method'   => 'POST',
                'callback' => 'analyzeProperty',
                'auth'     => true,
            ],
            // 2. GET /flip/results — List analysis results for a report.
            [
                'path'     => '/results',
                'method'   => 'GET',
                'callback' => 'listResults',
                'auth'     => true,
            ],
            // 3. GET /flip/results/{id} — Get single analysis result.
            [
                'path'     => '/results/(?P<id>\d+)',
                'method'   => 'GET',
                'callback' => 'getResult',
                'auth'     => true,
            ],
            // 4. GET /flip/results/{id}/comps — Get comparables for an analysis.
            [
                'path'     => '/results/(?P<id>\d+)/comps',
                'method'   => 'GET',
                'callback' => 'getComps',
                'auth'     => true,
            ],
            // 5. GET /flip/summary — Per-city summary stats.
            [
                'path'     => '/summary',
                'method'   => 'GET',
                'callback' => 'getSummary',
                'auth'     => true,
            ],
            // 6. POST /flip/arv — Calculate ARV only (without full analysis).
            [
                'path'     => '/arv',
                'method'   => 'POST',
                'callback' => 'calculateArv',
                'auth'     => true,
            ],
            // 7. GET /flip/config/cities — Get target cities.
            [
                'path'     => '/config/cities',
                'method'   => 'GET',
                'callback' => 'getCities',
                'auth'     => true,
            ],
            // 8. POST /flip/config/cities — Update target cities (admin).
            [
                'path'     => '/config/cities',
                'method'   => 'POST',
                'callback' => 'setCities',
                'auth'     => true,
            ],
            // 9. GET /flip/config/weights — Get scoring weights.
            [
                'path'     => '/config/weights',
                'method'   => 'GET',
                'callback' => 'getWeights',
                'auth'     => true,
            ],
        ];
    }

    /**
     * POST /flip/analyze — Analyze a single property.
     */
    public function analyzeProperty(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $propertyData = [];
        $propertyFields = [
            'listing_id', 'address', 'city', 'state', 'zip',
            'list_price', 'property_type', 'bedrooms_total', 'bathrooms_total',
            'living_area', 'lot_size_acres', 'year_built', 'garage_spaces',
            'latitude', 'longitude', 'days_on_market', 'original_list_price',
        ];

        foreach ($propertyFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $propertyData[$field] = $value;
            }
        }

        if (empty($propertyData['listing_id']) || empty($propertyData['list_price'])) {
            return ApiResponse::error('listing_id and list_price are required.', 422);
        }

        if (empty($propertyData['latitude']) || empty($propertyData['longitude'])) {
            return ApiResponse::error('Latitude and longitude are required.', 422);
        }

        $reportId = $request->get_param('report_id');

        try {
            $result = $this->analysisService->analyzeProperty(
                $propertyData,
                $reportId !== null ? (int) $reportId : null,
            );

            return ApiResponse::success($result);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /flip/results — List analysis results for a report.
     */
    public function listResults(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = $request->get_param('report_id');

        if (empty($reportId)) {
            return ApiResponse::error('report_id query parameter is required.', 422);
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 50)));

        $result = $this->analysisService->getAnalysesByReport((int) $reportId, $page, $perPage);

        return ApiResponse::paginated($result['analyses'], $result['total'], $page, $perPage);
    }

    /**
     * GET /flip/results/{id} — Get single analysis result.
     */
    public function getResult(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $analysisId = (int) $request->get_param('id');
        $result = $this->analysisService->getAnalysis($analysisId);

        if ($result === null) {
            return ApiResponse::error('Analysis result not found.', 404);
        }

        return ApiResponse::success($result);
    }

    /**
     * GET /flip/results/{id}/comps — Get comparables for an analysis.
     */
    public function getComps(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $analysisId = (int) $request->get_param('id');
        $result = $this->analysisService->getAnalysis($analysisId);

        if ($result === null) {
            return ApiResponse::error('Analysis result not found.', 404);
        }

        return ApiResponse::success($result['comparables'] ?? []);
    }

    /**
     * GET /flip/summary — Per-city summary stats.
     */
    public function getSummary(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = $request->get_param('report_id');

        if (empty($reportId)) {
            return ApiResponse::error('report_id query parameter is required.', 422);
        }

        $summary = $this->analysisService->getReportSummary((int) $reportId);

        return ApiResponse::success($summary);
    }

    /**
     * POST /flip/arv — Calculate ARV only (without full analysis).
     */
    public function calculateArv(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $subjectData = [];
        $subjectFields = [
            'listing_id', 'latitude', 'longitude', 'property_type',
            'bedrooms_total', 'bathrooms_total', 'living_area',
            'year_built', 'lot_size_acres', 'garage_spaces',
        ];

        foreach ($subjectFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $subjectData[$field] = $value;
            }
        }

        if (empty($subjectData['latitude']) || empty($subjectData['longitude'])) {
            return ApiResponse::error('Latitude and longitude are required.', 422);
        }

        try {
            $result = $this->arvService->calculateArv($subjectData);

            return ApiResponse::success($result);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /flip/config/cities — Get target cities.
     */
    public function getCities(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $cities = get_option('bmn_flip_target_cities', []);

        return ApiResponse::success($cities);
    }

    /**
     * POST /flip/config/cities — Update target cities (admin).
     */
    public function setCities(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $cities = $request->get_param('cities');

        if (!is_array($cities)) {
            return ApiResponse::error('cities must be an array.', 422);
        }

        update_option('bmn_flip_target_cities', $cities);

        return ApiResponse::success(['updated' => true]);
    }

    /**
     * GET /flip/config/weights — Get scoring weights.
     */
    public function getWeights(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $defaults = [
            'arv_spread'     => 0.30,
            'renovation_roi' => 0.25,
            'days_on_market' => 0.15,
            'price_drop'     => 0.15,
            'comp_confidence' => 0.10,
            'location'       => 0.05,
        ];

        $weights = get_option('bmn_flip_scoring_weights', []);
        $weights = array_merge($defaults, $weights);

        return ApiResponse::success($weights);
    }
}
