<?php

declare(strict_types=1);

namespace BMN\Flip\Controller;

use BMN\Flip\Service\ReportService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class ReportController extends RestController
{
    protected string $resource = 'flip/reports';

    private readonly ReportService $reportService;

    public function __construct(
        ReportService $reportService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->reportService = $reportService;
    }

    protected function getRoutes(): array
    {
        return [
            // 1. GET /flip/reports — List user's reports.
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'listReports',
                'auth'     => true,
            ],
            // 2. POST /flip/reports — Create a new report.
            [
                'path'     => '',
                'method'   => 'POST',
                'callback' => 'createReport',
                'auth'     => true,
            ],
            // 3. GET /flip/reports/{id} — Get single report.
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'GET',
                'callback' => 'getReport',
                'auth'     => true,
            ],
            // 4. PUT /flip/reports/{id} — Update report.
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'PUT',
                'callback' => 'updateReport',
                'auth'     => true,
            ],
            // 5. DELETE /flip/reports/{id} — Delete report.
            [
                'path'     => '/(?P<id>\d+)',
                'method'   => 'DELETE',
                'callback' => 'deleteReport',
                'auth'     => true,
            ],
            // 6. POST /flip/reports/{id}/favorite — Toggle favorite.
            [
                'path'     => '/(?P<id>\d+)/favorite',
                'method'   => 'POST',
                'callback' => 'toggleFavorite',
                'auth'     => true,
            ],
        ];
    }

    /**
     * GET /flip/reports — List user's reports.
     */
    public function listReports(WP_REST_Request $request): WP_REST_Response
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
     * POST /flip/reports — Create a new report.
     */
    public function createReport(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $name = $request->get_param('name');

        if (empty($name)) {
            return ApiResponse::error('Report name is required.', 422);
        }

        $cities = $request->get_param('cities');
        $filters = $request->get_param('filters');
        $type = $request->get_param('type') ?? 'manual';

        try {
            $reportId = $this->reportService->createReport(
                $user->ID,
                (string) $name,
                is_array($cities) ? $cities : [],
                is_array($filters) ? $filters : [],
                (string) $type,
            );

            return ApiResponse::success(['report_id' => $reportId]);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /flip/reports/{id} — Get single report.
     */
    public function getReport(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $result = $this->reportService->getReport($reportId, $user->ID);

        if ($result === null) {
            return ApiResponse::error('Report not found.', 404);
        }

        return ApiResponse::success($result);
    }

    /**
     * PUT /flip/reports/{id} — Update report.
     */
    public function updateReport(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $data = array_filter([
            'name'               => $request->get_param('name'),
            'status'             => $request->get_param('status'),
            'filters'            => $request->get_param('filters'),
            'notification_level' => $request->get_param('notification_level'),
        ], static fn (mixed $v): bool => $v !== null);

        if (empty($data)) {
            return ApiResponse::error('No updatable fields provided.', 422);
        }

        $updated = $this->reportService->updateReport($reportId, $user->ID, $data);

        if (!$updated) {
            return ApiResponse::error('Report not found or update failed.', 404);
        }

        return ApiResponse::success(['updated' => true]);
    }

    /**
     * DELETE /flip/reports/{id} — Delete report.
     */
    public function deleteReport(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $reportId = (int) $request->get_param('id');
        $deleted = $this->reportService->deleteReport($reportId, $user->ID);

        if (!$deleted) {
            return ApiResponse::error('Report not found.', 404);
        }

        return ApiResponse::success(['deleted' => true]);
    }

    /**
     * POST /flip/reports/{id}/favorite — Toggle favorite.
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
            return ApiResponse::error('Report not found.', 404);
        }

        return ApiResponse::success(['toggled' => true]);
    }
}
