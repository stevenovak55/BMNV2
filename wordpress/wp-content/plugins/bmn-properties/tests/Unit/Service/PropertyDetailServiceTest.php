<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Properties\Repository\PropertySearchRepository;
use BMN\Properties\Service\PropertyDetailService;
use PHPUnit\Framework\TestCase;

final class PropertyDetailServiceTest extends TestCase
{
    private PropertySearchRepository $repository;
    private CacheService $cache;
    private PropertyDetailService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PropertySearchRepository::class);
        $this->cache = $this->createMock(CacheService::class);

        // By default, cache->remember executes the callback immediately.
        $this->cache->method('remember')->willReturnCallback(
            fn (string $key, int $ttl, callable $callback, string $group) => $callback()
        );

        $this->service = new PropertyDetailService($this->repository, $this->cache);
    }

    private function buildPropertyRow(): object
    {
        return (object) [
            'listing_id' => '73464868',
            'listing_key' => 'LK1',
            'unparsed_address' => '123 Main St, Boston, MA 02116',
            'street_number' => '123',
            'street_name' => 'Main St',
            'unit_number' => null,
            'city' => 'Boston',
            'state_or_province' => 'MA',
            'postal_code' => '02116',
            'county_or_parish' => 'Suffolk',
            'subdivision_name' => 'Back Bay',
            'list_price' => '750000.00',
            'original_list_price' => '800000.00',
            'close_price' => null,
            'price_per_sqft' => '500.00',
            'bedrooms_total' => 3,
            'bathrooms_total' => 2,
            'bathrooms_full' => 1,
            'bathrooms_half' => 1,
            'living_area' => '1500.00',
            'lot_size_acres' => '0.10',
            'year_built' => 2005,
            'rooms_total' => 6,
            'garage_spaces' => 1,
            'parking_total' => 2,
            'fireplaces_total' => 1,
            'property_type' => 'Residential',
            'property_sub_type' => 'Condominium',
            'standard_status' => 'Active',
            'is_archived' => 0,
            'latitude' => 42.35,
            'longitude' => -71.07,
            'listing_contract_date' => '2026-01-15',
            'close_date' => null,
            'days_on_market' => 32,
            'public_remarks' => 'Beautiful condo in Back Bay.',
            'showing_instructions' => 'Call agent.',
            'virtual_tour_url_unbranded' => 'https://tour.example.com/123',
            'main_photo_url' => 'https://photos.example.com/1.jpg',
            'photo_count' => 10,
            'tax_annual_amount' => '5000.00',
            'tax_year' => 2025,
            'association_fee' => '400.00',
            'association_yn' => 1,
            'elementary_school' => 'Eliot',
            'middle_or_junior_school' => 'Timilty',
            'high_school' => 'Boston Latin',
            'school_district' => 'Boston',
            'list_agent_mls_id' => 'AGT001',
            'list_office_mls_id' => 'OFF001',
        ];
    }

    // ------------------------------------------------------------------
    // Basic detail fetch
    // ------------------------------------------------------------------

    public function testGetByListingIdReturnsFormattedDetail(): void
    {
        $property = $this->buildPropertyRow();
        $this->repository->method('findByListingId')->with('73464868')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertNotNull($result);
        $this->assertSame('73464868', $result['listing_id']);
        $this->assertSame('Boston', $result['city']);
        $this->assertSame(750000.0, $result['price']);
        $this->assertSame(3, $result['beds']);
        $this->assertSame('Beautiful condo in Back Bay.', $result['public_remarks']);
    }

    public function testGetByListingIdReturnsNullWhenNotFound(): void
    {
        $this->repository->method('findByListingId')->willReturn(null);

        $result = $this->service->getByListingId('nonexistent');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // Related data
    // ------------------------------------------------------------------

    public function testFetchesAgentWhenMlsIdPresent(): void
    {
        $property = $this->buildPropertyRow();
        $agent = (object) [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '617-555-1234',
            'agent_mls_id' => 'AGT001',
        ];

        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->expects($this->once())
            ->method('findAgent')
            ->with('AGT001')
            ->willReturn($agent);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertNotNull($result['agent']);
        $this->assertSame('John Doe', $result['agent']['name']);
        $this->assertSame('john@example.com', $result['agent']['email']);
    }

    public function testFetchesOfficeWhenMlsIdPresent(): void
    {
        $property = $this->buildPropertyRow();
        $office = (object) [
            'office_name' => 'Best Realty',
            'phone' => '617-555-5678',
            'address' => '100 Boylston St',
            'city' => 'Boston',
            'state_or_province' => 'MA',
            'postal_code' => '02116',
        ];

        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->expects($this->once())
            ->method('findOffice')
            ->with('OFF001')
            ->willReturn($office);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertNotNull($result['office']);
        $this->assertSame('Best Realty', $result['office']['name']);
    }

    public function testFetchesOpenHouses(): void
    {
        $property = $this->buildPropertyRow();
        $openHouses = [
            (object) [
                'open_house_date' => '2026-02-20',
                'open_house_start_time' => '12:00',
                'open_house_end_time' => '14:00',
                'open_house_type' => 'Public',
                'open_house_remarks' => 'Welcome!',
            ],
        ];

        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn($openHouses);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertTrue($result['has_open_house']);
        $this->assertCount(1, $result['open_houses']);
        $this->assertSame('2026-02-20', $result['open_houses'][0]['date']);
    }

    public function testFetchesPriceHistory(): void
    {
        $property = $this->buildPropertyRow();
        $history = [
            (object) [
                'change_type' => 'price_change',
                'field_name' => 'list_price',
                'old_value' => '800000',
                'new_value' => '750000',
                'changed_at' => '2026-02-01 10:00:00',
            ],
        ];

        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn($history);

        $result = $this->service->getByListingId('73464868');

        $this->assertCount(1, $result['price_history']);
        $this->assertSame('price_change', $result['price_history'][0]['change_type']);
    }

    public function testFetchesPhotos(): void
    {
        $property = $this->buildPropertyRow();
        $photos = [
            (object) ['media_url' => 'photo1.jpg', 'media_category' => 'Photo', 'order_index' => 0],
            (object) ['media_url' => 'photo2.jpg', 'media_category' => 'Photo', 'order_index' => 1],
        ];

        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn($photos);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertCount(2, $result['photos']);
        $this->assertSame('photo1.jpg', $result['photos'][0]['url']);
    }

    // ------------------------------------------------------------------
    // Caching
    // ------------------------------------------------------------------

    public function testUsesOneHourCacheTTL(): void
    {
        $this->cache = $this->createMock(CacheService::class);
        $this->cache->expects($this->once())
            ->method('remember')
            ->with(
                'detail_73464868',
                3600,
                $this->isType('callable'),
                'property_detail'
            )
            ->willReturn(null);

        $service = new PropertyDetailService($this->repository, $this->cache);
        $service->getByListingId('73464868');
    }

    public function testCacheHitSkipsRepositoryCall(): void
    {
        $cachedResult = ['listing_id' => '73464868', 'city' => 'Boston'];

        $this->cache = $this->createMock(CacheService::class);
        $this->cache->method('remember')->willReturn($cachedResult);

        $this->repository->expects($this->never())->method('findByListingId');

        $service = new PropertyDetailService($this->repository, $this->cache);
        $result = $service->getByListingId('73464868');

        $this->assertSame('73464868', $result['listing_id']);
    }

    // ------------------------------------------------------------------
    // Archived property visibility
    // ------------------------------------------------------------------

    public function testArchivedPropertyStillVisible(): void
    {
        $property = $this->buildPropertyRow();
        $property->is_archived = 1;
        $property->standard_status = 'Closed';

        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertNotNull($result);
        $this->assertTrue($result['is_archived']);
        $this->assertSame('Closed', $result['status']);
    }

    // ------------------------------------------------------------------
    // PropertyDetail formatting
    // ------------------------------------------------------------------

    public function testDetailIncludesTaxInfo(): void
    {
        $property = $this->buildPropertyRow();
        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertSame(5000.0, $result['tax_annual_amount']);
        $this->assertSame(2025, $result['tax_year']);
        $this->assertSame(400.0, $result['association_fee']);
        $this->assertTrue($result['association_yn']);
    }

    public function testDetailIncludesSchoolInfo(): void
    {
        $property = $this->buildPropertyRow();
        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertSame('Eliot', $result['elementary_school']);
        $this->assertSame('Timilty', $result['middle_school']);
        $this->assertSame('Boston Latin', $result['high_school']);
        $this->assertSame('Boston', $result['school_district']);
    }

    public function testDetailIncludesVirtualTourUrl(): void
    {
        $property = $this->buildPropertyRow();
        $this->repository->method('findByListingId')->willReturn($property);
        $this->repository->method('fetchAllMedia')->willReturn([]);
        $this->repository->method('findAgent')->willReturn(null);
        $this->repository->method('findOffice')->willReturn(null);
        $this->repository->method('fetchUpcomingOpenHouses')->willReturn([]);
        $this->repository->method('fetchPropertyHistory')->willReturn([]);

        $result = $this->service->getByListingId('73464868');

        $this->assertSame('https://tour.example.com/123', $result['virtual_tour_url']);
    }
}
