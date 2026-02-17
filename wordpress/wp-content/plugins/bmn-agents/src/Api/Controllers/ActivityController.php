<?php

declare(strict_types=1);

namespace BMN\Agents\Api\Controllers;

use BMN\Agents\Service\ActivityService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class ActivityController extends RestController
{
    protected string $resource = '';

    private readonly ActivityService $activityService;

    public function __construct(
        ActivityService $activityService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->activityService = $activityService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => 'agent/activity',
                'method'   => 'GET',
                'callback' => 'getActivityFeed',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/metrics',
                'method'   => 'GET',
                'callback' => 'getMetrics',
                'auth'     => true,
            ],
            [
                'path'     => 'agent/clients/(?P<client_id>\d+)/activity',
                'method'   => 'GET',
                'callback' => 'getClientActivity',
                'auth'     => true,
            ],
        ];
    }

    /**
     * GET /agent/activity — Agent's client activity feed.
     */
    public function getActivityFeed(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(100, max(1, (int) ($request->get_param('per_page') ?? 50)));

        $activities = $this->activityService->getAgentActivityFeed(
            (int) $user->ID,
            $page,
            $perPage
        );

        return ApiResponse::success($activities);
    }

    /**
     * GET /agent/metrics — Agent dashboard metrics.
     */
    public function getMetrics(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $days = min(365, max(1, (int) ($request->get_param('days') ?? 30)));

        $metrics = $this->activityService->getAgentMetrics((int) $user->ID, $days);

        return ApiResponse::success($metrics);
    }

    /**
     * GET /agent/clients/{client_id}/activity — Specific client's activity.
     */
    public function getClientActivity(WP_REST_Request $request): WP_REST_Response
    {
        $user = $this->getCurrentUser();

        if ($user === null) {
            return ApiResponse::error('Not authenticated.', 401);
        }

        $clientId = (int) $request->get_param('client_id');
        $limit = min(100, max(1, (int) ($request->get_param('limit') ?? 50)));

        $activities = $this->activityService->getClientActivity(
            (int) $user->ID,
            $clientId,
            $limit
        );

        return ApiResponse::success($activities);
    }
}
