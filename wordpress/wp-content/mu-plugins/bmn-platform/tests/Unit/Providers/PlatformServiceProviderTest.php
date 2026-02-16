<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Providers;

use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use BMN\Platform\Auth\JwtAuthService;
use BMN\Platform\Cache\CacheService;
use BMN\Platform\Cache\TransientCacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Email\EmailService;
use BMN\Platform\Email\WpEmailService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Platform\Geocoding\SpatialService;
use BMN\Platform\Logging\LoggingService;
use BMN\Platform\Providers\PlatformServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PlatformServiceProvider.
 *
 * Verifies that all Phase 1 services are properly registered in the
 * DI container as singletons and that concrete implementations can
 * be resolved by their interface bindings.
 */
class PlatformServiceProviderTest extends TestCase
{
    private Container $container;

    public static function setUpBeforeClass(): void
    {
        if (!defined('BMN_JWT_SECRET')) {
            define('BMN_JWT_SECRET', 'test-secret-for-provider-testing');
        }
    }

    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new \wpdb();

        $this->container = new Container();
        $provider = new PlatformServiceProvider();
        $provider->register($this->container);
    }

    // ------------------------------------------------------------------
    // 1. LoggingService is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsLoggingService(): void
    {
        $this->assertTrue(
            $this->container->has(LoggingService::class),
            'PlatformServiceProvider should bind LoggingService.'
        );

        $service = $this->container->make(LoggingService::class);
        $this->assertInstanceOf(LoggingService::class, $service);
    }

    // ------------------------------------------------------------------
    // 2. CacheService is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsCacheService(): void
    {
        $this->assertTrue(
            $this->container->has(CacheService::class),
            'PlatformServiceProvider should bind CacheService.'
        );

        $service = $this->container->make(CacheService::class);
        $this->assertInstanceOf(CacheService::class, $service);
    }

    // ------------------------------------------------------------------
    // 3. AuthService is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsAuthService(): void
    {
        $this->assertTrue(
            $this->container->has(AuthService::class),
            'PlatformServiceProvider should bind AuthService.'
        );

        $service = $this->container->make(AuthService::class);
        $this->assertInstanceOf(AuthService::class, $service);
    }

    // ------------------------------------------------------------------
    // 4. AuthMiddleware is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsAuthMiddleware(): void
    {
        $this->assertTrue(
            $this->container->has(AuthMiddleware::class),
            'PlatformServiceProvider should bind AuthMiddleware.'
        );

        $service = $this->container->make(AuthMiddleware::class);
        $this->assertInstanceOf(AuthMiddleware::class, $service);
    }

    // ------------------------------------------------------------------
    // 5. DatabaseService is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsDatabaseService(): void
    {
        $this->assertTrue(
            $this->container->has(DatabaseService::class),
            'PlatformServiceProvider should bind DatabaseService.'
        );

        $service = $this->container->make(DatabaseService::class);
        $this->assertInstanceOf(DatabaseService::class, $service);
    }

    // ------------------------------------------------------------------
    // 6. EmailService is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsEmailService(): void
    {
        $this->assertTrue(
            $this->container->has(EmailService::class),
            'PlatformServiceProvider should bind EmailService.'
        );

        $service = $this->container->make(EmailService::class);
        $this->assertInstanceOf(EmailService::class, $service);
    }

    // ------------------------------------------------------------------
    // 7. GeocodingService is registered
    // ------------------------------------------------------------------

    public function testRegisterBindsGeocodingService(): void
    {
        $this->assertTrue(
            $this->container->has(GeocodingService::class),
            'PlatformServiceProvider should bind GeocodingService.'
        );

        $service = $this->container->make(GeocodingService::class);
        $this->assertInstanceOf(GeocodingService::class, $service);
    }

    // ------------------------------------------------------------------
    // 8. Services are singletons (resolve twice, assertSame)
    // ------------------------------------------------------------------

    public function testServicesSingleton(): void
    {
        $first = $this->container->make(LoggingService::class);
        $second = $this->container->make(LoggingService::class);
        $this->assertSame($first, $second, 'LoggingService should be a singleton.');

        $first = $this->container->make(CacheService::class);
        $second = $this->container->make(CacheService::class);
        $this->assertSame($first, $second, 'CacheService should be a singleton.');

        $first = $this->container->make(AuthService::class);
        $second = $this->container->make(AuthService::class);
        $this->assertSame($first, $second, 'AuthService should be a singleton.');

        $first = $this->container->make(DatabaseService::class);
        $second = $this->container->make(DatabaseService::class);
        $this->assertSame($first, $second, 'DatabaseService should be a singleton.');

        $first = $this->container->make(EmailService::class);
        $second = $this->container->make(EmailService::class);
        $this->assertSame($first, $second, 'EmailService should be a singleton.');

        $first = $this->container->make(GeocodingService::class);
        $second = $this->container->make(GeocodingService::class);
        $this->assertSame($first, $second, 'GeocodingService should be a singleton.');
    }

    // ------------------------------------------------------------------
    // 9. Concrete classes are resolvable via their own bindings
    // ------------------------------------------------------------------

    public function testConcreteClassesResolvable(): void
    {
        $transientCache = $this->container->make(TransientCacheService::class);
        $this->assertInstanceOf(TransientCacheService::class, $transientCache, 'TransientCacheService should be resolvable.');

        $jwtAuth = $this->container->make(JwtAuthService::class);
        $this->assertInstanceOf(JwtAuthService::class, $jwtAuth, 'JwtAuthService should be resolvable.');

        $wpEmail = $this->container->make(WpEmailService::class);
        $this->assertInstanceOf(WpEmailService::class, $wpEmail, 'WpEmailService should be resolvable.');

        $spatialService = $this->container->make(SpatialService::class);
        $this->assertInstanceOf(SpatialService::class, $spatialService, 'SpatialService should be resolvable.');
    }
}
