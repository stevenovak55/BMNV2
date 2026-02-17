<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Provider;

use BMN\CMA\Controller\CmaController;
use BMN\CMA\Controller\MarketController;
use BMN\CMA\Provider\CmaServiceProvider;
use BMN\CMA\Repository\CmaReportRepository;
use BMN\CMA\Repository\ComparableRepository;
use BMN\CMA\Repository\MarketSnapshotRepository;
use BMN\CMA\Repository\ValueHistoryRepository;
use BMN\CMA\Service\AdjustmentService;
use BMN\CMA\Service\CmaReportService;
use BMN\CMA\Service\ComparableSearchService;
use BMN\CMA\Service\MarketConditionsService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use PHPUnit\Framework\TestCase;

final class CmaServiceProviderTest extends TestCase
{
    private Container $container;
    private CmaServiceProvider $provider;

    protected function setUp(): void
    {
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

        // Mock AuthService (interface) and create real AuthMiddleware (final class).
        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);
        $this->container->instance(AuthMiddleware::class, $authMiddleware);

        $this->provider = new CmaServiceProvider();
        $this->provider->register($this->container);
    }

    public function testRegisterBindsAllRepositories(): void
    {
        $this->assertTrue($this->container->has(CmaReportRepository::class));
        $this->assertTrue($this->container->has(ComparableRepository::class));
        $this->assertTrue($this->container->has(ValueHistoryRepository::class));
        $this->assertTrue($this->container->has(MarketSnapshotRepository::class));
    }

    public function testRegisterBindsAllServices(): void
    {
        $this->assertTrue($this->container->has(AdjustmentService::class));
        $this->assertTrue($this->container->has(ComparableSearchService::class));
        $this->assertTrue($this->container->has(CmaReportService::class));
        $this->assertTrue($this->container->has(MarketConditionsService::class));
    }

    public function testRegisterBindsAllControllers(): void
    {
        $this->assertTrue($this->container->has(CmaController::class));
        $this->assertTrue($this->container->has(MarketController::class));
    }

    public function testResolveCmaReportRepository(): void
    {
        $repo = $this->container->make(CmaReportRepository::class);
        $this->assertInstanceOf(CmaReportRepository::class, $repo);
    }

    public function testResolveComparableRepository(): void
    {
        $repo = $this->container->make(ComparableRepository::class);
        $this->assertInstanceOf(ComparableRepository::class, $repo);
    }

    public function testResolveAdjustmentService(): void
    {
        $service = $this->container->make(AdjustmentService::class);
        $this->assertInstanceOf(AdjustmentService::class, $service);
    }

    public function testResolveCmaReportService(): void
    {
        $service = $this->container->make(CmaReportService::class);
        $this->assertInstanceOf(CmaReportService::class, $service);
    }

    public function testResolveCmaController(): void
    {
        $controller = $this->container->make(CmaController::class);
        $this->assertInstanceOf(CmaController::class, $controller);
    }

    public function testResolveMarketController(): void
    {
        $controller = $this->container->make(MarketController::class);
        $this->assertInstanceOf(MarketController::class, $controller);
    }

    public function testSingletonsBehaveCorrectly(): void
    {
        $repo1 = $this->container->make(CmaReportRepository::class);
        $repo2 = $this->container->make(CmaReportRepository::class);

        $this->assertSame($repo1, $repo2);
    }

    public function testBootRegistersRestApiInitAction(): void
    {
        // Clear any existing registered actions.
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }
}
