<?php

declare(strict_types=1);

namespace BMN\CMA\Provider;

use BMN\CMA\Controller\CmaController;
use BMN\CMA\Controller\MarketController;
use BMN\CMA\Repository\CmaReportRepository;
use BMN\CMA\Repository\ComparableRepository;
use BMN\CMA\Repository\MarketSnapshotRepository;
use BMN\CMA\Repository\ValueHistoryRepository;
use BMN\CMA\Service\AdjustmentService;
use BMN\CMA\Service\CmaReportService;
use BMN\CMA\Service\ComparableSearchService;
use BMN\CMA\Service\MarketConditionsService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\MigrationRunner;

/**
 * Service provider for the BMN CMA plugin.
 *
 * Registers all repositories, services, and controllers as singletons.
 */
final class CmaServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authMiddleware = $container->make(AuthMiddleware::class);

        // Repositories.
        $container->singleton(
            CmaReportRepository::class,
            static fn (): CmaReportRepository => new CmaReportRepository($wpdb)
        );

        $container->singleton(
            ComparableRepository::class,
            static fn (): ComparableRepository => new ComparableRepository($wpdb)
        );

        $container->singleton(
            ValueHistoryRepository::class,
            static fn (): ValueHistoryRepository => new ValueHistoryRepository($wpdb)
        );

        $container->singleton(
            MarketSnapshotRepository::class,
            static fn (): MarketSnapshotRepository => new MarketSnapshotRepository($wpdb)
        );

        // Services.
        $container->singleton(
            ComparableSearchService::class,
            static fn (): ComparableSearchService => new ComparableSearchService($wpdb)
        );

        $container->singleton(
            AdjustmentService::class,
            static fn (): AdjustmentService => new AdjustmentService()
        );

        $container->singleton(
            CmaReportService::class,
            static fn (Container $c): CmaReportService => new CmaReportService(
                $c->make(CmaReportRepository::class),
                $c->make(ComparableRepository::class),
                $c->make(ValueHistoryRepository::class),
                $c->make(ComparableSearchService::class),
                $c->make(AdjustmentService::class),
            )
        );

        $container->singleton(
            MarketConditionsService::class,
            static fn (Container $c): MarketConditionsService => new MarketConditionsService(
                $c->make(MarketSnapshotRepository::class),
                $wpdb,
            )
        );

        // Controllers.
        $container->singleton(
            CmaController::class,
            static fn (Container $c): CmaController => new CmaController(
                $c->make(CmaReportService::class),
                $c->make(ComparableSearchService::class),
                $authMiddleware,
            )
        );

        $container->singleton(
            MarketController::class,
            static fn (Container $c): MarketController => new MarketController(
                $c->make(MarketConditionsService::class),
                $authMiddleware,
            )
        );
    }

    public function boot(Container $container): void
    {
        // Run migrations.
        $this->runMigrations($container);

        // Register REST routes.
        add_action('rest_api_init', static function () use ($container): void {
            $container->make(CmaController::class)->registerRoutes();
            $container->make(MarketController::class)->registerRoutes();
        });
    }

    private function runMigrations(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $runner = new MigrationRunner($db->getWpdb());

        $migrations = [
            new \BMN\CMA\Migration\CreateCmaReportsTable(),
            new \BMN\CMA\Migration\CreateComparablesTable(),
            new \BMN\CMA\Migration\CreateValueHistoryTable(),
            new \BMN\CMA\Migration\CreateMarketSnapshotsTable(),
        ];

        $runner->run($migrations);
    }
}
