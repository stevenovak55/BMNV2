<?php

declare(strict_types=1);

namespace BMN\Flip\Provider;

use BMN\Flip\Controller\FlipController;
use BMN\Flip\Controller\ReportController;
use BMN\Flip\Migration\CreateFlipAnalysesTable;
use BMN\Flip\Migration\CreateFlipComparablesTable;
use BMN\Flip\Migration\CreateFlipReportsTable;
use BMN\Flip\Migration\CreateMonitorSeenTable;
use BMN\Flip\Repository\FlipAnalysisRepository;
use BMN\Flip\Repository\FlipComparableRepository;
use BMN\Flip\Repository\FlipReportRepository;
use BMN\Flip\Repository\MonitorSeenRepository;
use BMN\Flip\Service\ArvService;
use BMN\Flip\Service\FinancialService;
use BMN\Flip\Service\FlipAnalysisService;
use BMN\Flip\Service\ReportService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\MigrationRunner;

/**
 * Service provider for the BMN Flip plugin.
 *
 * Registers all repositories, services, and controllers as singletons.
 */
final class FlipServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authMiddleware = $container->make(AuthMiddleware::class);

        // Repositories.
        $container->singleton(
            FlipAnalysisRepository::class,
            static fn (): FlipAnalysisRepository => new FlipAnalysisRepository($wpdb)
        );

        $container->singleton(
            FlipComparableRepository::class,
            static fn (): FlipComparableRepository => new FlipComparableRepository($wpdb)
        );

        $container->singleton(
            FlipReportRepository::class,
            static fn (): FlipReportRepository => new FlipReportRepository($wpdb)
        );

        $container->singleton(
            MonitorSeenRepository::class,
            static fn (): MonitorSeenRepository => new MonitorSeenRepository($wpdb)
        );

        // Services.
        $container->singleton(
            ArvService::class,
            static fn (): ArvService => new ArvService($wpdb)
        );

        $container->singleton(
            FinancialService::class,
            static fn (): FinancialService => new FinancialService()
        );

        $container->singleton(
            FlipAnalysisService::class,
            static fn (Container $c): FlipAnalysisService => new FlipAnalysisService(
                $c->make(ArvService::class),
                $c->make(FinancialService::class),
                $c->make(FlipAnalysisRepository::class),
                $c->make(FlipComparableRepository::class),
            )
        );

        $container->singleton(
            ReportService::class,
            static fn (Container $c): ReportService => new ReportService(
                $c->make(FlipReportRepository::class),
                $c->make(FlipAnalysisRepository::class),
                $c->make(FlipComparableRepository::class),
                $c->make(MonitorSeenRepository::class),
            )
        );

        // Controllers.
        $container->singleton(
            FlipController::class,
            static fn (Container $c): FlipController => new FlipController(
                $c->make(FlipAnalysisService::class),
                $c->make(ArvService::class),
                $authMiddleware,
            )
        );

        $container->singleton(
            ReportController::class,
            static fn (Container $c): ReportController => new ReportController(
                $c->make(ReportService::class),
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
            $container->make(FlipController::class)->registerRoutes();
            $container->make(ReportController::class)->registerRoutes();
        });
    }

    private function runMigrations(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $runner = new MigrationRunner($db->getWpdb());

        $migrations = [
            new CreateFlipReportsTable(),
            new CreateFlipAnalysesTable(),
            new CreateFlipComparablesTable(),
            new CreateMonitorSeenTable(),
        ];

        $runner->run($migrations);
    }
}
