<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Provider;

use BMN\Agents\Api\Controllers\ActivityController;
use BMN\Agents\Api\Controllers\AgentController;
use BMN\Agents\Api\Controllers\ReferralController;
use BMN\Agents\Api\Controllers\RelationshipController;
use BMN\Agents\Api\Controllers\SharedPropertyController;
use BMN\Agents\Provider\AgentsServiceProvider;
use BMN\Agents\Repository\ActivityLogRepository;
use BMN\Agents\Repository\AgentProfileRepository;
use BMN\Agents\Repository\AgentReadRepository;
use BMN\Agents\Repository\OfficeReadRepository;
use BMN\Agents\Repository\ReferralCodeRepository;
use BMN\Agents\Repository\ReferralSignupRepository;
use BMN\Agents\Repository\RelationshipRepository;
use BMN\Agents\Repository\SharedPropertyRepository;
use BMN\Agents\Service\ActivityService;
use BMN\Agents\Service\AgentProfileService;
use BMN\Agents\Service\ReferralService;
use BMN\Agents\Service\RelationshipService;
use BMN\Agents\Service\SharedPropertyService;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use PHPUnit\Framework\TestCase;

final class AgentsServiceProviderTest extends TestCase
{
    private Container $container;
    private AgentsServiceProvider $provider;

    protected function setUp(): void
    {
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_rest_routes'] = [];

        $this->container = new Container();
        $this->provider = new AgentsServiceProvider();

        // Register platform service stubs.
        $wpdb = new \wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $dbService = new class($wpdb) {
            private \wpdb $wpdb;
            public function __construct(\wpdb $wpdb) { $this->wpdb = $wpdb; }
            public function getWpdb(): \wpdb { return $this->wpdb; }
        };

        $this->container->instance(\BMN\Platform\Database\DatabaseService::class, $dbService);

        // AuthMiddleware is final — create a real instance with a mocked AuthService interface.
        $authService = $this->createMock(\BMN\Platform\Auth\AuthService::class);
        $authMiddleware = new \BMN\Platform\Auth\AuthMiddleware($authService);
        $this->container->instance(\BMN\Platform\Auth\AuthMiddleware::class, $authMiddleware);
    }

    public function testExtendsServiceProvider(): void
    {
        $this->assertInstanceOf(ServiceProvider::class, $this->provider);
    }

    public function testRegisterBindsAllRepositories(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(AgentReadRepository::class));
        $this->assertTrue($this->container->has(OfficeReadRepository::class));
        $this->assertTrue($this->container->has(AgentProfileRepository::class));
        $this->assertTrue($this->container->has(RelationshipRepository::class));
        $this->assertTrue($this->container->has(SharedPropertyRepository::class));
        $this->assertTrue($this->container->has(ReferralCodeRepository::class));
        $this->assertTrue($this->container->has(ReferralSignupRepository::class));
        $this->assertTrue($this->container->has(ActivityLogRepository::class));
    }

    public function testRegisterBindsAllServices(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(AgentProfileService::class));
        $this->assertTrue($this->container->has(RelationshipService::class));
        $this->assertTrue($this->container->has(SharedPropertyService::class));
        $this->assertTrue($this->container->has(ReferralService::class));
        $this->assertTrue($this->container->has(ActivityService::class));
    }

    public function testRegisterBindsAllControllers(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(AgentController::class));
        $this->assertTrue($this->container->has(RelationshipController::class));
        $this->assertTrue($this->container->has(SharedPropertyController::class));
        $this->assertTrue($this->container->has(ReferralController::class));
        $this->assertTrue($this->container->has(ActivityController::class));
    }

    public function testBootRegistersRestApiInitAction(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }

    public function testRepositoriesResolveCorrectly(): void
    {
        $this->provider->register($this->container);

        $this->assertInstanceOf(AgentReadRepository::class, $this->container->make(AgentReadRepository::class));
        $this->assertInstanceOf(OfficeReadRepository::class, $this->container->make(OfficeReadRepository::class));
        $this->assertInstanceOf(AgentProfileRepository::class, $this->container->make(AgentProfileRepository::class));
    }

    public function testServicesResolveCorrectly(): void
    {
        $this->provider->register($this->container);

        $this->assertInstanceOf(AgentProfileService::class, $this->container->make(AgentProfileService::class));
        $this->assertInstanceOf(RelationshipService::class, $this->container->make(RelationshipService::class));
        $this->assertInstanceOf(SharedPropertyService::class, $this->container->make(SharedPropertyService::class));
        $this->assertInstanceOf(ReferralService::class, $this->container->make(ReferralService::class));
        $this->assertInstanceOf(ActivityService::class, $this->container->make(ActivityService::class));
    }

    public function testControllersResolveCorrectly(): void
    {
        $this->provider->register($this->container);

        $this->assertInstanceOf(AgentController::class, $this->container->make(AgentController::class));
        $this->assertInstanceOf(RelationshipController::class, $this->container->make(RelationshipController::class));
        $this->assertInstanceOf(SharedPropertyController::class, $this->container->make(SharedPropertyController::class));
        $this->assertInstanceOf(ReferralController::class, $this->container->make(ReferralController::class));
        $this->assertInstanceOf(ActivityController::class, $this->container->make(ActivityController::class));
    }

    public function testSingletonsReturnSameInstance(): void
    {
        $this->provider->register($this->container);

        $service1 = $this->container->make(AgentProfileService::class);
        $service2 = $this->container->make(AgentProfileService::class);

        $this->assertSame($service1, $service2);
    }

    public function testBootCallsRestApiInitWithAllControllers(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        // Invoke the rest_api_init callback to verify routes register.
        $callback = $GLOBALS['wp_actions']['rest_api_init'][0]['callback'];
        $callback();

        // Verify all 21 routes are registered.
        $routes = $GLOBALS['wp_rest_routes'];

        // Agent routes (5).
        $this->assertArrayHasKey('bmn/v1/agents', $routes);
        $this->assertArrayHasKey('bmn/v1/agents/featured', $routes);

        // Relationship routes.
        $this->assertArrayHasKey('bmn/v1/my-agent', $routes);
        $this->assertArrayHasKey('bmn/v1/agent/clients', $routes);

        // Shared property routes.
        $this->assertArrayHasKey('bmn/v1/agent/share-properties', $routes);
        $this->assertArrayHasKey('bmn/v1/shared-properties', $routes);

        // Referral routes.
        $this->assertArrayHasKey('bmn/v1/agent/referral', $routes);
        $this->assertArrayHasKey('bmn/v1/agent/referral/stats', $routes);

        // Activity routes.
        $this->assertArrayHasKey('bmn/v1/agent/activity', $routes);
        $this->assertArrayHasKey('bmn/v1/agent/metrics', $routes);
    }
}
