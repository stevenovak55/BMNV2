<?php

declare(strict_types=1);

namespace BMN\Schools\Provider;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Schools\Api\Controllers\SchoolController;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolDataService;
use BMN\Schools\Service\SchoolFilterService;
use BMN\Schools\Service\SchoolRankingService;

/**
 * Service provider for the BMN Schools plugin.
 *
 * Follows the UsersServiceProvider pattern:
 *   register() — bind all services as singletons
 *   boot()     — register REST routes and hooks
 */
final class SchoolsServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Resolve platform services.
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $geocoding = $container->make(GeocodingService::class);
        $cache = $container->make(CacheService::class);

        // Repositories.
        $container->singleton(
            SchoolRepository::class,
            static fn (): SchoolRepository => new SchoolRepository($wpdb, $geocoding)
        );

        $container->singleton(
            SchoolDistrictRepository::class,
            static fn (): SchoolDistrictRepository => new SchoolDistrictRepository($wpdb, $geocoding)
        );

        $container->singleton(
            SchoolDataRepository::class,
            static fn (): SchoolDataRepository => new SchoolDataRepository($wpdb)
        );

        $container->singleton(
            SchoolRankingRepository::class,
            static fn (): SchoolRankingRepository => new SchoolRankingRepository($wpdb)
        );

        // Services.
        $container->singleton(
            SchoolRankingService::class,
            static fn (Container $c): SchoolRankingService => new SchoolRankingService(
                $c->make(SchoolDataRepository::class),
                $c->make(SchoolRankingRepository::class),
                $c->make(SchoolRepository::class),
                $c->make(SchoolDistrictRepository::class),
                $cache,
            )
        );

        $container->singleton(
            SchoolDataService::class,
            static fn (Container $c): SchoolDataService => new SchoolDataService(
                $c->make(SchoolRepository::class),
                $c->make(SchoolDistrictRepository::class),
                $c->make(SchoolDataRepository::class),
                $c->make(SchoolRankingService::class),
                $db,
            )
        );

        $container->singleton(
            SchoolFilterService::class,
            static fn (Container $c): SchoolFilterService => new SchoolFilterService(
                $c->make(SchoolRepository::class),
                $c->make(SchoolDistrictRepository::class),
                $c->make(SchoolRankingRepository::class),
                $geocoding,
                $cache,
            )
        );

        // Controller.
        $container->singleton(
            SchoolController::class,
            static fn (Container $c): SchoolController => new SchoolController(
                $c->make(SchoolRepository::class),
                $c->make(SchoolDistrictRepository::class),
                $c->make(SchoolRankingRepository::class),
                $c->make(SchoolDataRepository::class),
                $c->make(SchoolRankingService::class),
                $geocoding,
                $cache,
            )
        );
    }

    public function boot(Container $container): void
    {
        // Register REST routes.
        add_action('rest_api_init', static function () use ($container): void {
            $container->make(SchoolController::class)->registerRoutes();
        });

        // Register the school filter hook for PropertySearchService.
        add_filter('bmn_filter_by_school', static function (array $properties, array $criteria) use ($container): array {
            return $container->make(SchoolFilterService::class)->filter($properties, $criteria);
        }, 10, 2);

        // Action to trigger recalculation of all rankings.
        add_action('bmn_schools_recalculate', static function () use ($container): void {
            $container->make(SchoolRankingService::class)->calculateAllRankings();
        });
    }
}
