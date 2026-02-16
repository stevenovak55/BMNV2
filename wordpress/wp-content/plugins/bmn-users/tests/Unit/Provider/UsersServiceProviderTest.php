<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Provider;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Email\EmailService;
use BMN\Users\Api\Controllers\AuthController;
use BMN\Users\Api\Controllers\FavoriteController;
use BMN\Users\Api\Controllers\SavedSearchController;
use BMN\Users\Api\Controllers\UserController;
use BMN\Users\Provider\UsersServiceProvider;
use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Repository\PasswordResetRepository;
use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Repository\TokenRevocationRepository;
use BMN\Users\Service\FavoriteService;
use BMN\Users\Service\SavedSearchService;
use BMN\Users\Service\UserAuthService;
use BMN\Users\Service\UserProfileService;
use PHPUnit\Framework\TestCase;

final class UsersServiceProviderTest extends TestCase
{
    private Container $container;
    private UsersServiceProvider $provider;

    protected function setUp(): void
    {
        $this->container = new Container();

        // Register platform services that the provider depends on.
        $wpdb = new \wpdb();
        $db = new DatabaseService($wpdb);

        $this->container->instance(DatabaseService::class, $db);
        $authService = $this->createMock(AuthService::class);
        $this->container->instance(AuthService::class, $authService);
        $this->container->instance(AuthMiddleware::class, new AuthMiddleware($authService));
        $this->container->instance(EmailService::class, $this->createMock(EmailService::class));

        $this->provider = new UsersServiceProvider();
        $this->provider->register($this->container);
    }

    // ------------------------------------------------------------------
    // Repository registrations
    // ------------------------------------------------------------------

    public function testRegistersFavoriteRepository(): void
    {
        $this->assertTrue($this->container->has(FavoriteRepository::class));
        $this->assertInstanceOf(FavoriteRepository::class, $this->container->make(FavoriteRepository::class));
    }

    public function testRegistersSavedSearchRepository(): void
    {
        $this->assertTrue($this->container->has(SavedSearchRepository::class));
        $this->assertInstanceOf(SavedSearchRepository::class, $this->container->make(SavedSearchRepository::class));
    }

    public function testRegistersTokenRevocationRepository(): void
    {
        $this->assertTrue($this->container->has(TokenRevocationRepository::class));
        $this->assertInstanceOf(TokenRevocationRepository::class, $this->container->make(TokenRevocationRepository::class));
    }

    public function testRegistersPasswordResetRepository(): void
    {
        $this->assertTrue($this->container->has(PasswordResetRepository::class));
        $this->assertInstanceOf(PasswordResetRepository::class, $this->container->make(PasswordResetRepository::class));
    }

    // ------------------------------------------------------------------
    // Service registrations
    // ------------------------------------------------------------------

    public function testRegistersUserAuthService(): void
    {
        $this->assertTrue($this->container->has(UserAuthService::class));
        $this->assertInstanceOf(UserAuthService::class, $this->container->make(UserAuthService::class));
    }

    public function testRegistersFavoriteService(): void
    {
        $this->assertTrue($this->container->has(FavoriteService::class));
        $this->assertInstanceOf(FavoriteService::class, $this->container->make(FavoriteService::class));
    }

    public function testRegistersSavedSearchService(): void
    {
        $this->assertTrue($this->container->has(SavedSearchService::class));
        $this->assertInstanceOf(SavedSearchService::class, $this->container->make(SavedSearchService::class));
    }

    public function testRegistersUserProfileService(): void
    {
        $this->assertTrue($this->container->has(UserProfileService::class));
        $this->assertInstanceOf(UserProfileService::class, $this->container->make(UserProfileService::class));
    }

    // ------------------------------------------------------------------
    // Controller registrations
    // ------------------------------------------------------------------

    public function testRegistersAuthController(): void
    {
        $this->assertTrue($this->container->has(AuthController::class));
        $this->assertInstanceOf(AuthController::class, $this->container->make(AuthController::class));
    }

    public function testRegistersFavoriteController(): void
    {
        $this->assertTrue($this->container->has(FavoriteController::class));
        $this->assertInstanceOf(FavoriteController::class, $this->container->make(FavoriteController::class));
    }

    public function testRegistersSavedSearchController(): void
    {
        $this->assertTrue($this->container->has(SavedSearchController::class));
        $this->assertInstanceOf(SavedSearchController::class, $this->container->make(SavedSearchController::class));
    }

    public function testRegistersUserController(): void
    {
        $this->assertTrue($this->container->has(UserController::class));
        $this->assertInstanceOf(UserController::class, $this->container->make(UserController::class));
    }

    // ------------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------------

    public function testBootRegistersRestApiInitAction(): void
    {
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $hooks = array_keys($GLOBALS['wp_actions']);
        $this->assertContains('rest_api_init', $hooks);
    }

    public function testBootRegistersDailyCleanupAction(): void
    {
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $hooks = array_keys($GLOBALS['wp_actions']);
        $this->assertContains('bmn_daily_cleanup', $hooks);
    }

    public function testBootRegistersTokenRevocationFilter(): void
    {
        $GLOBALS['wp_filters'] = [];

        $this->provider->boot($this->container);

        $hooks = array_keys($GLOBALS['wp_filters']);
        $this->assertContains('bmn_is_token_revoked', $hooks);
    }
}
