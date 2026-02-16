<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Properties\Repository\PropertySearchRepository;
use BMN\Properties\Service\AutocompleteService;
use PHPUnit\Framework\TestCase;

final class AutocompleteServiceTest extends TestCase
{
    private PropertySearchRepository $repository;
    private CacheService $cache;
    private AutocompleteService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PropertySearchRepository::class);
        $this->cache = $this->createMock(CacheService::class);

        // By default, cache->remember executes the callback immediately.
        $this->cache->method('remember')->willReturnCallback(
            fn (string $key, int $ttl, callable $callback, string $group) => $callback()
        );

        $this->service = new AutocompleteService($this->repository, $this->cache);
    }

    private function configureEmptyResults(): void
    {
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteCities')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);
    }

    // ------------------------------------------------------------------
    // Basic behavior
    // ------------------------------------------------------------------

    public function testReturnsEmptyForShortTerm(): void
    {
        $result = $this->service->suggest('B');

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyForEmptyTerm(): void
    {
        $result = $this->service->suggest('');

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyForWhitespaceTerm(): void
    {
        $result = $this->service->suggest('  ');

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // Suggestion types
    // ------------------------------------------------------------------

    public function testReturnsCitySuggestions(): void
    {
        $this->repository->method('autocompleteCities')->willReturn([
            (object) ['value' => 'Boston', 'count' => 500],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('Bos');

        $this->assertCount(1, $result);
        $this->assertSame('Boston', $result[0]['value']);
        $this->assertSame('city', $result[0]['type']);
        $this->assertSame(500, $result[0]['count']);
    }

    public function testReturnsZipSuggestions(): void
    {
        $this->repository->method('autocompleteZips')->willReturn([
            (object) ['value' => '02116', 'count' => 150],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteCities')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('021');

        $this->assertCount(1, $result);
        $this->assertSame('02116', $result[0]['value']);
        $this->assertSame('zip', $result[0]['type']);
    }

    public function testReturnsMlsSuggestions(): void
    {
        $this->repository->method('autocompleteMlsNumbers')->willReturn([
            (object) ['value' => '73464868'],
        ]);
        $this->repository->method('autocompleteCities')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('734');

        $this->assertCount(1, $result);
        $this->assertSame('73464868', $result[0]['value']);
        $this->assertSame('mls', $result[0]['type']);
    }

    public function testReturnsNeighborhoodSuggestions(): void
    {
        $this->repository->method('autocompleteNeighborhoods')->willReturn([
            (object) ['value' => 'Back Bay', 'count' => 200],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteCities')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('Back');

        $this->assertCount(1, $result);
        $this->assertSame('Back Bay', $result[0]['value']);
        $this->assertSame('neighborhood', $result[0]['type']);
    }

    public function testReturnsStreetSuggestions(): void
    {
        $this->repository->method('autocompleteStreetNames')->willReturn([
            (object) ['value' => 'Beacon St', 'count' => 75],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteCities')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('Bea');

        $this->assertCount(1, $result);
        $this->assertSame('Beacon St', $result[0]['value']);
        $this->assertSame('street', $result[0]['type']);
    }

    public function testReturnsAddressSuggestions(): void
    {
        $this->repository->method('autocompleteAddresses')->willReturn([
            (object) ['value' => '123 Main St, Boston', 'listing_id' => '73464868'],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteCities')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);

        $result = $this->service->suggest('123 Main');

        $this->assertCount(1, $result);
        $this->assertSame('123 Main St, Boston', $result[0]['value']);
        $this->assertSame('address', $result[0]['type']);
    }

    // ------------------------------------------------------------------
    // Priority ordering
    // ------------------------------------------------------------------

    public function testMlsSuggestionsHaveHighestPriority(): void
    {
        $this->repository->method('autocompleteMlsNumbers')->willReturn([
            (object) ['value' => '02116'],
        ]);
        $this->repository->method('autocompleteCities')->willReturn([
            (object) ['value' => '02116 City', 'count' => 10],
        ]);
        $this->repository->method('autocompleteZips')->willReturn([
            (object) ['value' => '02116', 'count' => 50],
        ]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('02116');

        // MLS should be first (but "02116" will be deduped — MLS has higher priority than zip).
        $mlsFound = false;
        foreach ($result as $suggestion) {
            if ($suggestion['value'] === '02116' && $suggestion['type'] === 'mls') {
                $mlsFound = true;
                break;
            }
        }
        $this->assertTrue($mlsFound, 'MLS suggestion should win dedup over zip');
    }

    // ------------------------------------------------------------------
    // Deduplication
    // ------------------------------------------------------------------

    public function testDeduplicatesByValue(): void
    {
        $this->repository->method('autocompleteCities')->willReturn([
            (object) ['value' => 'Boston', 'count' => 500],
        ]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([
            (object) ['value' => 'Boston', 'count' => 50],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('Bos');

        // Should have only 1 entry for "Boston" (city wins over neighborhood).
        $bostonResults = array_filter($result, fn ($s) => strtolower($s['value']) === 'boston');
        $this->assertCount(1, $bostonResults);
        $this->assertSame('city', array_values($bostonResults)[0]['type']);
    }

    // ------------------------------------------------------------------
    // Limit
    // ------------------------------------------------------------------

    public function testLimitedToMaxResults(): void
    {
        // Generate many results.
        $cities = [];
        for ($i = 0; $i < 15; $i++) {
            $cities[] = (object) ['value' => "City{$i}", 'count' => $i];
        }
        $this->repository->method('autocompleteCities')->willReturn($cities);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('City');

        $this->assertLessThanOrEqual(10, count($result));
    }

    // ------------------------------------------------------------------
    // Caching
    // ------------------------------------------------------------------

    public function testUsesFiveMinuteCacheTTL(): void
    {
        $this->cache = $this->createMock(CacheService::class);
        $this->cache->expects($this->once())
            ->method('remember')
            ->with(
                $this->stringStartsWith('ac_'),
                300,
                $this->isType('callable'),
                'autocomplete'
            )
            ->willReturn([]);

        $service = new AutocompleteService($this->repository, $this->cache);
        $service->suggest('Bos');
    }

    public function testCacheHitSkipsRepositoryCalls(): void
    {
        $cachedResult = [['value' => 'Boston', 'type' => 'city', 'count' => 500]];

        $this->cache = $this->createMock(CacheService::class);
        $this->cache->method('remember')->willReturn($cachedResult);

        $this->repository->expects($this->never())->method('autocompleteCities');

        $service = new AutocompleteService($this->repository, $this->cache);
        $result = $service->suggest('Bos');

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // Null/empty value filtering
    // ------------------------------------------------------------------

    public function testFiltersOutNullValues(): void
    {
        $this->repository->method('autocompleteCities')->willReturn([
            (object) ['value' => null, 'count' => 10],
            (object) ['value' => 'Boston', 'count' => 500],
        ]);
        $this->repository->method('autocompleteMlsNumbers')->willReturn([]);
        $this->repository->method('autocompleteZips')->willReturn([]);
        $this->repository->method('autocompleteNeighborhoods')->willReturn([]);
        $this->repository->method('autocompleteStreetNames')->willReturn([]);
        $this->repository->method('autocompleteAddresses')->willReturn([]);

        $result = $this->service->suggest('Bos');

        // Should only have Boston (null value filtered out during dedup).
        $this->assertCount(1, $result);
        $this->assertSame('Boston', $result[0]['value']);
    }
}
