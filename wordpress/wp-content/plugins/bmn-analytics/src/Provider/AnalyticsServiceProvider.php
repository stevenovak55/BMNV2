<?php

declare(strict_types=1);

namespace BMN\Analytics\Provider;

use BMN\Analytics\Controller\ReportingController;
use BMN\Analytics\Controller\TrackingController;
use BMN\Analytics\Migration\CreateDailyAggregatesTable;
use BMN\Analytics\Migration\CreateEventsTable;
use BMN\Analytics\Migration\CreateSessionsTable;
use BMN\Analytics\Repository\DailyAggregateRepository;
use BMN\Analytics\Repository\EventRepository;
use BMN\Analytics\Repository\SessionRepository;
use BMN\Analytics\Service\ReportingService;
use BMN\Analytics\Service\TrackingService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\MigrationRunner;

/**
 * Service provider for the BMN Analytics plugin.
 *
 * Registers repositories, services, and controllers as singletons.
 * Boots migrations, REST routes, and daily aggregation cron.
 */
final class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Resolve platform services.
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authMiddleware = $container->make(AuthMiddleware::class);

        // Repositories.
        $container->singleton(
            EventRepository::class,
            static fn (): EventRepository => new EventRepository($wpdb)
        );

        $container->singleton(
            SessionRepository::class,
            static fn (): SessionRepository => new SessionRepository($wpdb)
        );

        $container->singleton(
            DailyAggregateRepository::class,
            static fn (): DailyAggregateRepository => new DailyAggregateRepository($wpdb)
        );

        // Services.
        $container->singleton(
            TrackingService::class,
            static fn (Container $c): TrackingService => new TrackingService(
                $c->make(EventRepository::class),
                $c->make(SessionRepository::class),
            )
        );

        $container->singleton(
            ReportingService::class,
            static fn (Container $c): ReportingService => new ReportingService(
                $c->make(EventRepository::class),
                $c->make(SessionRepository::class),
                $c->make(DailyAggregateRepository::class),
            )
        );

        // Controllers.
        $container->singleton(
            TrackingController::class,
            static fn (Container $c): TrackingController => new TrackingController(
                $c->make(TrackingService::class),
                $authMiddleware,
            )
        );

        $container->singleton(
            ReportingController::class,
            static fn (Container $c): ReportingController => new ReportingController(
                $c->make(ReportingService::class),
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
            $container->make(TrackingController::class)->registerRoutes();
            $container->make(ReportingController::class)->registerRoutes();
        });

        // Schedule daily aggregation cron.
        if (!wp_next_scheduled('bmn_analytics_daily_aggregate')) {
            wp_schedule_event(time(), 'daily', 'bmn_analytics_daily_aggregate');
        }

        add_action('bmn_analytics_daily_aggregate', static function () use ($container): void {
            $service = $container->make(ReportingService::class);
            $yesterday = gmdate('Y-m-d', strtotime('-1 day'));
            $service->aggregateDaily($yesterday);
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
            new CreateEventsTable(),
            new CreateSessionsTable(),
            new CreateDailyAggregatesTable(),
        ];

        $runner->run($migrations);
    }
}
