<?php

declare(strict_types=1);

namespace BMN\Platform\Providers;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Auth\JwtAuthService;
use BMN\Platform\Cache\CacheService;
use BMN\Platform\Cache\TransientCacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Email\EmailService;
use BMN\Platform\Email\WpEmailService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Platform\Geocoding\SpatialService;
use BMN\Platform\Logging\LoggingService;

/**
 * Core platform service provider.
 *
 * Registers all Phase 1 shared services into the DI container as
 * singletons. Services are wired with their dependencies so that
 * any plugin can resolve them by interface.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Logging (no dependencies)
        $container->singleton(LoggingService::class, fn (): LoggingService => new LoggingService());

        // Cache (no dependencies)
        $container->singleton(CacheService::class, fn (): TransientCacheService => new TransientCacheService());
        $container->singleton(TransientCacheService::class, fn (Container $c): TransientCacheService => $c->make(CacheService::class));

        // Auth
        $container->singleton(AuthService::class, fn (): JwtAuthService => new JwtAuthService());
        $container->singleton(JwtAuthService::class, fn (Container $c): JwtAuthService => $c->make(AuthService::class));

        // Auth middleware (depends on AuthService)
        $container->singleton(AuthMiddleware::class, fn (Container $c): AuthMiddleware => new AuthMiddleware(
            $c->make(AuthService::class),
        ));

        // Database (depends on global $wpdb)
        $container->singleton(DatabaseService::class, function (): DatabaseService {
            global $wpdb;
            return new DatabaseService($wpdb);
        });

        // Email (no constructor dependencies)
        $container->singleton(EmailService::class, fn (): WpEmailService => new WpEmailService());
        $container->singleton(WpEmailService::class, fn (Container $c): WpEmailService => $c->make(EmailService::class));

        // Geocoding (optional CacheService dependency)
        $container->singleton(GeocodingService::class, fn (Container $c): SpatialService => new SpatialService(
            $c->make(CacheService::class),
        ));
        $container->singleton(SpatialService::class, fn (Container $c): SpatialService => $c->make(GeocodingService::class));
    }

    public function boot(Container $container): void
    {
        // No boot-time actions needed for Phase 1 services.
    }
}
