<?php

declare(strict_types=1);

namespace BMN\CMA\Controller;

use BMN\CMA\Service\CmaReportService;
use BMN\CMA\Service\ComparableSearchService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class CmaController extends RestController
{
    protected string $resource = 'cma';

    private readonly CmaReportService $reportService;
    private readonly ComparableSearchService $searchService;

    public function __construct(
        CmaReportService $reportService,
        ComparableSearchService $searchService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->reportService = $reportService;
        $this->searchService = $searchService;
    }

    protected function getRoutes(): array
    {
        return [
            // 1. POST /cma — Generate a CMA report.
            [
                'path'     => '',
                'method'   => 'POST',
                'callback' => 'generate',
                'auth'     => true,
            ],
            // 2. GET /cma/sessions — List user sessions.
            [
                'path'     => '/sessions',
                'method'   => 'GET',
                'callback' => 'listSessions',
                'auth'     => true,
            ],
            // 3. POST /cma/sessions — Save session (alias for generate).
            [
                'path'     => '/sessions',
                'method'   => 'POST',
                'callback' => 'generate',
                'auth'     => true,
            ],
            // 4. GET /cma/sessions/{id} — Get a single session.
            [
                'path'     => '/sessions/(?P<id>\d+)',
                'method'   => 'GET',
                'callback' => 'getSession',
                'auth'     => true,
            ],
            // 5. PUT /cma/sessions/{id} — Update a session.
            [
                'path'     => '/sessions/(?P<id>\d+)',
                'method'   => 'PUT',
                'callback' => 'updateSession',
                'auth'     => true,
            ],
            // 6. DELETE /cma/sessions/{id} — Delete a session.
            [
                'path'     => '/sessions/(?P<id>\d+)',
                'method'   => 'DELETE',
                'callback' => 'deleteSession',
                'auth'     => true,
            ],
            // 7. POST /cma/sessions/{id}/favorite — Toggle favorite.
            [
                'path'     => '/sessions/(?P<id>\d+)/favorite',
                'method'   => 'POST',
                'callback' => 'toggleFavorite',
                'auth'     => true,
            ],
            // 8. GET /cma/comparables/{listing_id} — Find comparables for a listing.
            [
                'path'     => '/comparables/(?P<listing_id>[A-Za-z0-9_-]+)',
                'method'   => 'GET',
                'callback' => 'findComparables',
                'auth'     => true,
            ],
            // 9. GET /cma/history/{listing_id} — Property value history.
            [
                'path'     => '/history/(?P<listing_id>[A-Za-z0-9_-]+)',
                'method'   => 'GET',
                'callback' => 'getPropertyHistory',
                'auth'     => true,
            ],
            // 10. GET /cma/history/trends — Value trend data.
            [
                'path'     => '/history/trends',
                'method'   => 'GET',
                'callback' => 'getValueTrends',
                'auth'     => true,
            ],
        ];
    }

    /**
     * POST /cma — Generate a CMA report.
     */
    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $subjectData = [];
        $requiredSubjectFields = [
            'listing_id', 'address', 'city', 'state', 'zip',
            'latitude', 'longitude', 'bedrooms_total', 'bathrooms_total',
            'living_area', 'year_built', 'lot_size_acres', 'garage_spaces',
            'property_type', 'session_name', 'is_arv_mode', 'overrides',
        ];

        foreach ($requiredSubjectFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $subjectData[$field] = $value;
            }
        }

        // Require at least listing_id or address.
        if (empty($subjectData['listing_id']) && empty($subjectData['address'])) {
            return ApiResponse::error('Either listing_id or subject address is required.', 422);
        }

        // Require coordinates for search.
        if (empty($subjectData['latitude']) || empty($subjectData['longitude'])) {
            return ApiResponse::error('Subject latitude and longitude are required.', 422);
        }

        $filters = [];
        $filterFields = ['radius_miles', 'min_comps', 'max_comps', 'months_back', 'property_type', 'status'];
        foreach ($filterFields as $field) {
            $value = $request->get_param('filter_' . $field);
            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        try {
            $result = $this->reportService->generateReport($subjectData, $filters, $user->ID);
            return ApiResponse::success($result);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /cma/sessions — List user's CMA sessions.
     */
    public function listSessions(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));

        $result = $this->reportService->getUserReports($user->ID, $page, $perPage);

        return ApiResponse::paginated($result['reports'], $result['total'], $page, $perPage);
    }

    /**
     * GET /cma/sessions/{id} — Get a single CMA session.
     */
    public function getSession(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $result = $this->reportService->getReport($reportId, $user->ID);

        if ($result === null) {
            return ApiResponse::error('CMA session not found.', 404);
        }

        return ApiResponse::success($result);
    }

    /**
     * PUT /cma/sessions/{id} — Update a CMA session.
     */
    public function updateSession(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $data = array_filter([
            'session_name' => $request->get_param('session_name'),
            'is_arv_mode'  => $request->get_param('is_arv_mode'),
        ], static fn (mixed $v): bool => $v !== null);

        if (empty($data)) {
            return ApiResponse::error('No updatable fields provided.', 422);
        }

        $updated = $this->reportService->updateReport($reportId, $user->ID, $data);

        if (!$updated) {
            return ApiResponse::error('CMA session not found or update failed.', 404);
        }

        return ApiResponse::success(['updated' => true]);
    }

    /**
     * DELETE /cma/sessions/{id} — Delete a CMA session.
     */
    public function deleteSession(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $deleted = $this->reportService->deleteReport($reportId, $user->ID);

        if (!$deleted) {
            return ApiResponse::error('CMA session not found.', 404);
        }

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * POST /cma/sessions/{id}/favorite — Toggle favorite on a session.
     */
    public function toggleFavorite(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $toggled = $this->reportService->toggleFavorite($reportId, $user->ID);

        if (!$toggled) {
            return ApiResponse::error('CMA session not found.', 404);
        }

        return ApiResponse::success(['toggled' => true]);
    }

    /**
     * GET /cma/comparables/{listing_id} — Find comparables for a listing.
     */
    public function findComparables(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $listingId = (string) $request->get_param('listing_id');

        // Build subject from query params.
        $subject = [
            'listing_id' => $listingId,
            'latitude'   => $request->get_param('latitude'),
            'longitude'  => $request->get_param('longitude'),
        ];

        if (empty($subject['latitude']) || empty($subject['longitude'])) {
            return ApiResponse::error('Latitude and longitude are required.', 422);
        }

        $filters = [];
        $filterFields = ['radius_miles', 'min_comps', 'max_comps', 'months_back', 'property_type'];
        foreach ($filterFields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        $comparables = $this->searchService->findComparables($subject, $filters);

        return ApiResponse::success([
            'listing_id'  => $listingId,
            'comparables' => $comparables,
            'count'       => count($comparables),
        ]);
    }

    /**
     * GET /cma/history/{listing_id} — Property value history.
     */
    public function getPropertyHistory(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $listingId = (string) $request->get_param('listing_id');
        $history = $this->reportService->getPropertyHistory($listingId);

        return ApiResponse::success([
            'listing_id' => $listingId,
            'history'    => $history,
        ]);
    }

    /**
     * GET /cma/history/trends — Value trend data for charting.
     */
    public function getValueTrends(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $listingId = $request->get_param('listing_id');

        if (empty($listingId)) {
            return ApiResponse::error('listing_id query parameter is required.', 422);
        }

        $trends = $this->reportService->getValueTrends((string) $listingId);

        return ApiResponse::success([
            'listing_id' => $listingId,
            'trends'     => $trends,
        ]);
    }
}
