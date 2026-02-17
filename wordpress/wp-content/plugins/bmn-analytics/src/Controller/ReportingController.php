<?php

declare(strict_types=1);

namespace BMN\Analytics\Controller;

use BMN\Analytics\Service\ReportingService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for analytics reporting endpoints (GET queries).
 *
 * All reporting endpoints require authentication (admin access).
 *
 * Endpoints:
 *   GET /bmn/v1/analytics/trends                          - Trend data
 *   GET /bmn/v1/analytics/top-properties                  - Most viewed properties
 *   GET /bmn/v1/analytics/top-content                     - Most viewed pages
 *   GET /bmn/v1/analytics/traffic-sources                 - Traffic source breakdown
 *   GET /bmn/v1/analytics/property/{listing_id}           - Property stats
 */
class ReportingController extends RestController
{
    protected string $resource = 'analytics';

    private readonly ReportingService $reportingService;

    public function __construct(ReportingService $reportingService, ?AuthMiddleware $authMiddleware = null)
    {
        parent::__construct($authMiddleware);
        $this->reportingService = $reportingService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '/trends',
                'method'   => 'GET',
                'callback' => 'getTrends',
                'auth'     => true,
            ],
            [
                'path'     => '/top-properties',
                'method'   => 'GET',
                'callback' => 'getTopProperties',
                'auth'     => true,
            ],
            [
                'path'     => '/top-content',
                'method'   => 'GET',
                'callback' => 'getTopContent',
                'auth'     => true,
            ],
            [
                'path'     => '/traffic-sources',
                'method'   => 'GET',
                'callback' => 'getTrafficSources',
                'auth'     => true,
            ],
            [
                'path'     => '/property/(?P<listing_id>[A-Za-z0-9_-]+)',
                'method'   => 'GET',
                'callback' => 'getPropertyStats',
                'auth'     => true,
            ],
        ];
    }

    /**
     * GET /analytics/trends - Get trend data for a date range.
     */
    public function getTrends(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'start_date' => ['type' => 'string', 'required' => true],
            'end_date'   => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $trends = $this->reportingService->getTrends(
            (string) $request->get_param('start_date'),
            (string) $request->get_param('end_date'),
            (string) ($request->get_param('interval') ?? 'day'),
        );

        return ApiResponse::success($trends);
    }

    /**
     * GET /analytics/top-properties - Get most viewed properties.
     */
    public function getTopProperties(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'start_date' => ['type' => 'string', 'required' => true],
            'end_date'   => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $limit = $request->get_param('limit') !== null
            ? (int) $request->get_param('limit')
            : 10;

        $properties = $this->reportingService->getTopProperties(
            (string) $request->get_param('start_date'),
            (string) $request->get_param('end_date'),
            $limit,
        );

        return ApiResponse::success($properties);
    }

    /**
     * GET /analytics/top-content - Get most viewed pages.
     */
    public function getTopContent(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'start_date' => ['type' => 'string', 'required' => true],
            'end_date'   => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $limit = $request->get_param('limit') !== null
            ? (int) $request->get_param('limit')
            : 10;

        $content = $this->reportingService->getTopContent(
            (string) $request->get_param('start_date'),
            (string) $request->get_param('end_date'),
            $limit,
        );

        return ApiResponse::success($content);
    }

    /**
     * GET /analytics/traffic-sources - Get traffic source breakdown.
     */
    public function getTrafficSources(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'start_date' => ['type' => 'string', 'required' => true],
            'end_date'   => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $sources = $this->reportingService->getTrafficSources(
            (string) $request->get_param('start_date'),
            (string) $request->get_param('end_date'),
        );

        return ApiResponse::success($sources);
    }

    /**
     * GET /analytics/property/{listing_id} - Get stats for a specific property.
     */
    public function getPropertyStats(WP_REST_Request $request): WP_REST_Response
    {
        $listingId = (string) $request->get_param('listing_id');

        if ($listingId === '') {
            return ApiResponse::error('Parameter "listing_id" is required.', 422);
        }

        $stats = $this->reportingService->getPropertyStats($listingId);

        return ApiResponse::success($stats);
    }
}
