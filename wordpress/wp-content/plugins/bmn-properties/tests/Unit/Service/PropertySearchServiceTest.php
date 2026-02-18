<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Properties\Repository\PropertySearchRepository;
use BMN\Properties\Service\Filter\FilterBuilder;
use BMN\Properties\Service\Filter\FilterResult;
use BMN\Properties\Service\PropertySearchService;
use PHPUnit\Framework\TestCase;

final class PropertySearchServiceTest extends TestCase
{
    private PropertySearchRepository $repository;
    private FilterBuilder $filterBuilder;
    private CacheService $cache;
    private PropertySearchService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PropertySearchRepository::class);
        $this->filterBuilder = $this->createMock(FilterBuilder::class);
        $this->cache = $this->createMock(CacheService::class);

        // By default, cache->remember executes the callback immediately.
        $this->cache->method('remember')->willReturnCallback(
            fn (string $key, int $ttl, callable $callback, string $group) => $callback()
        );

        $this->service = new PropertySearchService(
            $this->repository,
            $this->filterBuilder,
            $this->cache,
        );
    }

    private function buildFilterResult(array $overrides = []): FilterResult
    {
        return new FilterResult(
            where: $overrides['where'] ?? "is_archived = 0 AND standard_status = 'Active'",
            orderBy: $overrides['orderBy'] ?? 'listing_contract_date DESC',
            isDirectLookup: $overrides['isDirectLookup'] ?? false,
            hasSchoolFilters: $overrides['hasSchoolFilters'] ?? false,
            schoolCriteria: $overrides['schoolCriteria'] ?? [],
            overfetchMultiplier: $overrides['overfetchMultiplier'] ?? 1,
        );
    }

    private function buildPropertyRow(string $listingId = '73464868', string $listingKey = 'LK1'): object
    {
        return (object) [
            'listing_id' => $listingId,
            'listing_key' => $listingKey,
            'unparsed_address' => '123 Main St',
            'street_number' => '123',
            'street_name' => 'Main St',
            'unit_number' => null,
            'city' => 'Boston',
            'state_or_province' => 'MA',
            'postal_code' => '02116',
            'list_price' => '750000.00',
            'original_list_price' => '800000.00',
            'bedrooms_total' => 3,
            'bathrooms_total' => 2,
            'living_area' => '1500.00',
            'property_type' => 'Residential',
            'property_sub_type' => 'Condominium',
            'standard_status' => 'Active',
            'latitude' => 42.35,
            'longitude' => -71.07,
            'listing_contract_date' => '2026-01-15',
            'days_on_market' => 32,
            'main_photo_url' => 'https://photos.example.com/1.jpg',
            'year_built' => 2005,
            'lot_size_acres' => 0.1,
            'garage_spaces' => 1,
            'is_archived' => 0,
        ];
    }

    // ------------------------------------------------------------------
    // Basic search flow
    // ------------------------------------------------------------------

    public function testSearchReturnsFormattedResults(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow();
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);
        $this->repository->method('batchFetchMedia')->willReturn([]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search([], 1, 25);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(25, $result['per_page']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('73464868', $result['data'][0]['listing_id']);
    }

    public function testSearchReturnsEmptyForNoResults(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);
        $this->repository->method('searchProperties')->willReturn([]);
        $this->repository->method('countProperties')->willReturn(0);

        $result = $this->service->search([], 1, 25);

        $this->assertSame([], $result['data']);
        $this->assertSame(0, $result['total']);
    }

    // ------------------------------------------------------------------
    // Pagination
    // ------------------------------------------------------------------

    public function testPerPageClampedToMin1(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);
        $this->repository->method('searchProperties')->willReturn([]);
        $this->repository->method('countProperties')->willReturn(0);

        $result = $this->service->search([], 1, 0);

        $this->assertSame(1, $result['per_page']);
    }

    public function testPerPageClampedToMax250(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);
        $this->repository->method('searchProperties')->willReturn([]);
        $this->repository->method('countProperties')->willReturn(0);

        $result = $this->service->search([], 1, 500);

        $this->assertSame(250, $result['per_page']);
    }

    public function testPageClampedToMin1(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);
        $this->repository->method('searchProperties')->willReturn([]);
        $this->repository->method('countProperties')->willReturn(0);

        $result = $this->service->search([], -1, 25);

        $this->assertSame(1, $result['page']);
    }

    // ------------------------------------------------------------------
    // Caching
    // ------------------------------------------------------------------

    public function testSearchUsesCache(): void
    {
        $this->cache = $this->createMock(CacheService::class);
        $this->cache->expects($this->once())
            ->method('remember')
            ->with(
                $this->stringStartsWith('search_'),
                120,
                $this->isType('callable'),
                'property_search'
            )
            ->willReturn(['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 25]);

        $service = new PropertySearchService($this->repository, $this->filterBuilder, $this->cache);

        $service->search([], 1, 25);
    }

    public function testDifferentFiltersBuildDifferentCacheKeys(): void
    {
        $keys = [];
        $this->cache = $this->createMock(CacheService::class);
        $this->cache->method('remember')
            ->willReturnCallback(function (string $key, int $ttl, callable $callback) use (&$keys) {
                $keys[] = $key;
                return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 25];
            });

        $service = new PropertySearchService($this->repository, $this->filterBuilder, $this->cache);

        $service->search(['city' => 'Boston'], 1, 25);
        $service->search(['city' => 'Cambridge'], 1, 25);

        $this->assertCount(2, $keys);
        $this->assertNotSame($keys[0], $keys[1]);
    }

    // ------------------------------------------------------------------
    // Batch operations
    // ------------------------------------------------------------------

    public function testBatchFetchesPhotosForResults(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow();
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);

        $this->repository->expects($this->once())
            ->method('batchFetchMedia')
            ->with(['LK1'], 5)
            ->willReturn([
                'LK1' => [
                    (object) ['media_url' => 'photo1.jpg', 'media_category' => 'Photo', 'order_index' => 0],
                ],
            ]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search([], 1, 25);

        $this->assertSame(['photo1.jpg'], $result['data'][0]['photos']);
    }

    public function testBatchFetchesOpenHousesForResults(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow();
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);
        $this->repository->method('batchFetchMedia')->willReturn([]);

        $this->repository->expects($this->once())
            ->method('batchFetchNextOpenHouses')
            ->with(['LK1'])
            ->willReturn([
                'LK1' => (object) [
                    'open_house_date' => '2026-02-20',
                    'open_house_start_time' => '12:00',
                    'open_house_end_time' => '14:00',
                ],
            ]);

        $result = $this->service->search([], 1, 25);

        $this->assertTrue($result['data'][0]['has_open_house']);
        $this->assertSame('2026-02-20', $result['data'][0]['next_open_house']['date']);
    }

    // ------------------------------------------------------------------
    // School overfetch
    // ------------------------------------------------------------------

    public function testSchoolFiltersMultiplyFetchLimit(): void
    {
        $filterResult = $this->buildFilterResult([
            'hasSchoolFilters' => true,
            'schoolCriteria' => ['school_grade' => 'A'],
            'overfetchMultiplier' => 10,
        ]);
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $this->repository->expects($this->once())
            ->method('searchProperties')
            ->with(
                PropertySearchRepository::LIST_SELECT,
                $this->anything(),
                $this->anything(),
                $this->greaterThanOrEqual(250), // 25 * 10 = 250
                $this->anything(),
            )
            ->willReturn([]);
        $this->repository->method('countProperties')->willReturn(0);

        $this->service->search(['school_grade' => 'A'], 1, 25);
    }

    // ------------------------------------------------------------------
    // Direct lookup
    // ------------------------------------------------------------------

    public function testDirectLookupPassesFiltersThrough(): void
    {
        $filterResult = $this->buildFilterResult([
            'isDirectLookup' => true,
            'where' => "listing_id = '73464868'",
        ]);
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow();
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);
        $this->repository->method('batchFetchMedia')->willReturn([]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search(['mls_number' => '73464868'], 1, 25);

        $this->assertCount(1, $result['data']);
    }

    // ------------------------------------------------------------------
    // PropertyListItem formatting
    // ------------------------------------------------------------------

    public function testListItemFormatsCorrectly(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow();
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);
        $this->repository->method('batchFetchMedia')->willReturn([]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search([], 1, 25);
        $item = $result['data'][0];

        $this->assertSame('73464868', $item['listing_id']);
        $this->assertSame('LK1', $item['listing_key']);
        $this->assertSame('123 Main St', $item['address']);
        $this->assertSame('Boston', $item['city']);
        $this->assertSame(750000.0, $item['price']);
        $this->assertSame(800000.0, $item['original_price']);
        $this->assertSame(3, $item['beds']);
        $this->assertSame(2, $item['baths']);
        $this->assertSame(1500, $item['sqft']);
        $this->assertSame('Residential', $item['property_type']);
        $this->assertSame('Active', $item['status']);
        $this->assertSame(42.35, $item['latitude']);
        $this->assertSame(-71.07, $item['longitude']);
        $this->assertSame(32, $item['dom']);
        $this->assertFalse($item['has_open_house']);
        $this->assertNull($item['next_open_house']);
    }

    public function testExclusiveListingDetected(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow('100', 'LK_EXCL');
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);
        $this->repository->method('batchFetchMedia')->willReturn([]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search([], 1, 25);

        $this->assertTrue($result['data'][0]['is_exclusive']);
    }

    public function testNonExclusiveListingDetected(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $row = $this->buildPropertyRow('73464868', 'LK1');
        $this->repository->method('searchProperties')->willReturn([$row]);
        $this->repository->method('countProperties')->willReturn(1);
        $this->repository->method('batchFetchMedia')->willReturn([]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search([], 1, 25);

        $this->assertFalse($result['data'][0]['is_exclusive']);
    }

    // ------------------------------------------------------------------
    // Multiple results
    // ------------------------------------------------------------------

    public function testMultipleResultsFormattedCorrectly(): void
    {
        $filterResult = $this->buildFilterResult();
        $this->filterBuilder->method('build')->willReturn($filterResult);

        $rows = [
            $this->buildPropertyRow('111', 'LK1'),
            $this->buildPropertyRow('222', 'LK2'),
            $this->buildPropertyRow('333', 'LK3'),
        ];
        $this->repository->method('searchProperties')->willReturn($rows);
        $this->repository->method('countProperties')->willReturn(3);
        $this->repository->method('batchFetchMedia')->willReturn([]);
        $this->repository->method('batchFetchNextOpenHouses')->willReturn([]);

        $result = $this->service->search([], 1, 25);

        $this->assertCount(3, $result['data']);
        $this->assertSame(3, $result['total']);
    }

    // ------------------------------------------------------------------
    // Filters passed to builder
    // ------------------------------------------------------------------

    public function testFiltersPassedToFilterBuilder(): void
    {
        $filters = ['city' => 'Boston', 'beds' => '3'];

        $this->filterBuilder->expects($this->once())
            ->method('build')
            ->with($filters)
            ->willReturn($this->buildFilterResult());

        $this->repository->method('searchProperties')->willReturn([]);
        $this->repository->method('countProperties')->willReturn(0);

        $this->service->search($filters, 1, 25);
    }
}
