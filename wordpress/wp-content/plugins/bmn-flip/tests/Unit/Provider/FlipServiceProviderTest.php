<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Provider;

use BMN\Flip\Controller\FlipController;
use BMN\Flip\Controller\ReportController;
use BMN\Flip\Provider\FlipServiceProvider;
use BMN\Flip\Repository\FlipAnalysisRepository;
use BMN\Flip\Repository\FlipComparableRepository;
use BMN\Flip\Repository\FlipReportRepository;
use BMN\Flip\Repository\MonitorSeenRepository;
use BMN\Flip\Service\ArvService;
use BMN\Flip\Service\FinancialService;
use BMN\Flip\Service\FlipAnalysisService;
use BMN\Flip\Service\ReportService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use PHPUnit\Framework\TestCase;

final class FlipServiceProviderTest extends TestCase
{
    private Container $container;
    private FlipServiceProvider $provider;

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

        $this->provider = new FlipServiceProvider();
        $this->provider->register($this->container);
    }

    // ------------------------------------------------------------------
    // register — bindings
    // ------------------------------------------------------------------

    public function testRegisterBindsAllRepositories(): void
    {
        $this->assertTrue($this->container->has(FlipAnalysisRepository::class));
        $this->assertTrue($this->container->has(FlipComparableRepository::class));
        $this->assertTrue($this->container->has(FlipReportRepository::class));
        $this->assertTrue($this->container->has(MonitorSeenRepository::class));
    }

    public function testRegisterBindsAllServices(): void
    {
        $this->assertTrue($this->container->has(ArvService::class));
        $this->assertTrue($this->container->has(FinancialService::class));
        $this->assertTrue($this->container->has(FlipAnalysisService::class));
        $this->assertTrue($this->container->has(ReportService::class));
    }

    public function testRegisterBindsAllControllers(): void
    {
        $this->assertTrue($this->container->has(FlipController::class));
        $this->assertTrue($this->container->has(ReportController::class));
    }

    // ------------------------------------------------------------------
    // register — resolve individual classes
    // ------------------------------------------------------------------

    public function testResolveFlipAnalysisRepository(): void
    {
        $repo = $this->container->make(FlipAnalysisRepository::class);
        $this->assertInstanceOf(FlipAnalysisRepository::class, $repo);
    }

    public function testResolveFlipComparableRepository(): void
    {
        $repo = $this->container->make(FlipComparableRepository::class);
        $this->assertInstanceOf(FlipComparableRepository::class, $repo);
    }

    public function testResolveFinancialService(): void
    {
        $service = $this->container->make(FinancialService::class);
        $this->assertInstanceOf(FinancialService::class, $service);
    }

    public function testResolveFlipAnalysisService(): void
    {
        $service = $this->container->make(FlipAnalysisService::class);
        $this->assertInstanceOf(FlipAnalysisService::class, $service);
    }

    public function testResolveReportService(): void
    {
        $service = $this->container->make(ReportService::class);
        $this->assertInstanceOf(ReportService::class, $service);
    }

    public function testResolveFlipController(): void
    {
        $controller = $this->container->make(FlipController::class);
        $this->assertInstanceOf(FlipController::class, $controller);
    }

    // ------------------------------------------------------------------
    // singletons
    // ------------------------------------------------------------------

    public function testSingletonsBehaveCorrectly(): void
    {
        $repo1 = $this->container->make(FlipAnalysisRepository::class);
        $repo2 = $this->container->make(FlipAnalysisRepository::class);

        $this->assertSame($repo1, $repo2);
    }

    // ------------------------------------------------------------------
    // boot
    // ------------------------------------------------------------------

    public function testBootRegistersRestApiInitAction(): void
    {
        // Clear any existing registered actions.
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }
}
