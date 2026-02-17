<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Api;

use BMN\Agents\Api\Controllers\SharedPropertyController;
use BMN\Agents\Service\SharedPropertyService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class SharedPropertyControllerTest extends TestCase
{
    private SharedPropertyService $service;
    private SharedPropertyController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];
        unset($GLOBALS['current_user']);

        $this->service = $this->createMock(SharedPropertyService::class);
        $this->controller = new SharedPropertyController($this->service);
    }

    public function testRegistersSharePropertiesRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/share-properties', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersGetSharedRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/shared-properties', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersRespondRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/shared-properties/(?P<id>\d+)/respond', $GLOBALS['wp_rest_routes']);
    }

    public function testSharePropertiesReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/agent/share-properties');
        $response = $this->controller->shareProperties($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testSharePropertiesReturns201OnSuccess(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('shareProperties')->willReturn(2);

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/share-properties');
        $request->set_param('client_ids', [20]);
        $request->set_param('listing_ids', ['MLS001', 'MLS002']);
        $response = $this->controller->shareProperties($request);

        $data = $response->get_data();
        $this->assertSame(201, $response->get_status());
        $this->assertSame(2, $data['data']['shared_count']);
    }

    public function testSharePropertiesReturns422WhenMissingClientIds(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/share-properties');
        $request->set_param('listing_ids', ['MLS001']);
        $response = $this->controller->shareProperties($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testGetSharedPropertiesReturnsPaginated(): void
    {
        $user = new \WP_User(20);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getSharedForClient')->willReturn([
            'items' => [
                (object) ['id' => 1, 'agent_user_id' => 10, 'listing_id' => 'MLS001',
                    'agent_note' => null, 'client_response' => 'none', 'client_note' => null,
                    'is_dismissed' => 0, 'view_count' => 0, 'first_viewed_at' => null,
                    'shared_at' => '2026-01-01'],
            ],
            'total' => 1,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/shared-properties');
        $response = $this->controller->getSharedProperties($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $data['data']);
    }

    public function testRespondToShareReturnsSuccess(): void
    {
        $user = new \WP_User(20);
        $GLOBALS['current_user'] = $user;

        $this->service->method('respondToShare')->willReturn(true);

        $request = new WP_REST_Request('PUT', '/bmn/v1/shared-properties/1/respond');
        $request->set_param('id', '1');
        $request->set_param('response', 'interested');
        $response = $this->controller->respondToShare($request);

        $this->assertSame(200, $response->get_status());
    }

    public function testDismissShareReturnsSuccess(): void
    {
        $user = new \WP_User(20);
        $GLOBALS['current_user'] = $user;

        $this->service->method('dismissShare')->willReturn(true);

        $request = new WP_REST_Request('PUT', '/bmn/v1/shared-properties/1/dismiss');
        $request->set_param('id', '1');
        $response = $this->controller->dismissShare($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['data']['dismissed']);
    }
}
