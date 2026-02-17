<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Api;

use BMN\Agents\Api\Controllers\AgentController;
use BMN\Agents\Service\AgentProfileService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_REST_Request;

final class AgentControllerTest extends TestCase
{
    private AgentProfileService $profileService;
    private AgentController $controller;

    protected function setUp(): void
    {
        $GLOBALS['wp_rest_routes'] = [];
        unset($GLOBALS['current_user']);

        $this->profileService = $this->createMock(AgentProfileService::class);
        $this->controller = new AgentController($this->profileService);
    }

    // ------------------------------------------------------------------
    // Route registration
    // ------------------------------------------------------------------

    public function testRegistersListAgentsRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agents', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersFeaturedRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agents/featured', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersGetAgentRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agents/(?P<agent_mls_id>[^/]+)', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersUpdateProfileRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agents/(?P<agent_mls_id>[^/]+)/profile', $GLOBALS['wp_rest_routes']);
    }

    public function testRegistersLinkUserRoute(): void
    {
        $this->controller->registerRoutes();
        $this->assertArrayHasKey('bmn/v1/agents/(?P<agent_mls_id>[^/]+)/link-user', $GLOBALS['wp_rest_routes']);
    }

    public function testPublicRoutesHaveNoAuth(): void
    {
        $this->controller->registerRoutes();

        $publicRoutes = [
            'agents',
            'agents/featured',
            'agents/(?P<agent_mls_id>[^/]+)',
        ];

        foreach ($publicRoutes as $route) {
            $this->assertSame(
                '__return_true',
                $GLOBALS['wp_rest_routes']["bmn/v1/{$route}"]['permission_callback'],
                "Route {$route} should be public"
            );
        }
    }

    // ------------------------------------------------------------------
    // listAgents
    // ------------------------------------------------------------------

    public function testListAgentsReturnsSuccessWithPagination(): void
    {
        $this->profileService->method('listAgents')->willReturn([
            'items' => [['agent_mls_id' => 'AGT001', 'full_name' => 'Test']],
            'total' => 50,
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agents');
        $response = $this->controller->listAgents($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertSame(50, $data['meta']['total']);
    }

    // ------------------------------------------------------------------
    // getFeaturedAgents
    // ------------------------------------------------------------------

    public function testGetFeaturedAgentsReturnsSuccess(): void
    {
        $this->profileService->method('getFeaturedAgents')->willReturn([
            ['agent_mls_id' => 'AGT001', 'is_featured' => true],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agents/featured');
        $response = $this->controller->getFeaturedAgents($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
    }

    // ------------------------------------------------------------------
    // getAgent
    // ------------------------------------------------------------------

    public function testGetAgentReturnsSuccess(): void
    {
        $this->profileService->method('getAgent')->willReturn([
            'agent_mls_id' => 'AGT001', 'full_name' => 'John Smith',
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/agents/AGT001');
        $request->set_param('agent_mls_id', 'AGT001');
        $response = $this->controller->getAgent($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame('AGT001', $data['data']['agent_mls_id']);
    }

    public function testGetAgentReturns404WhenNotFound(): void
    {
        $this->profileService->method('getAgent')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/agents/NONEXISTENT');
        $request->set_param('agent_mls_id', 'NONEXISTENT');
        $response = $this->controller->getAgent($request);

        $this->assertSame(404, $response->get_status());
    }

    // ------------------------------------------------------------------
    // updateProfile (authenticated)
    // ------------------------------------------------------------------

    public function testUpdateProfileReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('PUT', '/bmn/v1/agents/AGT001/profile');
        $request->set_param('agent_mls_id', 'AGT001');
        $response = $this->controller->updateProfile($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testUpdateProfileReturnsSuccessWhenAuthenticated(): void
    {
        $user = new \WP_User(1);
        $user->roles = ['administrator'];
        $GLOBALS['current_user'] = $user;

        $this->profileService->method('saveProfile')->willReturn(5);

        $request = new WP_REST_Request('PUT', '/bmn/v1/agents/AGT001/profile');
        $request->set_param('agent_mls_id', 'AGT001');
        $request->set_param('bio', 'Updated bio');
        $response = $this->controller->updateProfile($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(5, $data['data']['profile_id']);
    }

    // ------------------------------------------------------------------
    // linkUser (authenticated)
    // ------------------------------------------------------------------

    public function testLinkUserReturns401WhenNotAuthenticated(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/agents/AGT001/link-user');
        $request->set_param('agent_mls_id', 'AGT001');
        $request->set_param('user_id', 42);
        $response = $this->controller->linkUser($request);

        $this->assertSame(401, $response->get_status());
    }

    public function testLinkUserReturns422WhenMissingUserId(): void
    {
        $user = new \WP_User(1);
        $user->roles = ['administrator'];
        $GLOBALS['current_user'] = $user;

        $request = new WP_REST_Request('POST', '/bmn/v1/agents/AGT001/link-user');
        $request->set_param('agent_mls_id', 'AGT001');
        $response = $this->controller->linkUser($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testLinkUserReturnsSuccessWhenValid(): void
    {
        $user = new \WP_User(1);
        $user->roles = ['administrator'];
        $GLOBALS['current_user'] = $user;

        $this->profileService->method('linkToUser')->willReturn(3);

        $request = new WP_REST_Request('POST', '/bmn/v1/agents/AGT001/link-user');
        $request->set_param('agent_mls_id', 'AGT001');
        $request->set_param('user_id', 42);
        $response = $this->controller->linkUser($request);

        $data = $response->get_data();
        $this->assertSame(200, $response->get_status());
        $this->assertTrue($data['success']);
        $this->assertSame(42, $data['data']['user_id']);
    }
}
