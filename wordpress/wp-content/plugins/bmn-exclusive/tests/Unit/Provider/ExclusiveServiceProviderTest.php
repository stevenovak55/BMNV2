<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Provider;

use BMN\Exclusive\Api\Controllers\ListingController;
use BMN\Exclusive\Api\Controllers\PhotoController;
use BMN\Exclusive\Provider\ExclusiveServiceProvider;
use BMN\Exclusive\Repository\ExclusiveListingRepository;
use BMN\Exclusive\Repository\ExclusivePhotoRepository;
use BMN\Exclusive\Service\ListingService;
use BMN\Exclusive\Service\PhotoService;
use BMN\Exclusive\Service\ValidationService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use PHPUnit\Framework\TestCase;

final class ExclusiveServiceProviderTest extends TestCase
{
    private Container $container;
    private ExclusiveServiceProvider $provider;

    protected function setUp(): void
    {
        $this->container = new Container();

        // DatabaseService is final — use anonymous class stand-in.
        $wpdb = new \wpdb();
        $wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $wpdb;
        $dbService = new class($wpdb) {
            private \wpdb $wpdb;
            public function __construct(\wpdb $wpdb) { $this->wpdb = $wpdb; }
            public function getWpdb(): \wpdb { return $this->wpdb; }
        };
        $this->container->instance(DatabaseService::class, $dbService);

        // Mock AuthService (interface) and create real AuthMiddleware (final class).
        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);
        $this->container->instance(AuthMiddleware::class, $authMiddleware);

        $this->provider = new ExclusiveServiceProvider();
        $this->provider->register($this->container);
    }

    // -- register: bindings --

    public function testRegisterBindsAllRepositories(): void
    {
        $this->assertTrue($this->container->has(ExclusiveListingRepository::class));
        $this->assertTrue($this->container->has(ExclusivePhotoRepository::class));
    }

    public function testRegisterBindsAllServices(): void
    {
        $this->assertTrue($this->container->has(ValidationService::class));
        $this->assertTrue($this->container->has(ListingService::class));
        $this->assertTrue($this->container->has(PhotoService::class));
    }

    public function testRegisterBindsAllControllers(): void
    {
        $this->assertTrue($this->container->has(ListingController::class));
        $this->assertTrue($this->container->has(PhotoController::class));
    }

    // -- register: resolve --

    public function testResolveExclusiveListingRepository(): void
    {
        $repo = $this->container->make(ExclusiveListingRepository::class);
        $this->assertInstanceOf(ExclusiveListingRepository::class, $repo);
    }

    public function testResolveExclusivePhotoRepository(): void
    {
        $repo = $this->container->make(ExclusivePhotoRepository::class);
        $this->assertInstanceOf(ExclusivePhotoRepository::class, $repo);
    }

    public function testResolveValidationService(): void
    {
        $service = $this->container->make(ValidationService::class);
        $this->assertInstanceOf(ValidationService::class, $service);
    }

    public function testResolveListingService(): void
    {
        $service = $this->container->make(ListingService::class);
        $this->assertInstanceOf(ListingService::class, $service);
    }

    public function testResolvePhotoService(): void
    {
        $service = $this->container->make(PhotoService::class);
        $this->assertInstanceOf(PhotoService::class, $service);
    }

    public function testResolveListingController(): void
    {
        $controller = $this->container->make(ListingController::class);
        $this->assertInstanceOf(ListingController::class, $controller);
    }

    public function testResolvePhotoController(): void
    {
        $controller = $this->container->make(PhotoController::class);
        $this->assertInstanceOf(PhotoController::class, $controller);
    }

    // -- singletons --

    public function testSingletonsBehaveCorrectly(): void
    {
        $repo1 = $this->container->make(ExclusiveListingRepository::class);
        $repo2 = $this->container->make(ExclusiveListingRepository::class);

        $this->assertSame($repo1, $repo2);
    }

    // -- boot --

    public function testBootRegistersRestApiInitAction(): void
    {
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }
}
