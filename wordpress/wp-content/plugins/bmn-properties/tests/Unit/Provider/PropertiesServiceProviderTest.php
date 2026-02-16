<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Provider;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Core\Container;
use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Properties\Api\Controllers\PropertyController;
use BMN\Properties\Provider\PropertiesServiceProvider;
use BMN\Properties\Repository\PropertySearchRepository;
use BMN\Properties\Service\AutocompleteService;
use BMN\Properties\Service\Filter\FilterBuilder;
use BMN\Properties\Service\Filter\SortResolver;
use BMN\Properties\Service\Filter\StatusResolver;
use BMN\Properties\Service\PropertyDetailService;
use BMN\Properties\Service\PropertySearchService;
use PHPUnit\Framework\TestCase;

final class PropertiesServiceProviderTest extends TestCase
{
    private Container $container;
    private PropertiesServiceProvider $provider;

    protected function setUp(): void
    {
        $this->container = new Container();

        // Register platform services that the provider depends on.
        // DatabaseService is final, so use a real instance with the stub wpdb.
        $wpdb = new \wpdb();
        $db = new DatabaseService($wpdb);

        $this->container->instance(DatabaseService::class, $db);
        $this->container->instance(CacheService::class, $this->createMock(CacheService::class));
        $this->container->instance(GeocodingService::class, $this->createMock(GeocodingService::class));

        $this->provider = new PropertiesServiceProvider();
        $this->provider->register($this->container);
    }

    public function testRegistersStatusResolver(): void
    {
        $this->assertTrue($this->container->has(StatusResolver::class));
        $this->assertInstanceOf(StatusResolver::class, $this->container->make(StatusResolver::class));
    }

    public function testRegistersSortResolver(): void
    {
        $this->assertTrue($this->container->has(SortResolver::class));
        $this->assertInstanceOf(SortResolver::class, $this->container->make(SortResolver::class));
    }

    public function testRegistersFilterBuilder(): void
    {
        $this->assertTrue($this->container->has(FilterBuilder::class));
        $this->assertInstanceOf(FilterBuilder::class, $this->container->make(FilterBuilder::class));
    }

    public function testRegistersPropertySearchRepository(): void
    {
        $this->assertTrue($this->container->has(PropertySearchRepository::class));
        $this->assertInstanceOf(PropertySearchRepository::class, $this->container->make(PropertySearchRepository::class));
    }

    public function testRegistersPropertySearchService(): void
    {
        $this->assertTrue($this->container->has(PropertySearchService::class));
        $this->assertInstanceOf(PropertySearchService::class, $this->container->make(PropertySearchService::class));
    }

    public function testRegistersPropertyDetailService(): void
    {
        $this->assertTrue($this->container->has(PropertyDetailService::class));
        $this->assertInstanceOf(PropertyDetailService::class, $this->container->make(PropertyDetailService::class));
    }

    public function testRegistersAutocompleteService(): void
    {
        $this->assertTrue($this->container->has(AutocompleteService::class));
        $this->assertInstanceOf(AutocompleteService::class, $this->container->make(AutocompleteService::class));
    }

    public function testRegistersPropertyController(): void
    {
        $this->assertTrue($this->container->has(PropertyController::class));
        $this->assertInstanceOf(PropertyController::class, $this->container->make(PropertyController::class));
    }

    public function testBootRegistersRestApiInitAction(): void
    {
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $hooks = array_keys($GLOBALS['wp_actions']);
        $this->assertContains('rest_api_init', $hooks);
    }

    public function testBootRegistersExtractionCompletedAction(): void
    {
        $GLOBALS['wp_actions'] = [];

        $this->provider->boot($this->container);

        $hooks = array_keys($GLOBALS['wp_actions']);
        $this->assertContains('bmn_extraction_completed', $hooks);
    }
}
