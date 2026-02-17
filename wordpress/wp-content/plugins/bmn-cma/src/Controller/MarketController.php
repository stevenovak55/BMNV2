<?php

declare(strict_types=1);

namespace BMN\CMA\Controller;

use BMN\CMA\Service\MarketConditionsService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Http\ApiResponse;
use BMN\Platform\Http\RestController;
use WP_REST_Request;
use WP_REST_Response;

final class MarketController extends RestController
{
    protected string $resource = 'market-conditions';

    private readonly MarketConditionsService $marketService;

    public function __construct(
        MarketConditionsService $marketService,
        ?AuthMiddleware $authMiddleware = null,
    ) {
        parent::__construct($authMiddleware);
        $this->marketService = $marketService;
    }

    protected function getRoutes(): array
    {
        return [
            // 1. GET /market-conditions — Get conditions for a city.
            [
                'path'     => '',
                'method'   => 'GET',
                'callback' => 'getConditions',
                'auth'     => false,
            ],
            // 2. GET /market-conditions/summary — Market summary for all property types.
            [
                'path'     => '/summary',
                'method'   => 'GET',
                'callback' => 'getSummary',
                'auth'     => false,
            ],
            // 3. GET /market-conditions/trends — Historical trend data.
            [
                'path'     => '/trends',
                'method'   => 'GET',
                'callback' => 'getTrends',
                'auth'     => false,
            ],
        ];
    }

    /**
     * GET /market-conditions — Get market conditions for a city.
     */
    public function getConditions(WP_REST_Request $request): WP_REST_Response
    {
        $city = $request->get_param('city');

        if (empty($city)) {
            return ApiResponse::error('City query parameter is required.', 422);
        }

        $propertyType = $request->get_param('property_type') ?? 'all';
        $conditions = $this->marketService->getConditions((string) $city, (string) $propertyType);

        return ApiResponse::success($conditions);
    }

    /**
     * GET /market-conditions/summary — Market summary across all property types.
     */
    public function getSummary(WP_REST_Request $request): WP_REST_Response
    {
        $city = $request->get_param('city');

        if (empty($city)) {
            return ApiResponse::error('City query parameter is required.', 422);
        }

        $summary = $this->marketService->getSummary((string) $city);

        return ApiResponse::success($summary);
    }

    /**
     * GET /market-conditions/trends — Historical trend data from snapshots.
     */
    public function getTrends(WP_REST_Request $request): WP_REST_Response
    {
        $city = $request->get_param('city');

        if (empty($city)) {
            return ApiResponse::error('City query parameter is required.', 422);
        }

        $propertyType = $request->get_param('property_type') ?? 'all';
        $months = min(60, max(1, (int) ($request->get_param('months') ?? 12)));

        $trends = $this->marketService->getHistoricalTrends(
            (string) $city,
            (string) $propertyType,
            $months
        );

        return ApiResponse::success([
            'city'          => $city,
            'property_type' => $propertyType,
            'months'        => $months,
            'trends'        => $trends,
        ]);
    }
}
