<?php

declare(strict_types=1);

namespace BMN\Exclusive\Provider;

use BMN\Exclusive\Api\Controllers\ListingController;
use BMN\Exclusive\Api\Controllers\PhotoController;
use BMN\Exclusive\Migration\CreateExclusiveListingsTable;
use BMN\Exclusive\Migration\CreateExclusivePhotosTable;
use BMN\Exclusive\Repository\ExclusiveListingRepository;
use BMN\Exclusive\Repository\ExclusivePhotoRepository;
use BMN\Exclusive\Service\ListingService;
use BMN\Exclusive\Service\PhotoService;
use BMN\Exclusive\Service\ValidationService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\MigrationRunner;

/**
 * Service provider for the BMN Exclusive plugin.
 *
 * Registers all repositories, services, and controllers as singletons.
 */
final class ExclusiveServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authMiddleware = $container->make(AuthMiddleware::class);

        // Repositories.
        $container->singleton(
            ExclusiveListingRepository::class,
            static fn (): ExclusiveListingRepository => new ExclusiveListingRepository($wpdb)
        );

        $container->singleton(
            ExclusivePhotoRepository::class,
            static fn (): ExclusivePhotoRepository => new ExclusivePhotoRepository($wpdb)
        );

        // Services.
        $container->singleton(
            ValidationService::class,
            static fn (): ValidationService => new ValidationService()
        );

        $container->singleton(
            ListingService::class,
            static fn (Container $c): ListingService => new ListingService(
                $c->make(ExclusiveListingRepository::class),
                $c->make(ExclusivePhotoRepository::class),
                $c->make(ValidationService::class),
            )
        );

        $container->singleton(
            PhotoService::class,
            static fn (Container $c): PhotoService => new PhotoService(
                $c->make(ExclusiveListingRepository::class),
                $c->make(ExclusivePhotoRepository::class),
            )
        );

        // Controllers.
        $container->singleton(
            ListingController::class,
            static fn (Container $c): ListingController => new ListingController(
                $c->make(ListingService::class),
                $c->make(ValidationService::class),
                $authMiddleware,
            )
        );

        $container->singleton(
            PhotoController::class,
            static fn (Container $c): PhotoController => new PhotoController(
                $c->make(PhotoService::class),
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
            $container->make(ListingController::class)->registerRoutes();
            $container->make(PhotoController::class)->registerRoutes();
        });
    }

    private function runMigrations(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $runner = new MigrationRunner($db->getWpdb());

        $migrations = [
            new CreateExclusiveListingsTable(),
            new CreateExclusivePhotosTable(),
        ];

        $runner->run($migrations);
    }
}
