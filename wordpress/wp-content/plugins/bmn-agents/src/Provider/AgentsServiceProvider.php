<?php

declare(strict_types=1);

namespace BMN\Agents\Provider;

use BMN\Agents\Api\Controllers\ActivityController;
use BMN\Agents\Api\Controllers\AgentController;
use BMN\Agents\Api\Controllers\ReferralController;
use BMN\Agents\Api\Controllers\RelationshipController;
use BMN\Agents\Api\Controllers\SharedPropertyController;
use BMN\Agents\Repository\ActivityLogRepository;
use BMN\Agents\Repository\AgentProfileRepository;
use BMN\Agents\Repository\AgentReadRepository;
use BMN\Agents\Repository\OfficeReadRepository;
use BMN\Agents\Repository\ReferralCodeRepository;
use BMN\Agents\Repository\ReferralSignupRepository;
use BMN\Agents\Repository\RelationshipRepository;
use BMN\Agents\Repository\SharedPropertyRepository;
use BMN\Agents\Service\ActivityService;
use BMN\Agents\Service\AgentProfileService;
use BMN\Agents\Service\ReferralService;
use BMN\Agents\Service\RelationshipService;
use BMN\Agents\Service\SharedPropertyService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Core\Container;
use BMN\Platform\Core\ServiceProvider;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\MigrationRunner;

/**
 * Service provider for the BMN Agents plugin.
 *
 * Follows the AppointmentsServiceProvider pattern:
 *   register() — bind all services as singletons
 *   boot()     — register REST routes
 */
final class AgentsServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $wpdb = $db->getWpdb();
        $authMiddleware = $container->make(AuthMiddleware::class);

        // Read-only repositories (extractor tables).
        $container->singleton(AgentReadRepository::class, static fn (): AgentReadRepository => new AgentReadRepository($wpdb));

        $container->singleton(OfficeReadRepository::class, static fn (): OfficeReadRepository => new OfficeReadRepository($wpdb));

        // Agents plugin repositories.
        $container->singleton(AgentProfileRepository::class, static fn (): AgentProfileRepository => new AgentProfileRepository($wpdb));

        $container->singleton(RelationshipRepository::class, static fn (): RelationshipRepository => new RelationshipRepository($wpdb));

        $container->singleton(SharedPropertyRepository::class, static fn (): SharedPropertyRepository => new SharedPropertyRepository($wpdb));

        $container->singleton(ReferralCodeRepository::class, static fn (): ReferralCodeRepository => new ReferralCodeRepository($wpdb));

        $container->singleton(ReferralSignupRepository::class, static fn (): ReferralSignupRepository => new ReferralSignupRepository($wpdb));

        $container->singleton(ActivityLogRepository::class, static fn (): ActivityLogRepository => new ActivityLogRepository($wpdb));

        // Services.
        $container->singleton(AgentProfileService::class, static fn (Container $c): AgentProfileService => new AgentProfileService(
            $c->make(AgentReadRepository::class),
            $c->make(OfficeReadRepository::class),
            $c->make(AgentProfileRepository::class),
        ));

        $container->singleton(RelationshipService::class, static fn (Container $c): RelationshipService => new RelationshipService(
            $c->make(RelationshipRepository::class),
        ));

        $container->singleton(SharedPropertyService::class, static fn (Container $c): SharedPropertyService => new SharedPropertyService(
            $c->make(SharedPropertyRepository::class),
        ));

        $container->singleton(ReferralService::class, static fn (Container $c): ReferralService => new ReferralService(
            $c->make(ReferralCodeRepository::class),
            $c->make(ReferralSignupRepository::class),
        ));

        $container->singleton(ActivityService::class, static fn (Container $c): ActivityService => new ActivityService(
            $c->make(ActivityLogRepository::class),
            $c->make(RelationshipRepository::class),
        ));

        // Controllers.
        $container->singleton(AgentController::class, static fn (Container $c): AgentController => new AgentController(
            $c->make(AgentProfileService::class),
            $authMiddleware,
        ));

        $container->singleton(RelationshipController::class, static fn (Container $c): RelationshipController => new RelationshipController(
            $c->make(RelationshipService::class),
            $authMiddleware,
        ));

        $container->singleton(SharedPropertyController::class, static fn (Container $c): SharedPropertyController => new SharedPropertyController(
            $c->make(SharedPropertyService::class),
            $authMiddleware,
        ));

        $container->singleton(ReferralController::class, static fn (Container $c): ReferralController => new ReferralController(
            $c->make(ReferralService::class),
            $authMiddleware,
        ));

        $container->singleton(ActivityController::class, static fn (Container $c): ActivityController => new ActivityController(
            $c->make(ActivityService::class),
            $authMiddleware,
        ));
    }

    public function boot(Container $container): void
    {
        // Run migrations.
        $this->runMigrations($container);

        // Register REST routes.
        add_action('rest_api_init', static function () use ($container): void {
            $container->make(AgentController::class)->registerRoutes();
            $container->make(RelationshipController::class)->registerRoutes();
            $container->make(SharedPropertyController::class)->registerRoutes();
            $container->make(ReferralController::class)->registerRoutes();
            $container->make(ActivityController::class)->registerRoutes();
        });
    }

    private function runMigrations(Container $container): void
    {
        $db = $container->make(DatabaseService::class);
        $runner = new MigrationRunner($db->getWpdb());

        $migrations = [
            new \BMN\Agents\Migration\CreateAgentProfilesTable(),
            new \BMN\Agents\Migration\CreateRelationshipsTable(),
            new \BMN\Agents\Migration\CreateSharedPropertiesTable(),
            new \BMN\Agents\Migration\CreateReferralCodesTable(),
            new \BMN\Agents\Migration\CreateReferralSignupsTable(),
            new \BMN\Agents\Migration\CreateActivityLogTable(),
        ];

        $runner->run($migrations);
    }
}
