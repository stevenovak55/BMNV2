<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Api;

use BMN\Agents\Api\Controllers\ActivityController;
use BMN\Agents\Service\ActivityService;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class ActivityControllerTest extends TestCase
{
    private ActivityService $service;
    private ActivityController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];
        unset($GLOBALS['current_user']);

        $this->service = $this->createMock(ActivityService::class);
        $this->controller = new ActivityController($this->service);
    }

    public function testRegistersActivityFeedRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/activity', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersMetricsRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/metrics', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersClientActivityRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/clients/(?P<client_id>\d+)/activity', $GLOBALS['wp_rest_routes']);
    }

    public function testGetActivityFeedReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/agent/activity');
        $response = $this->controller->getActivityFeed($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testGetActivityFeedReturnsData(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getAgentActivityFeed')->willReturn([
            ['id' => 1, 'activity_type' => 'favorite_added', 'created_at' => '2026-01-01'],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agent/activity');
        $response = $this->controller->getActivityFeed($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $data['data']);
    }

    public function testGetMetricsReturnsData(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getAgentMetrics')->willReturn([
            'total_clients' => 15,
            'active_clients' => 8,
            'recent_activities' => 42,
            'period_days' => 30,
            'activity_by_type' => [],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agent/metrics');
        $response = $this->controller->getMetrics($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertSame(15, $data['data']['total_clients']);
    }

    public function testGetClientActivityReturnsData(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getClientActivity')->willReturn([
            ['id' => 1, 'activity_type' => 'client_login', 'created_at' => '2026-01-01'],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agent/clients/20/activity');
        $request->set_param('client_id', '20');
        $response = $this->controller->getClientActivity($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $data['data']);
    }
}
