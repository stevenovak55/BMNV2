<?php

declare(strict_types=1);

namespace BMN\Users\Provider;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Email\EmailService;
use BMN\Users\Api\Controllers\AuthController;
use BMN\Users\Api\Controllers\FavoriteController;
use BMN\Users\Api\Controllers\SavedSearchController;
use BMN\Users\Api\Controllers\UserController;
use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Repository\PasswordResetRepository;
use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Repository\TokenRevocationRepository;
use BMN\Users\Service\FavoriteService;
use BMN\Users\Service\SavedSearchService;
use BMN\Users\Service\UserAuthService;
use BMN\Users\Service\UserProfileService;

/**
 * Service provider for the BMN Users plugin.
 *
 * Follows the PropertiesServiceProvider pattern exactly:
 *   register() — bind all services as singletons
 *   boot()     — register REST routes and cleanup cron
 */
final class UsersServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Resolve platform services.
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authService = $container->make(AuthService::class);
        $authMiddleware = $container->make(AuthMiddleware::class);
        $emailService = $container->make(EmailService::class);

        // Repositories.
        $container->singleton(FavoriteRepository::class, static fn (): FavoriteRepository => new FavoriteRepository($wpdb));

        $container->singleton(SavedSearchRepository::class, static fn (): SavedSearchRepository => new SavedSearchRepository($wpdb));

        $container->singleton(TokenRevocationRepository::class, static fn (): TokenRevocationRepository => new TokenRevocationRepository($wpdb));

        $container->singleton(PasswordResetRepository::class, static fn (): PasswordResetRepository => new PasswordResetRepository($wpdb));

        // Services.
        $container->singleton(UserAuthService::class, static fn (Container $c): UserAuthService => new UserAuthService(
            $authService,
            $emailService,
            $c->make(TokenRevocationRepository::class),
            $c->make(PasswordResetRepository::class),
        ));

        $container->singleton(FavoriteService::class, static fn (Container $c): FavoriteService => new FavoriteService(
            $c->make(FavoriteRepository::class),
        ));

        $container->singleton(SavedSearchService::class, static fn (Container $c): SavedSearchService => new SavedSearchService(
            $c->make(SavedSearchRepository::class),
        ));

        $container->singleton(UserProfileService::class, static fn (): UserProfileService => new UserProfileService());

        // Controllers.
        $container->singleton(AuthController::class, static fn (Container $c): AuthController => new AuthController(
            $c->make(UserAuthService::class),
            $c->make(FavoriteRepository::class),
            $c->make(SavedSearchRepository::class),
            $authMiddleware,
        ));

        $container->singleton(FavoriteController::class, static fn (Container $c): FavoriteController => new FavoriteController(
            $c->make(FavoriteService::class),
            $authMiddleware,
        ));

        $container->singleton(SavedSearchController::class, static fn (Container $c): SavedSearchController => new SavedSearchController(
            $c->make(SavedSearchService::class),
            $authMiddleware,
        ));

        $container->singleton(UserController::class, static fn (Container $c): UserController => new UserController(
            $c->make(UserProfileService::class),
            $authMiddleware,
        ));
    }

    public function boot(Container $container): void
    {
        // Register REST routes.
        add_action('rest_api_init', static function () use ($container): void {
            $container->make(AuthController::class)->registerRoutes();
            $container->make(FavoriteController::class)->registerRoutes();
            $container->make(SavedSearchController::class)->registerRoutes();
            $container->make(UserController::class)->registerRoutes();
        });

        // Check token revocation on every authenticated request.
        add_filter('bmn_is_token_revoked', static function (bool $revoked, string $token) use ($container): bool {
            if ($revoked) {
                return true;
            }

            $tokenHash = hash('sha256', $token);

            return $container->make(TokenRevocationRepository::class)->isRevoked($tokenHash);
        }, 10, 2);

        // Cleanup expired revoked tokens and password resets on daily cron.
        add_action('bmn_daily_cleanup', static function () use ($container): void {
            $container->make(TokenRevocationRepository::class)->cleanupExpired();
            $container->make(PasswordResetRepository::class)->cleanupExpired();
        });
    }
}
