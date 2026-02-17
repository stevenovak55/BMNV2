<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Service;

use BMN\CMA\Service\ComparableSearchService;
use PHPUnit\Framework\TestCase;

final class ComparableSearchServiceTest extends TestCase
{
    private \wpdb $wpdb;
    private ComparableSearchService $service;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $this->service = new ComparableSearchService($this->wpdb);
    }

    public function testFindComparablesReturnsEmptyWhenLatitudeIsZero(): void
    {
        $subject = ['latitude' => 0, 'longitude' => -71.05, 'listing_id' => 'MLS100'];

        $result = $this->service->findComparables($subject);

        $this->assertSame([], $result);
    }

    public function testFindComparablesReturnsEmptyWhenLongitudeIsZero(): void
    {
        $subject = ['latitude' => 42.36, 'longitude' => 0, 'listing_id' => 'MLS100'];

        $result = $this->service->findComparables($subject);

        $this->assertSame([], $result);
    }

    public function testFindComparablesReturnsEmptyWhenListingIdEmpty(): void
    {
        $subject = ['latitude' => 42.36, 'longitude' => -71.05, 'listing_id' => ''];

        $result = $this->service->findComparables($subject);

        $this->assertSame([], $result);
    }

    public function testFindComparablesReturnsEmptyWhenMissingFields(): void
    {
        $subject = [];

        $result = $this->service->findComparables($subject);

        $this->assertSame([], $result);
    }

    public function testFindComparablesExecutesQueryWithValidSubject(): void
    {
        $comp = (object) ['listing_id' => 'MLS200', 'close_price' => 500000, 'distance_miles' => 0.5];
        $this->wpdb->get_results_result = [$comp];

        $subject = [
            'latitude'   => 42.36,
            'longitude'  => -71.05,
            'listing_id' => 'MLS100',
        ];

        $result = $this->service->findComparables($subject);

        $this->assertCount(1, $result);
        $this->assertSame('MLS200', $result[0]->listing_id);

        // Verify query structure.
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_properties', $lastQuery['sql']);
        $this->assertStringContainsString('distance_miles', $lastQuery['sql']);
        $this->assertStringContainsString('HAVING', $lastQuery['sql']);
    }

    public function testFindComparablesAppliesOptionalFilters(): void
    {
        $this->wpdb->get_results_result = [];

        $subject = [
            'latitude'        => 42.36,
            'longitude'       => -71.05,
            'listing_id'      => 'MLS100',
            'bedrooms_total'  => 3,
            'bathrooms_total' => 2,
            'living_area'     => 2000,
            'year_built'      => 2010,
        ];

        $filters = ['property_type' => 'Single Family'];

        $this->service->findComparables($subject, $filters);

        $lastQuery = end($this->wpdb->queries);
        $sql = $lastQuery['sql'];

        // Should contain filters for bedrooms, bathrooms, sqft, year, property type.
        $this->assertStringContainsString('bedrooms_total BETWEEN', $sql);
        $this->assertStringContainsString('bathrooms_total BETWEEN', $sql);
        $this->assertStringContainsString('living_area BETWEEN', $sql);
        $this->assertStringContainsString('year_built BETWEEN', $sql);
        $this->assertStringContainsString('property_type', $sql);
    }

    public function testFindComparablesReturnsEmptyOnNullResult(): void
    {
        $this->wpdb->get_results_result = null;

        $subject = [
            'latitude'   => 42.36,
            'longitude'  => -71.05,
            'listing_id' => 'MLS100',
        ];

        $result = $this->service->findComparables($subject);

        $this->assertSame([], $result);
    }

    public function testExpandSearchTriesMultipleRadii(): void
    {
        // First few calls return too few results, last returns enough.
        $callCount = 0;
        $this->wpdb->get_results_result = [];

        $subject = [
            'latitude'   => 42.36,
            'longitude'  => -71.05,
            'listing_id' => 'MLS100',
        ];

        // With 0 current count, it should try expanding.
        $result = $this->service->expandSearch($subject, [], 0);

        // Even if no results found, it should still return whatever was found at largest radius.
        $this->assertSame([], $result);

        // Should have executed multiple queries (one per radius tier + final).
        $this->assertGreaterThanOrEqual(5, count($this->wpdb->queries));
    }

    public function testExpandSearchReturnsEarlyWhenSufficientComps(): void
    {
        // If currentCount >= minComps, it should just call findComparables once.
        $comp = (object) ['listing_id' => 'MLS200'];
        $this->wpdb->get_results_result = [$comp];

        $subject = [
            'latitude'   => 42.36,
            'longitude'  => -71.05,
            'listing_id' => 'MLS100',
        ];

        $result = $this->service->expandSearch($subject, ['min_comps' => 1], 5);

        // Only one query should be executed (the initial findComparables).
        $this->assertCount(1, $this->wpdb->queries);
    }
}
