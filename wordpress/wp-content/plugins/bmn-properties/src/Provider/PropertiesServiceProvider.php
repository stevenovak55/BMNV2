<?php

declare(strict_types=1);

namespace BMN\Properties\Provider;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Properties\Api\Controllers\PropertyController;
use BMN\Properties\Repository\PropertySearchRepository;
use BMN\Properties\Service\AutocompleteService;
use BMN\Properties\Service\Filter\FilterBuilder;
use BMN\Properties\Service\Filter\SortResolver;
use BMN\Properties\Service\Filter\StatusResolver;
use BMN\Properties\Service\PropertyDetailService;
use BMN\Properties\Service\PropertySearchService;

/**
 * Service provider for the BMN Properties plugin.
 *
 * Follows the ExtractorServiceProvider pattern exactly:
 *   register() — bind all services as singletons
 *   boot()     — register REST routes and cache invalidation
 */
final class PropertiesServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Resolve platform services.
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $cache = $container->make(CacheService::class);
        $geocoding = $container->make(GeocodingService::class);

        // Filter system.
        $container->singleton(StatusResolver::class, static fn (): StatusResolver => new StatusResolver());

        $container->singleton(SortResolver::class, static fn (): SortResolver => new SortResolver());

        $container->singleton(FilterBuilder::class, static fn (Container $c): FilterBuilder => new FilterBuilder(
            $wpdb,
            $geocoding,
            $c->make(StatusResolver::class),
            $c->make(SortResolver::class),
        ));

        // Repository.
        $container->singleton(PropertySearchRepository::class, static fn (): PropertySearchRepository => new PropertySearchRepository($wpdb));

        // Services.
        $container->singleton(PropertySearchService::class, static fn (Container $c): PropertySearchService => new PropertySearchService(
            $c->make(PropertySearchRepository::class),
            $c->make(FilterBuilder::class),
            $cache,
        ));

        $container->singleton(PropertyDetailService::class, static fn (Container $c): PropertyDetailService => new PropertyDetailService(
            $c->make(PropertySearchRepository::class),
            $cache,
        ));

        $container->singleton(AutocompleteService::class, static fn (Container $c): AutocompleteService => new AutocompleteService(
            $c->make(PropertySearchRepository::class),
            $cache,
        ));

        // Controller.
        $container->singleton(PropertyController::class, static fn (Container $c): PropertyController => new PropertyController(
            $c->make(PropertySearchService::class),
            $c->make(PropertyDetailService::class),
            $c->make(AutocompleteService::class),
        ));
    }

    public function boot(Container $container): void
    {
        // Register REST routes.
        add_action('rest_api_init', static function () use ($container): void {
            $controller = $container->make(PropertyController::class);
            $controller->registerRoutes();
        });

        // Invalidate property caches when extraction completes.
        add_action('bmn_extraction_completed', static function () use ($container): void {
            $cache = $container->make(CacheService::class);
            $cache->invalidateGroup('property_search');
            $cache->invalidateGroup('property_detail');
            $cache->invalidateGroup('autocomplete');
        });
    }
}
