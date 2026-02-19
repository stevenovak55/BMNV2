<?php

declare(strict_types=1);

namespace BMN\Extractor\Provider;

use BMN\Extractor\Admin\AdminDashboard;
use BMN\Extractor\Api\Controllers\ExtractionController;
use BMN\Extractor\Repository\AgentRepository;
use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\MediaRepository;
use BMN\Extractor\Repository\OfficeRepository;
use BMN\Extractor\Repository\OpenHouseRepository;
use BMN\Extractor\Repository\PropertyHistoryRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Repository\RoomRepository;
use BMN\Extractor\Service\BridgeApiClient;
use BMN\Extractor\Service\CronManager;
use BMN\Extractor\Service\DataNormalizer;
use BMN\Extractor\Service\ExtractionEngine;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\MigrationRunner;

/**
 * DI wiring for the BMN Extractor plugin.
 *
 * Registers all services, repositories, and controllers into the
 * platform DI container as singletons.
 */
final class ExtractorServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();

        // Services (no dependencies on other extractor services).
        $container->singleton(DataNormalizer::class, fn (): DataNormalizer => new DataNormalizer());

        $container->singleton(BridgeApiClient::class, fn (): BridgeApiClient => new BridgeApiClient());

        // Repositories.
        $container->singleton(PropertyRepository::class, fn () => new PropertyRepository($wpdb));
        $container->singleton(MediaRepository::class, fn () => new MediaRepository($wpdb));
        $container->singleton(AgentRepository::class, fn () => new AgentRepository($wpdb));
        $container->singleton(OfficeRepository::class, fn () => new OfficeRepository($wpdb));
        $container->singleton(OpenHouseRepository::class, fn () => new OpenHouseRepository($wpdb));
        $container->singleton(ExtractionRepository::class, fn () => new ExtractionRepository($wpdb));
        $container->singleton(PropertyHistoryRepository::class, fn () => new PropertyHistoryRepository($wpdb));
        $container->singleton(RoomRepository::class, fn () => new RoomRepository($wpdb));

        // Extraction Engine (depends on all repositories + services).
        $container->singleton(ExtractionEngine::class, fn (Container $c): ExtractionEngine => new ExtractionEngine(
            $wpdb,
            $c->make(BridgeApiClient::class),
            $c->make(DataNormalizer::class),
            $c->make(PropertyRepository::class),
            $c->make(MediaRepository::class),
            $c->make(AgentRepository::class),
            $c->make(OfficeRepository::class),
            $c->make(OpenHouseRepository::class),
            $c->make(ExtractionRepository::class),
            $c->make(PropertyHistoryRepository::class),
            $c->make(RoomRepository::class),
        ));

        // Cron Manager.
        $container->singleton(CronManager::class, fn (Container $c): CronManager => new CronManager(
            $c->make(ExtractionEngine::class),
        ));

        // REST Controller.
        $container->singleton(ExtractionController::class, fn (Container $c): ExtractionController => new ExtractionController(
            $c->make(ExtractionEngine::class),
            $c->make(ExtractionRepository::class),
            $c->make(PropertyRepository::class),
        ));

        // Admin Dashboard.
        $container->singleton(AdminDashboard::class, fn (Container $c): AdminDashboard => new AdminDashboard(
            $c->make(ExtractionEngine::class),
            $c->make(ExtractionRepository::class),
            $c->make(PropertyRepository::class),
        ));
    }

    public function boot(Container $container): void
    {
        // Run migrations.
        $this->runMigrations($container);

        // Register REST routes.
        add_action('rest_api_init', function () use ($container): void {
            $container->make(ExtractionController::class)->registerRoutes();
        });

        // Register cron schedules.
        $container->make(CronManager::class)->register();

        // Register admin dashboard.
        $container->make(AdminDashboard::class)->register();

        // Register cleanup handler.
        add_action('bmn_extraction_cleanup_run', function () use ($container): void {
            $container->make(OpenHouseRepository::class)->cleanupExpired(7);
            $container->make(ExtractionRepository::class)->cleanupOld(30);
        });
    }

    /**
     * Run pending database migrations.
     */
    private function runMigrations(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $runner = new MigrationRunner($db->getWpdb());

        $migrations = [
            new \BMN\Extractor\Migrations\CreatePropertiesTable(),
            new \BMN\Extractor\Migrations\CreateMediaTable(),
            new \BMN\Extractor\Migrations\CreateAgentsTable(),
            new \BMN\Extractor\Migrations\CreateOfficesTable(),
            new \BMN\Extractor\Migrations\CreateOpenHousesTable(),
            new \BMN\Extractor\Migrations\CreateExtractionsTable(),
            new \BMN\Extractor\Migrations\CreatePropertyHistoryTable(),
            new \BMN\Extractor\Migrations\AddPropertyDetailColumns(),
            new \BMN\Extractor\Migrations\AddComparisonFixes(),
            new \BMN\Extractor\Migrations\CreateRoomsTable(),
        ];

        $runner->run($migrations);
    }
}
