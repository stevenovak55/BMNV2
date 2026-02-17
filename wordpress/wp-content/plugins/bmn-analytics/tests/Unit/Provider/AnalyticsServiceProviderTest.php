<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Provider;

use BMN\Analytics\Controller\ReportingController;
use BMN\Analytics\Controller\TrackingController;
use BMN\Analytics\Provider\AnalyticsServiceProvider;
use BMN\Analytics\Repository\DailyAggregateRepository;
use BMN\Analytics\Repository\EventRepository;
use BMN\Analytics\Repository\SessionRepository;
use BMN\Analytics\Service\ReportingService;
use BMN\Analytics\Service\TrackingService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnalyticsServiceProvider.
 */
final class AnalyticsServiceProviderTest extends TestCase
{
    private Container $container;
    private AnalyticsServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();

        // DatabaseService is final — use anonymous class stand-in.
        $wpdb = new \wpdb();
        $wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $wpdb;
        $dbService = new class($wpdb) {
            private \wpdb $wpdb;
            public function __construct(\wpdb $wpdb) { $this->wpdb = $wpdb; }
            public function getWpdb(): \wpdb { return $this->wpdb; }
        };
        $this->container->instance(DatabaseService::class, $dbService);

        // AuthMiddleware is final, so we instantiate it with a mocked AuthService.
        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);
        $this->container->instance(AuthMiddleware::class, $authMiddleware);

        $this->provider = new AnalyticsServiceProvider();
    }

    // ------------------------------------------------------------------
    // register()
    // ------------------------------------------------------------------

    public function testRegisterBindsEventRepository(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(EventRepository::class));
        $repo = $this->container->make(EventRepository::class);
        $this->assertInstanceOf(EventRepository::class, $repo);
    }

    public function testRegisterBindsSessionRepository(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(SessionRepository::class));
        $repo = $this->container->make(SessionRepository::class);
        $this->assertInstanceOf(SessionRepository::class, $repo);
    }

    public function testRegisterBindsDailyAggregateRepository(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(DailyAggregateRepository::class));
        $repo = $this->container->make(DailyAggregateRepository::class);
        $this->assertInstanceOf(DailyAggregateRepository::class, $repo);
    }

    public function testRegisterBindsTrackingService(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(TrackingService::class));
        $service = $this->container->make(TrackingService::class);
        $this->assertInstanceOf(TrackingService::class, $service);
    }

    public function testRegisterBindsReportingService(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(ReportingService::class));
        $service = $this->container->make(ReportingService::class);
        $this->assertInstanceOf(ReportingService::class, $service);
    }

    public function testRegisterBindsTrackingController(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(TrackingController::class));
        $controller = $this->container->make(TrackingController::class);
        $this->assertInstanceOf(TrackingController::class, $controller);
    }

    public function testRegisterBindsReportingController(): void
    {
        $this->provider->register($this->container);

        $this->assertTrue($this->container->has(ReportingController::class));
        $controller = $this->container->make(ReportingController::class);
        $this->assertInstanceOf(ReportingController::class, $controller);
    }

    // ------------------------------------------------------------------
    // Singleton behavior
    // ------------------------------------------------------------------

    public function testRegisteredServicesAreSingletons(): void
    {
        $this->provider->register($this->container);

        $service1 = $this->container->make(TrackingService::class);
        $service2 = $this->container->make(TrackingService::class);

        $this->assertSame($service1, $service2, 'TrackingService should be a singleton.');
    }

    // ------------------------------------------------------------------
    // boot()
    // ------------------------------------------------------------------

    public function testBootRegistersRestApiInitAction(): void
    {
        // Clear any previously registered actions.
        $GLOBALS['wp_actions'] = [];

        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }

    public function testBootRegistersDailyAggregateAction(): void
    {
        $GLOBALS['wp_actions'] = [];

        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('bmn_analytics_daily_aggregate', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['bmn_analytics_daily_aggregate']);
    }
}
