<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Service\Filter;

use BMN\Platform\Geocoding\GeocodingService;
use BMN\Properties\Service\Filter\FilterBuilder;
use BMN\Properties\Service\Filter\FilterResult;
use BMN\Properties\Service\Filter\SortResolver;
use BMN\Properties\Service\Filter\StatusResolver;
use PHPUnit\Framework\TestCase;

final class FilterBuilderTest extends TestCase
{
    private \wpdb $wpdb;
    private FilterBuilder $builder;
    private GeocodingService $geocoding;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->geocoding = $this->createMock(GeocodingService::class);
        $this->builder = new FilterBuilder(
            $this->wpdb,
            $this->geocoding,
            new StatusResolver(),
            new SortResolver(),
        );
    }

    // ------------------------------------------------------------------
    // Basic / Default
    // ------------------------------------------------------------------

    public function testEmptyFiltersReturnActiveStatus(): void
    {
        $result = $this->builder->build([]);

        $this->assertInstanceOf(FilterResult::class, $result);
        $this->assertStringContainsString("is_archived = 0", $result->where);
        $this->assertStringContainsString("standard_status = 'Active'", $result->where);
        $this->assertFalse($result->isDirectLookup);
        $this->assertFalse($result->hasSchoolFilters);
        $this->assertSame(1, $result->overfetchMultiplier);
    }

    public function testDefaultOrderByIsListDateDesc(): void
    {
        $result = $this->builder->build([]);

        $this->assertSame('listing_contract_date DESC', $result->orderBy);
    }

    // ------------------------------------------------------------------
    // Direct Lookup
    // ------------------------------------------------------------------

    public function testMlsNumberDirectLookup(): void
    {
        $result = $this->builder->build(['mls_number' => '73464868']);

        $this->assertTrue($result->isDirectLookup);
        $this->assertStringContainsString("listing_id = '73464868'", $result->where);
        // Direct lookup should NOT include status filters.
        $this->assertStringNotContainsString('is_archived', $result->where);
    }

    public function testAddressDirectLookup(): void
    {
        $result = $this->builder->build(['address' => '123 Main St']);

        $this->assertTrue($result->isDirectLookup);
        $this->assertStringContainsString('unparsed_address LIKE', $result->where);
        $this->assertStringContainsString('123 Main St', $result->where);
    }

    public function testDirectLookupBypassesOtherFilters(): void
    {
        $result = $this->builder->build([
            'mls_number' => '73464868',
            'city' => 'Boston',
            'beds' => 3,
        ]);

        $this->assertTrue($result->isDirectLookup);
        // Should only have the MLS number condition.
        $this->assertStringNotContainsString('city', $result->where);
        $this->assertStringNotContainsString('bedrooms_total', $result->where);
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    public function testStatusFilterActive(): void
    {
        $result = $this->builder->build(['status' => 'Active']);

        $this->assertStringContainsString("is_archived = 0", $result->where);
    }

    public function testStatusFilterSold(): void
    {
        $result = $this->builder->build(['status' => 'Sold']);

        $this->assertStringContainsString("is_archived = 1", $result->where);
        $this->assertStringContainsString("standard_status = 'Closed'", $result->where);
    }

    public function testStatusFilterPending(): void
    {
        $result = $this->builder->build(['status' => 'Pending']);

        $this->assertStringContainsString("'Pending'", $result->where);
        $this->assertStringContainsString("'Active Under Contract'", $result->where);
    }

    // ------------------------------------------------------------------
    // Location
    // ------------------------------------------------------------------

    public function testCityFilter(): void
    {
        $result = $this->builder->build(['city' => 'Boston']);

        $this->assertStringContainsString("city IN ('Boston')", $result->where);
    }

    public function testMultipleCitiesCommaSeparated(): void
    {
        $result = $this->builder->build(['city' => 'Boston,Cambridge']);

        $this->assertStringContainsString('city IN', $result->where);
        $this->assertStringContainsString("'Boston'", $result->where);
        $this->assertStringContainsString("'Cambridge'", $result->where);
    }

    public function testZipFilter(): void
    {
        $result = $this->builder->build(['zip' => '02116']);

        $this->assertStringContainsString("postal_code IN ('02116')", $result->where);
    }

    public function testNeighborhoodSearchesThreeColumns(): void
    {
        $result = $this->builder->build(['neighborhood' => 'Back Bay']);

        $this->assertStringContainsString('subdivision_name', $result->where);
        $this->assertStringContainsString('mls_area_major', $result->where);
        $this->assertStringContainsString('mls_area_minor', $result->where);
        $this->assertStringContainsString('OR', $result->where);
    }

    public function testStreetNameUsesLike(): void
    {
        $result = $this->builder->build(['street_name' => 'Beacon']);

        $this->assertStringContainsString('street_name LIKE', $result->where);
        $this->assertStringContainsString('Beacon', $result->where);
    }

    // ------------------------------------------------------------------
    // Type
    // ------------------------------------------------------------------

    public function testPropertyTypeFilter(): void
    {
        $result = $this->builder->build(['property_type' => 'Residential']);

        $this->assertStringContainsString("property_type = 'Residential'", $result->where);
    }

    public function testPropertySubTypeFilter(): void
    {
        $result = $this->builder->build(['property_sub_type' => 'Condominium']);

        $this->assertStringContainsString("property_sub_type = 'Condominium'", $result->where);
    }

    // ------------------------------------------------------------------
    // Price
    // ------------------------------------------------------------------

    public function testMinPriceFilter(): void
    {
        $result = $this->builder->build(['min_price' => '500000']);

        $this->assertStringContainsString('list_price >= 500000', $result->where);
    }

    public function testMaxPriceFilter(): void
    {
        $result = $this->builder->build(['max_price' => '1000000']);

        $this->assertStringContainsString('list_price <= 1000000', $result->where);
    }

    public function testPriceReducedFilter(): void
    {
        $result = $this->builder->build(['price_reduced' => '1']);

        $this->assertStringContainsString('original_list_price > list_price', $result->where);
    }

    // ------------------------------------------------------------------
    // Rooms
    // ------------------------------------------------------------------

    public function testBedsMinimumFilter(): void
    {
        $result = $this->builder->build(['beds' => '3']);

        $this->assertStringContainsString('bedrooms_total >= 3', $result->where);
    }

    public function testBathsMinimumFilter(): void
    {
        $result = $this->builder->build(['baths' => '2']);

        $this->assertStringContainsString('bathrooms_total >= 2', $result->where);
    }

    // ------------------------------------------------------------------
    // Size
    // ------------------------------------------------------------------

    public function testSqftMinFilter(): void
    {
        $result = $this->builder->build(['sqft_min' => '1000']);

        $this->assertStringContainsString('living_area >= 1000', $result->where);
    }

    public function testSqftMaxFilter(): void
    {
        $result = $this->builder->build(['sqft_max' => '3000']);

        $this->assertStringContainsString('living_area <= 3000', $result->where);
    }

    public function testLotSizeAutoConvertSqftToAcres(): void
    {
        // 43560 sqft = 1 acre. A value > 100 is assumed sqft.
        $result = $this->builder->build(['lot_size_min' => '43560']);

        $this->assertStringContainsString('lot_size_acres >=', $result->where);
        // Should be approximately 1.0 acre.
        $this->assertMatchesRegularExpression('/lot_size_acres >= .*1\.0/', $result->where);
    }

    public function testLotSizeSmallValueTreatedAsAcres(): void
    {
        // A value <= 100 is treated as acres directly.
        $result = $this->builder->build(['lot_size_min' => '0.5']);

        $this->assertStringContainsString('lot_size_acres >=', $result->where);
        $this->assertStringContainsString('0.5', $result->where);
    }

    // ------------------------------------------------------------------
    // Time
    // ------------------------------------------------------------------

    public function testYearBuiltMinFilter(): void
    {
        $result = $this->builder->build(['year_built_min' => '2000']);

        $this->assertStringContainsString('year_built >= 2000', $result->where);
    }

    public function testYearBuiltMaxFilter(): void
    {
        $result = $this->builder->build(['year_built_max' => '2020']);

        $this->assertStringContainsString('year_built <= 2020', $result->where);
    }

    public function testMaxDomFilter(): void
    {
        $result = $this->builder->build(['max_dom' => '30']);

        $this->assertStringContainsString('days_on_market <= 30', $result->where);
    }

    public function testMinDomFilter(): void
    {
        $result = $this->builder->build(['min_dom' => '7']);

        $this->assertStringContainsString('days_on_market >= 7', $result->where);
    }

    public function testNewListingDaysFilter(): void
    {
        $result = $this->builder->build(['new_listing_days' => '7']);

        $this->assertStringContainsString('listing_contract_date >=', $result->where);
    }

    // ------------------------------------------------------------------
    // Parking
    // ------------------------------------------------------------------

    public function testGarageSpacesMinFilter(): void
    {
        $result = $this->builder->build(['garage_spaces_min' => '2']);

        $this->assertStringContainsString('garage_spaces >= 2', $result->where);
    }

    public function testParkingTotalMinFilter(): void
    {
        $result = $this->builder->build(['parking_total_min' => '3']);

        $this->assertStringContainsString('parking_total >= 3', $result->where);
    }

    // ------------------------------------------------------------------
    // Amenity
    // ------------------------------------------------------------------

    public function testHasVirtualTourFilter(): void
    {
        $result = $this->builder->build(['has_virtual_tour' => '1']);

        $this->assertStringContainsString('virtual_tour_url_unbranded IS NOT NULL', $result->where);
    }

    public function testHasGarageFilter(): void
    {
        $result = $this->builder->build(['has_garage' => '1']);

        $this->assertStringContainsString('garage_spaces > 0', $result->where);
    }

    public function testHasFireplaceFilter(): void
    {
        $result = $this->builder->build(['has_fireplace' => '1']);

        $this->assertStringContainsString('fireplaces_total > 0', $result->where);
    }

    // ------------------------------------------------------------------
    // Special
    // ------------------------------------------------------------------

    public function testOpenHouseOnlyFilter(): void
    {
        $result = $this->builder->build(['open_house_only' => '1']);

        $this->assertStringContainsString('listing_key IN (SELECT listing_key FROM', $result->where);
        $this->assertStringContainsString('bmn_open_houses', $result->where);
    }

    public function testExclusiveOnlyFilter(): void
    {
        $result = $this->builder->build(['exclusive_only' => '1']);

        $this->assertStringContainsString('CAST(listing_id AS UNSIGNED) < 1000000', $result->where);
    }

    // ------------------------------------------------------------------
    // Geo
    // ------------------------------------------------------------------

    public function testBoundsFilterUsesSpatialQuery(): void
    {
        $this->geocoding
            ->expects($this->once())
            ->method('buildSpatialBoundsCondition')
            ->with(42.4, 42.3, -71.0, -71.1, 'coordinates')
            ->willReturn('MBRContains(ST_GeomFromText(...), coordinates)');

        $result = $this->builder->build(['bounds' => '42.3,-71.1,42.4,-71.0']);

        $this->assertStringContainsString('MBRContains', $result->where);
    }

    public function testPolygonFilter(): void
    {
        $polygon = [[42.3, -71.1], [42.4, -71.0], [42.35, -71.05]];

        $this->geocoding
            ->expects($this->once())
            ->method('buildPolygonCondition')
            ->with($polygon, 'latitude', 'longitude')
            ->willReturn('(polygon_condition)');

        $result = $this->builder->build(['polygon' => json_encode($polygon)]);

        $this->assertStringContainsString('polygon_condition', $result->where);
    }

    // ------------------------------------------------------------------
    // School (detection)
    // ------------------------------------------------------------------

    public function testSchoolFiltersDetected(): void
    {
        $result = $this->builder->build(['school_grade' => 'A']);

        $this->assertTrue($result->hasSchoolFilters);
        $this->assertSame(['school_grade' => 'A'], $result->schoolCriteria);
        $this->assertSame(10, $result->overfetchMultiplier);
    }

    public function testNoSchoolFiltersWhenAbsent(): void
    {
        $result = $this->builder->build(['city' => 'Boston']);

        $this->assertFalse($result->hasSchoolFilters);
        $this->assertSame([], $result->schoolCriteria);
        $this->assertSame(1, $result->overfetchMultiplier);
    }

    // ------------------------------------------------------------------
    // Combinations
    // ------------------------------------------------------------------

    public function testCombinedFilters(): void
    {
        $result = $this->builder->build([
            'city' => 'Boston',
            'beds' => '3',
            'baths' => '2',
            'min_price' => '500000',
            'max_price' => '1000000',
            'sort' => 'price_asc',
        ]);

        $this->assertStringContainsString("city IN ('Boston')", $result->where);
        $this->assertStringContainsString('bedrooms_total >= 3', $result->where);
        $this->assertStringContainsString('bathrooms_total >= 2', $result->where);
        $this->assertStringContainsString('list_price >= 500000', $result->where);
        $this->assertStringContainsString('list_price <= 1000000', $result->where);
        $this->assertSame('list_price ASC', $result->orderBy);
    }

    public function testSortParameterPassedThrough(): void
    {
        $result = $this->builder->build(['sort' => 'price_desc']);

        $this->assertSame('list_price DESC', $result->orderBy);
    }
}
