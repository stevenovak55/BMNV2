<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Service;

use BMN\Flip\Service\ArvService;
use PHPUnit\Framework\TestCase;

class ArvServiceTest extends TestCase
{
    private ArvService $service;
    private \wpdb $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->service = new ArvService($this->wpdb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    // ------------------------------------------------------------------
    // calculateArv
    // ------------------------------------------------------------------

    public function testCalculateArvWithComps(): void
    {
        // Return 3 comps from get_results (findComparables calls get_results).
        $this->wpdb->get_results_result = [
            (object) [
                'listing_id' => 'C1', 'close_price' => 500000, 'living_area' => 2000,
                'bedrooms_total' => 3, 'bathrooms_total' => 2, 'year_built' => 2000,
                'garage_spaces' => 1, 'lot_size_acres' => 0.25, 'property_type' => 'Single Family Residence',
                'distance_miles' => 0.3, 'close_date' => date('Y-m-d', strtotime('-2 months')),
                'remarks' => '', 'address' => '1 Main St', 'city' => 'Boston',
                'days_on_market' => 20,
            ],
            (object) [
                'listing_id' => 'C2', 'close_price' => 520000, 'living_area' => 2100,
                'bedrooms_total' => 3, 'bathrooms_total' => 2, 'year_built' => 2002,
                'garage_spaces' => 1, 'lot_size_acres' => 0.30, 'property_type' => 'Single Family Residence',
                'distance_miles' => 0.4, 'close_date' => date('Y-m-d', strtotime('-1 month')),
                'remarks' => '', 'address' => '2 Main St', 'city' => 'Boston',
                'days_on_market' => 25,
            ],
            (object) [
                'listing_id' => 'C3', 'close_price' => 510000, 'living_area' => 2050,
                'bedrooms_total' => 3, 'bathrooms_total' => 2, 'year_built' => 2001,
                'garage_spaces' => 1, 'lot_size_acres' => 0.28, 'property_type' => 'Single Family Residence',
                'distance_miles' => 0.5, 'close_date' => date('Y-m-d', strtotime('-3 months')),
                'remarks' => '', 'address' => '3 Main St', 'city' => 'Boston',
                'days_on_market' => 30,
            ],
        ];

        $subject = [
            'listing_id' => 'S1', 'latitude' => 42.36, 'longitude' => -71.06,
            'property_type' => 'Single Family Residence', 'bedrooms_total' => 3,
            'bathrooms_total' => 2, 'living_area' => 2000, 'year_built' => 2000,
            'garage_spaces' => 1, 'lot_size_acres' => 0.25,
        ];

        $result = $this->service->calculateArv($subject);

        $this->assertArrayHasKey('arv', $result);
        $this->assertGreaterThan(0, $result['arv']);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertContains($result['confidence'], ['high', 'medium', 'low', 'none']);
        $this->assertArrayHasKey('comp_count', $result);
        $this->assertEquals(3, $result['comp_count']);
        $this->assertArrayHasKey('avg_ppsf', $result);
        $this->assertGreaterThan(0, $result['avg_ppsf']);
        $this->assertArrayHasKey('comparables', $result);
        $this->assertCount(3, $result['comparables']);
    }

    public function testCalculateArvWithNoComps(): void
    {
        $this->wpdb->get_results_result = [];

        $subject = [
            'listing_id' => 'S1', 'latitude' => 42.36, 'longitude' => -71.06,
            'property_type' => 'Single Family Residence', 'bedrooms_total' => 3,
            'bathrooms_total' => 2, 'living_area' => 2000, 'year_built' => 2000,
            'garage_spaces' => 1, 'lot_size_acres' => 0.25,
        ];

        $result = $this->service->calculateArv($subject);

        $this->assertEquals(0.0, $result['arv']);
        $this->assertEquals('none', $result['confidence']);
        $this->assertEquals(0, $result['comp_count']);
        $this->assertEquals(0.0, $result['avg_ppsf']);
        $this->assertEmpty($result['comparables']);
        $this->assertNull($result['neighborhood_ceiling']);
    }

    // ------------------------------------------------------------------
    // findComparables
    // ------------------------------------------------------------------

    public function testFindComparables(): void
    {
        $this->wpdb->get_results_result = [
            (object) [
                'listing_id' => 'C1', 'close_price' => 500000, 'living_area' => 2000,
                'bedrooms_total' => 3, 'bathrooms_total' => 2, 'distance_miles' => 0.3,
            ],
        ];

        $subject = [
            'listing_id' => 'S1', 'latitude' => 42.36, 'longitude' => -71.06,
            'property_type' => 'Single Family Residence', 'bedrooms_total' => 3,
            'bathrooms_total' => 2,
        ];

        $result = $this->service->findComparables($subject, 1.0, 15);

        $this->assertCount(1, $result);

        // Verify the query used the Haversine formula.
        $this->assertNotEmpty($this->wpdb->queries);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('ACOS', $lastQuery['sql']);
        $this->assertStringContainsString('RADIANS', $lastQuery['sql']);
        $this->assertStringContainsString('3959', $lastQuery['sql']);
    }

    public function testFindComparablesReturnsEmptyArray(): void
    {
        $this->wpdb->get_results_result = null;

        $subject = [
            'listing_id' => 'S1', 'latitude' => 42.36, 'longitude' => -71.06,
            'property_type' => 'Single Family Residence', 'bedrooms_total' => 3,
            'bathrooms_total' => 2,
        ];

        $result = $this->service->findComparables($subject, 1.0, 15);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ------------------------------------------------------------------
    // calculateAdjustments
    // ------------------------------------------------------------------

    public function testCalculateAdjustmentsBedroom(): void
    {
        $subject = (object) [
            'bedrooms_total' => 4, 'bathrooms_total' => 2, 'living_area' => 2000,
            'year_built' => 2000, 'garage_spaces' => 1, 'lot_size_acres' => 0.25,
        ];
        $comp = (object) [
            'bedrooms_total' => 3, 'bathrooms_total' => 2, 'living_area' => 2000,
            'year_built' => 2000, 'garage_spaces' => 1, 'lot_size_acres' => 0.25,
            'close_price' => 500000,
        ];
        $avgPpsf = 250.0;

        $result = $this->service->calculateAdjustments($subject, $comp, $avgPpsf);

        $this->assertArrayHasKey('adjustments', $result);
        $this->assertArrayHasKey('bedroom', $result['adjustments']);
        $this->assertGreaterThan(0, $result['adjustments']['bedroom']); // subject has more beds
    }

    public function testCalculateAdjustmentsBathroom(): void
    {
        $subject = (object) [
            'bedrooms_total' => 3, 'bathrooms_total' => 3, 'living_area' => 2000,
            'year_built' => 2000, 'garage_spaces' => 1, 'lot_size_acres' => 0.25,
        ];
        $comp = (object) [
            'bedrooms_total' => 3, 'bathrooms_total' => 2, 'living_area' => 2000,
            'year_built' => 2000, 'garage_spaces' => 1, 'lot_size_acres' => 0.25,
            'close_price' => 500000,
        ];
        $avgPpsf = 250.0;

        $result = $this->service->calculateAdjustments($subject, $comp, $avgPpsf);

        $this->assertArrayHasKey('bathroom', $result['adjustments']);
        $this->assertGreaterThan(0, $result['adjustments']['bathroom']); // subject has more baths
    }

    public function testCalculateAdjustmentsSqft(): void
    {
        $subject = (object) [
            'bedrooms_total' => 3, 'bathrooms_total' => 2, 'living_area' => 2500,
            'year_built' => 2000, 'garage_spaces' => 1, 'lot_size_acres' => 0.25,
        ];
        $comp = (object) [
            'bedrooms_total' => 3, 'bathrooms_total' => 2, 'living_area' => 2000,
            'year_built' => 2000, 'garage_spaces' => 1, 'lot_size_acres' => 0.25,
            'close_price' => 500000,
        ];
        $avgPpsf = 250.0;

        $result = $this->service->calculateAdjustments($subject, $comp, $avgPpsf);

        $this->assertArrayHasKey('sqft', $result['adjustments']);
        $this->assertGreaterThan(0, $result['adjustments']['sqft']); // subject has more sqft

        // Verify sqft adjustment is capped at 15% of close_price.
        $cap = 500000 * 0.15;
        $this->assertLessThanOrEqual($cap, abs($result['adjustments']['sqft']));
    }

    public function testCalculateAdjustmentsTotal(): void
    {
        // Subject with more of everything -> all positive adjustments that
        // would exceed 25% cap when combined.
        $subject = (object) [
            'bedrooms_total' => 6, 'bathrooms_total' => 5, 'living_area' => 4000,
            'year_built' => 2020, 'garage_spaces' => 3, 'lot_size_acres' => 1.0,
        ];
        $comp = (object) [
            'bedrooms_total' => 2, 'bathrooms_total' => 1, 'living_area' => 1000,
            'year_built' => 1960, 'garage_spaces' => 0, 'lot_size_acres' => 0.10,
            'close_price' => 400000,
        ];
        $avgPpsf = 250.0;

        $result = $this->service->calculateAdjustments($subject, $comp, $avgPpsf);

        // Total adjustment should be capped at 25% of close_price.
        $maxAdj = 400000 * 0.25;
        $this->assertLessThanOrEqual($maxAdj, abs($result['total']));
        $this->assertArrayHasKey('adjusted_price', $result);
        $this->assertArrayHasKey('gross_pct', $result);
    }

    // ------------------------------------------------------------------
    // calculateWeight
    // ------------------------------------------------------------------

    public function testCalculateWeightDistance(): void
    {
        $closeComp = (object) [
            'close_date' => date('Y-m-d', strtotime('-1 month')),
            'distance_miles' => 0.2,
            'remarks' => '',
            'close_price' => 500000,
            'living_area' => 2000,
        ];
        $farComp = (object) [
            'close_date' => date('Y-m-d', strtotime('-1 month')),
            'distance_miles' => 5.0,
            'remarks' => '',
            'close_price' => 500000,
            'living_area' => 2000,
        ];

        $closeWeight = $this->service->calculateWeight($closeComp, 250.0);
        $farWeight = $this->service->calculateWeight($farComp, 250.0);

        // Closer comp should have higher weight.
        $this->assertGreaterThan($farWeight, $closeWeight);
        $this->assertGreaterThan(0, $closeWeight);
        $this->assertGreaterThan(0, $farWeight);
    }

    // ------------------------------------------------------------------
    // calculateConfidence
    // ------------------------------------------------------------------

    public function testCalculateConfidenceHigh(): void
    {
        // 8+ comps, close distance, recent, consistent prices -> high confidence (>=75).
        $comps = [];
        for ($i = 0; $i < 10; $i++) {
            $comps[] = (object) [
                'distance_miles' => 0.3,
                'close_date' => date('Y-m-d', strtotime('-1 month')),
                'close_price' => 500000 + ($i * 1000), // very tight variance
            ];
        }

        $subject = [
            'latitude' => 42.36, 'longitude' => -71.06,
            'property_type' => 'Single Family Residence',
        ];

        $result = $this->service->calculateConfidence($comps, $subject);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertGreaterThanOrEqual(75, $result['score']);
        $this->assertEquals('high', $result['level']);
    }

    public function testCalculateConfidenceLow(): void
    {
        // 2 comps, moderate distance, older sales, wide variance -> low confidence (20-49).
        $comps = [
            (object) [
                'distance_miles' => 3.0,
                'close_date' => date('Y-m-d', strtotime('-10 months')),
                'close_price' => 300000,
            ],
            (object) [
                'distance_miles' => 4.0,
                'close_date' => date('Y-m-d', strtotime('-11 months')),
                'close_price' => 600000, // wide variance
            ],
        ];

        $subject = [
            'latitude' => 42.36, 'longitude' => -71.06,
            'property_type' => 'Single Family Residence',
        ];

        $result = $this->service->calculateConfidence($comps, $subject);

        $this->assertGreaterThanOrEqual(20, $result['score']);
        $this->assertLessThan(50, $result['score']);
        $this->assertEquals('low', $result['level']);
    }

    // ------------------------------------------------------------------
    // getNeighborhoodCeiling
    // ------------------------------------------------------------------

    public function testGetNeighborhoodCeiling(): void
    {
        // Return a set of close prices for the ceiling calculation.
        $this->wpdb->get_results_result = [
            (object) ['close_price' => 400000],
            (object) ['close_price' => 450000],
            (object) ['close_price' => 480000],
            (object) ['close_price' => 500000],
            (object) ['close_price' => 520000],
            (object) ['close_price' => 540000],
            (object) ['close_price' => 560000],
            (object) ['close_price' => 580000],
            (object) ['close_price' => 600000],
            (object) ['close_price' => 700000],
        ];

        $result = $this->service->getNeighborhoodCeiling(42.36, -71.06, 'Single Family Residence');

        $this->assertNotNull($result);
        $this->assertIsFloat($result);
        // P90 of 10 items: index = ceil(0.90 * 10) - 1 = 8 -> 600000.
        $this->assertEquals(600000.0, $result);
    }
}
