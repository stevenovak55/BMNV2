<?php

declare(strict_types=1);

namespace BMN\Analytics\Controller;

use BMN\Analytics\Service\TrackingService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for analytics tracking endpoints (POST actions).
 *
 * All tracking endpoints are public (no auth required) so that anonymous
 * visitors can be tracked. The active-visitors endpoint requires auth.
 *
 * Endpoints:
 *   POST /bmn/v1/analytics/event           - Record a generic event
 *   POST /bmn/v1/analytics/pageview        - Record a pageview
 *   POST /bmn/v1/analytics/property-view   - Record a property view
 *   GET  /bmn/v1/analytics/active-visitors - Get active visitor count (auth)
 */
class TrackingController extends RestController
{
    protected string $resource = 'analytics';

    private readonly TrackingService $trackingService;

    public function __construct(TrackingService $trackingService, ?AuthMiddleware $authMiddleware = null)
    {
        parent::__construct($authMiddleware);
        $this->trackingService = $trackingService;
    }

    protected function getRoutes(): array
    {
        return [
            [
                'path'     => '/event',
                'method'   => 'POST',
                'callback' => 'recordEvent',
                'auth'     => false,
            ],
            [
                'path'     => '/pageview',
                'method'   => 'POST',
                'callback' => 'recordPageview',
                'auth'     => false,
            ],
            [
                'path'     => '/property-view',
                'method'   => 'POST',
                'callback' => 'recordPropertyView',
                'auth'     => false,
            ],
            [
                'path'     => '/active-visitors',
                'method'   => 'GET',
                'callback' => 'getActiveVisitors',
                'auth'     => true,
            ],
        ];
    }

    /**
     * POST /analytics/event - Record a generic analytics event.
     */
    public function recordEvent(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'event_type' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $eventData = [
            'event_type'  => (string) $request->get_param('event_type'),
            'session_id'  => $request->get_param('session_id'),
            'user_id'     => $request->get_param('user_id') !== null ? (int) $request->get_param('user_id') : null,
            'entity_id'   => $request->get_param('entity_id'),
            'entity_type' => $request->get_param('entity_type'),
            'metadata'    => $request->get_param('metadata'),
            'ip_address'  => $request->get_param('ip_address'),
            'user_agent'  => $request->get_param('user_agent'),
            'referrer'    => $request->get_param('referrer'),
        ];

        // Remove null values to let defaults apply.
        $eventData = array_filter($eventData, static fn (mixed $v): bool => $v !== null);

        $eventId = $this->trackingService->recordEvent($eventData);

        if ($eventId === false) {
            return ApiResponse::error('Failed to record event.', 500);
        }

        return ApiResponse::success(['event_id' => $eventId], [], 201);
    }

    /**
     * POST /analytics/pageview - Record a pageview event.
     */
    public function recordPageview(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'path' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $extra = [];
        if ($request->get_param('ip_address') !== null) {
            $extra['ip_address'] = $request->get_param('ip_address');
        }
        if ($request->get_param('user_agent') !== null) {
            $extra['user_agent'] = $request->get_param('user_agent');
        }
        if ($request->get_param('referrer') !== null) {
            $extra['referrer'] = $request->get_param('referrer');
        }
        if ($request->get_param('metadata') !== null) {
            $extra['metadata'] = $request->get_param('metadata');
        }

        $eventId = $this->trackingService->recordPageview(
            (string) $request->get_param('path'),
            $request->get_param('session_id'),
            $request->get_param('user_id') !== null ? (int) $request->get_param('user_id') : null,
            $extra,
        );

        if ($eventId === false) {
            return ApiResponse::error('Failed to record pageview.', 500);
        }

        return ApiResponse::success(['event_id' => $eventId], [], 201);
    }

    /**
     * POST /analytics/property-view - Record a property view event.
     */
    public function recordPropertyView(WP_REST_Request $request): WP_REST_Response
    {
        $valid = $this->validateParams($request, [
            'listing_id' => ['type' => 'string', 'required' => true],
        ]);

        if ($valid !== true) {
            return ApiResponse::error($valid->get_error_message(), 422);
        }

        $extra = [];
        if ($request->get_param('ip_address') !== null) {
            $extra['ip_address'] = $request->get_param('ip_address');
        }
        if ($request->get_param('user_agent') !== null) {
            $extra['user_agent'] = $request->get_param('user_agent');
        }
        if ($request->get_param('referrer') !== null) {
            $extra['referrer'] = $request->get_param('referrer');
        }
        if ($request->get_param('metadata') !== null) {
            $extra['metadata'] = $request->get_param('metadata');
        }

        $eventId = $this->trackingService->recordPropertyView(
            (string) $request->get_param('listing_id'),
            $request->get_param('session_id'),
            $request->get_param('user_id') !== null ? (int) $request->get_param('user_id') : null,
            $extra,
        );

        if ($eventId === false) {
            return ApiResponse::error('Failed to record property view.', 500);
        }

        return ApiResponse::success(['event_id' => $eventId], [], 201);
    }

    /**
     * GET /analytics/active-visitors - Get count of currently active visitors.
     */
    public function getActiveVisitors(WP_REST_Request $request): WP_REST_Response
    {
        $minutes = $request->get_param('minutes') !== null
            ? (int) $request->get_param('minutes')
            : 15;

        $count = $this->trackingService->getActiveVisitors($minutes);

        return ApiResponse::success([
            'active_visitors' => $count,
            'window_minutes'  => $minutes,
        ]);
    }
}
