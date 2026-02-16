<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Provider;

use BMN\Extractor\Admin\AdminDashboard;
use BMN\Extractor\Api\Controllers\ExtractionController;
use BMN\Extractor\Provider\ExtractorServiceProvider;
use BMN\Extractor\Repository\AgentRepository;
use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\MediaRepository;
use BMN\Extractor\Repository\OfficeRepository;
use BMN\Extractor\Repository\OpenHouseRepository;
use BMN\Extractor\Repository\PropertyHistoryRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Service\BridgeApiClient;
use BMN\Extractor\Service\CronManager;
use BMN\Extractor\Service\DataNormalizer;
use BMN\Extractor\Service\ExtractionEngine;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use PHPUnit\Framework\TestCase;

class ExtractorServiceProviderTest extends TestCase
{
    private Container $container;
    private ExtractorServiceProvider $provider;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->provider = new ExtractorServiceProvider();

        // Create a real DatabaseService with the wpdb stub (not mocked, since it's final).
        $wpdb = new \wpdb();
        $dbService = new DatabaseService($wpdb);

        $this->container->instance(DatabaseService::class, $dbService);

        // Reset globals.
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_scheduled_events'] = [];
        $GLOBALS['wp_rest_routes'] = [];
        $GLOBALS['wp_options'] = [];
    }

    // ------------------------------------------------------------------
    // register()
    // ------------------------------------------------------------------

    public function testRegisterBindsAllExpectedServices(): void
    {
        $this->provider->register($this->container);

        $expectedBindings = [
            DataNormalizer::class,
            BridgeApiClient::class,
            PropertyRepository::class,
            MediaRepository::class,
            AgentRepository::class,
            OfficeRepository::class,
            OpenHouseRepository::class,
            ExtractionRepository::class,
            PropertyHistoryRepository::class,
            ExtractionEngine::class,
            CronManager::class,
            ExtractionController::class,
            AdminDashboard::class,
        ];

        foreach ($expectedBindings as $abstract) {
            $this->assertTrue(
                $this->container->has($abstract),
                "Expected binding for {$abstract} not found."
            );
        }
    }

    // ------------------------------------------------------------------
    // All services resolvable
    // ------------------------------------------------------------------

    public function testAllServicesAreResolvable(): void
    {
        $this->provider->register($this->container);

        $this->assertInstanceOf(DataNormalizer::class, $this->container->make(DataNormalizer::class));
        $this->assertInstanceOf(BridgeApiClient::class, $this->container->make(BridgeApiClient::class));
        $this->assertInstanceOf(PropertyRepository::class, $this->container->make(PropertyRepository::class));
        $this->assertInstanceOf(MediaRepository::class, $this->container->make(MediaRepository::class));
        $this->assertInstanceOf(AgentRepository::class, $this->container->make(AgentRepository::class));
        $this->assertInstanceOf(OfficeRepository::class, $this->container->make(OfficeRepository::class));
        $this->assertInstanceOf(OpenHouseRepository::class, $this->container->make(OpenHouseRepository::class));
        $this->assertInstanceOf(ExtractionRepository::class, $this->container->make(ExtractionRepository::class));
        $this->assertInstanceOf(PropertyHistoryRepository::class, $this->container->make(PropertyHistoryRepository::class));
        $this->assertInstanceOf(ExtractionEngine::class, $this->container->make(ExtractionEngine::class));
        $this->assertInstanceOf(CronManager::class, $this->container->make(CronManager::class));
        $this->assertInstanceOf(ExtractionController::class, $this->container->make(ExtractionController::class));
        $this->assertInstanceOf(AdminDashboard::class, $this->container->make(AdminDashboard::class));
    }

    // ------------------------------------------------------------------
    // Singletons
    // ------------------------------------------------------------------

    public function testSingletonsReturnSameInstance(): void
    {
        $this->provider->register($this->container);

        $norm1 = $this->container->make(DataNormalizer::class);
        $norm2 = $this->container->make(DataNormalizer::class);
        $this->assertSame($norm1, $norm2);

        $engine1 = $this->container->make(ExtractionEngine::class);
        $engine2 = $this->container->make(ExtractionEngine::class);
        $this->assertSame($engine1, $engine2);

        $propRepo1 = $this->container->make(PropertyRepository::class);
        $propRepo2 = $this->container->make(PropertyRepository::class);
        $this->assertSame($propRepo1, $propRepo2);
    }

    // ------------------------------------------------------------------
    // boot()
    // ------------------------------------------------------------------

    public function testBootRegistersRestRoutesAndCron(): void
    {
        $this->provider->register($this->container);

        // Test REST route registration independently (boot normally runs migrations first).
        $controller = $this->container->make(ExtractionController::class);
        $controller->registerRoutes();

        $this->assertArrayHasKey('bmn/v1/extractions/status', $GLOBALS['wp_rest_routes']);
        $this->assertArrayHasKey('bmn/v1/extractions/trigger', $GLOBALS['wp_rest_routes']);

        // Test cron registration independently.
        $cronManager = $this->container->make(CronManager::class);
        $cronManager->register();

        $this->assertArrayHasKey(CronManager::HOOK_EXTRACTION, $GLOBALS['wp_scheduled_events']);
        $this->assertArrayHasKey(CronManager::HOOK_CLEANUP, $GLOBALS['wp_scheduled_events']);
        $this->assertArrayHasKey(CronManager::HOOK_CONTINUE, $GLOBALS['wp_scheduled_events']);
    }

    public function testBootRegistersAdminDashboard(): void
    {
        $this->provider->register($this->container);

        $dashboard = $this->container->make(AdminDashboard::class);
        $dashboard->register();

        $this->assertArrayHasKey('admin_menu', $GLOBALS['wp_actions']);
        $this->assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['wp_actions']);
    }
}
