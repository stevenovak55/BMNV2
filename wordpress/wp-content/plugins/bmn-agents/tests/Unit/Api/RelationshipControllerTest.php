<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Api;

use BMN\Agents\Api\Controllers\RelationshipController;
use BMN\Agents\Service\RelationshipService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class RelationshipControllerTest extends TestCase
{
    private RelationshipService $service;
    private RelationshipController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];
        unset($GLOBALS['current_user']);

        $this->service = $this->createMock(RelationshipService::class);
        $this->controller = new RelationshipController($this->service);
    }

    public function testRegistersMyAgentRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/my-agent', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersGetClientsRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/clients', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersUnassignClientRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agent/clients/(?P<client_id>\d+)', $GLOBALS['wp_rest_routes']);
    }

    public function testGetMyAgentReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/my-agent');
        $response = $this->controller->getMyAgent($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testGetMyAgentReturnsAgent(): void
    {
        $user = new \WP_User(42);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getClientAgent')->willReturn(
            (object) ['id' => 1, 'agent_user_id' => 10, 'status' => 'active', 'source' => 'manual', 'assigned_at' => '2026-01-01']
        );

        $request = new WP_REST_Request('GET', '/bmn/v1/my-agent');
        $response = $this->controller->getMyAgent($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(10, $data['data']['agent_user_id']);
    }

    public function testGetMyAgentReturnsNullWhenNoAgent(): void
    {
        $user = new \WP_User(42);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getClientAgent')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/my-agent');
        $response = $this->controller->getMyAgent($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertNull($data['data']);
    }

    public function testGetClientsReturnsPaginatedList(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('getAgentClients')->willReturn([
            'items' => [(object) ['id' => 1, 'client_user_id' => 20, 'status' => 'active', 'source' => 'manual', 'notes' => null, 'assigned_at' => '2026-01-01']],
            'total' => 1,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agent/clients');
        $response = $this->controller->getClients($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $data['data']);
    }

    public function testCreateClientReturns201(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('createClient')->willReturn([
            'user_id' => 100, 'relationship_id' => 1,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/clients');
        $request->set_param('email', 'new@test.com');
        $request->set_param('first_name', 'Jane');
        $request->set_param('last_name', 'Doe');
        $response = $this->controller->createClient($request);

        $this->assertSame(201, $response->get_status());
    }

    public function testCreateClientReturns422WhenMissingFields(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $request = new WP_REST_Request('POST', '/bmn/v1/agent/clients');
        $response = $this->controller->createClient($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testUnassignClientReturnsSuccess(): void
    {
        $user = new \WP_User(10);
        $GLOBALS['current_user'] = $user;

        $this->service->method('unassignAgent')->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/agent/clients/20');
        $request->set_param('client_id', '20');
        $response = $this->controller->unassignClient($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertSame('inactive', $data['data']['status']);
    }
}
