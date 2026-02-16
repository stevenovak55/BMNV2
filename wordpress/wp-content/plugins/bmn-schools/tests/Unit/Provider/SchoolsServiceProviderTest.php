<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Provider;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Cache\TransientCacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Platform\Geocoding\SpatialService;
use BMN\Schools\Api\Controllers\SchoolController;
use BMN\Schools\Provider\SchoolsServiceProvider;
use BMN\Schools\Repository\SchoolDataRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolDataService;
use BMN\Schools\Service\SchoolFilterService;
use BMN\Schools\Service\SchoolRankingService;
use PHPUnit\Framework\TestCase;

final class SchoolsServiceProviderTest extends TestCase
{
    private Container $container;
    private SchoolsServiceProvider $provider;

    protected function setUp(): void
    {
        $GLOBALS['wp_actions'] = [];
        $GLOBALS['wp_filters'] = [];
        $GLOBALS['wp_rest_routes'] = [];

        $this->container = new Container();

        // Register platform dependencies.
        $wpdb = new \wpdb();
        $this->container->singleton(DatabaseService::class, static fn () => new DatabaseService($wpdb));
        $this->container->singleton(GeocodingService::class, static fn () => new SpatialService());
        $this->container->singleton(CacheService::class, static fn () => new TransientCacheService());

        $this->provider = new SchoolsServiceProvider();
    }

    // ------------------------------------------------------------------
    // Container bindings
    // ------------------------------------------------------------------

    public function testRegistersSchoolRepository(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolRepository::class));
    }

    public function testRegistersSchoolDistrictRepository(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolDistrictRepository::class));
    }

    public function testRegistersSchoolDataRepository(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolDataRepository::class));
    }

    public function testRegistersSchoolRankingRepository(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolRankingRepository::class));
    }

    public function testRegistersSchoolRankingService(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolRankingService::class));
    }

    public function testRegistersSchoolDataService(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolDataService::class));
    }

    public function testRegistersSchoolFilterService(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolFilterService::class));
    }

    public function testRegistersSchoolController(): void
    {
        $this->provider->register($this->container);
        $this->assertTrue($this->container->has(SchoolController::class));
    }

    // ------------------------------------------------------------------
    // Boot lifecycle
    // ------------------------------------------------------------------

    public function testBootRegistersRestApiInitAction(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('rest_api_init', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['rest_api_init']);
    }

    public function testBootRegistersSchoolFilterHook(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('bmn_filter_by_school', $GLOBALS['wp_filters']);
        $this->assertNotEmpty($GLOBALS['wp_filters']['bmn_filter_by_school']);
    }

    public function testBootRegistersRecalculateAction(): void
    {
        $this->provider->register($this->container);
        $this->provider->boot($this->container);

        $this->assertArrayHasKey('bmn_schools_recalculate', $GLOBALS['wp_actions']);
        $this->assertNotEmpty($GLOBALS['wp_actions']['bmn_schools_recalculate']);
    }

    // ------------------------------------------------------------------
    // Singleton behavior
    // ------------------------------------------------------------------

    public function testServicesAreSingletons(): void
    {
        $this->provider->register($this->container);

        $repo1 = $this->container->make(SchoolRepository::class);
        $repo2 = $this->container->make(SchoolRepository::class);

        $this->assertSame($repo1, $repo2);
    }
}
