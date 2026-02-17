<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Service;

use BMN\CMA\Service\AdjustmentService;
use PHPUnit\Framework\TestCase;

final class AdjustmentServiceTest extends TestCase
{
    private AdjustmentService $service;

    protected function setUp(): void
    {
        $this->service = new AdjustmentService();
    }

    // ------------------------------------------------------------------
    // calculateAdjustments
    // ------------------------------------------------------------------

    public function testZeroClosePriceReturnsEmptyResult(): void
    {
        $subject = (object) ['bedrooms_total' => 3, 'living_area' => 1500];
        $comparable = (object) ['close_price' => 0, 'bedrooms_total' => 3];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $this->assertSame([], $result['adjustments']);
        $this->assertSame(0.0, $result['total']);
        $this->assertSame(0.0, $result['adjusted_price']);
        $this->assertSame(0.0, $result['gross_pct']);
    }

    public function testNegativeClosePriceReturnsEmptyResult(): void
    {
        $subject = (object) ['bedrooms_total' => 3];
        $comparable = (object) ['close_price' => -100000];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $this->assertSame(0.0, $result['adjusted_price']);
    }

    public function testBedroomAdjustmentPositive(): void
    {
        // Subject has more bedrooms => positive adjustment.
        $subject = (object) ['bedrooms_total' => 4];
        $comparable = (object) ['close_price' => 500000, 'bedrooms_total' => 3];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $this->assertArrayHasKey('bedrooms', $result['adjustments']);
        $adj = $result['adjustments']['bedrooms'];
        $this->assertSame(4, $adj['subject_value']);
        $this->assertSame(3, $adj['comp_value']);
        $this->assertSame(1, $adj['difference']);
        // 1 * 0.025 * 500000 = 12500
        $this->assertSame(12500.0, $adj['adjustment']);
    }

    public function testBedroomAdjustmentNegative(): void
    {
        // Subject has fewer bedrooms => negative adjustment.
        $subject = (object) ['bedrooms_total' => 2];
        $comparable = (object) ['close_price' => 400000, 'bedrooms_total' => 4];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['bedrooms'];
        $this->assertSame(-2, $adj['difference']);
        // -2 * 0.025 * 400000 = -20000
        $this->assertSame(-20000.0, $adj['adjustment']);
    }

    public function testBathroomAdjustment(): void
    {
        // Subject has 1 more bath.
        $subject = (object) ['bathrooms_total' => 2.5];
        $comparable = (object) ['close_price' => 600000, 'bathrooms_total' => 1.5];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['bathrooms'];
        $this->assertSame(2.5, $adj['subject_value']);
        $this->assertSame(1.5, $adj['comp_value']);
        // 1.0 * 0.01 * 600000 = 6000
        $this->assertSame(6000.0, $adj['adjustment']);
    }

    public function testSqftAdjustmentWithCapping(): void
    {
        // Subject is 50% larger => should be capped at +10%.
        $subject = (object) ['living_area' => 3000];
        $comparable = (object) ['close_price' => 500000, 'living_area' => 2000];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['sqft'];
        // Proportional is 50%, but capped at 10%.
        $this->assertSame(10.0, $adj['pct']);
        // 0.10 * 500000 = 50000
        $this->assertSame(50000.0, $adj['adjustment']);
    }

    public function testSqftAdjustmentWithinRange(): void
    {
        // Subject is 5% smaller => -5% (within bounds).
        $subject = (object) ['living_area' => 1900];
        $comparable = (object) ['close_price' => 500000, 'living_area' => 2000];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['sqft'];
        $this->assertSame(-5.0, $adj['pct']);
        $this->assertSame(-25000.0, $adj['adjustment']);
    }

    public function testYearBuiltAdjustment(): void
    {
        // Subject is 10 years newer => 10 * 0.004 = 4%.
        $subject = (object) ['year_built' => 2020];
        $comparable = (object) ['close_price' => 500000, 'year_built' => 2010];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['year_built'];
        $this->assertSame(10, $adj['difference']);
        $this->assertSame(4.0, $adj['pct']);
        $this->assertSame(20000.0, $adj['adjustment']);
    }

    public function testYearBuiltAdjustmentCappedAtTenPercent(): void
    {
        // 50 year difference => 50 * 0.004 = 20%, capped at 10%.
        $subject = (object) ['year_built' => 2020];
        $comparable = (object) ['close_price' => 500000, 'year_built' => 1970];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['year_built'];
        $this->assertSame(10.0, $adj['pct']);
        $this->assertSame(50000.0, $adj['adjustment']);
    }

    public function testGarageAdjustmentFirstSpace(): void
    {
        // Subject has 1 garage space, comp has 0.
        $subject = (object) ['garage_spaces' => 1];
        $comparable = (object) ['close_price' => 500000, 'garage_spaces' => 0];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['garage'];
        $this->assertSame(1, $adj['difference']);
        // 1 space: 0.025 * 500000 = 12500
        $this->assertSame(12500.0, $adj['adjustment']);
    }

    public function testGarageAdjustmentMultipleSpaces(): void
    {
        // Subject has 3 garage spaces, comp has 0.
        $subject = (object) ['garage_spaces' => 3];
        $comparable = (object) ['close_price' => 500000, 'garage_spaces' => 0];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['garage'];
        $this->assertSame(3, $adj['difference']);
        // First space: 0.025 * 500000 = 12500. Two additional: 2 * 0.015 * 500000 = 15000.
        $this->assertSame(27500.0, $adj['adjustment']);
    }

    public function testLotSizeAdjustment(): void
    {
        // Subject lot is 0.5 acres larger => 0.5/0.25 = 2 quarters => 2 * 0.02 = 4%.
        $subject = (object) ['lot_size_acres' => 1.0];
        $comparable = (object) ['close_price' => 500000, 'lot_size_acres' => 0.5];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        $adj = $result['adjustments']['lot_size'];
        $this->assertSame(4.0, $adj['pct']);
        $this->assertSame(20000.0, $adj['adjustment']);
    }

    public function testGrossAdjustmentCappingAt40Percent(): void
    {
        // Create large adjustments that exceed 40% gross.
        $subject = (object) [
            'bedrooms_total'  => 6,
            'bathrooms_total' => 5.0,
            'living_area'     => 4000,
            'year_built'      => 2025,
            'garage_spaces'   => 4,
            'lot_size_acres'  => 3.0,
        ];
        $comparable = (object) [
            'close_price'     => 500000,
            'bedrooms_total'  => 2,
            'bathrooms_total' => 1.0,
            'living_area'     => 1500,
            'year_built'      => 1970,
            'garage_spaces'   => 0,
            'lot_size_acres'  => 0.25,
        ];

        $result = $this->service->calculateAdjustments($subject, $comparable);

        // Gross percentage should be capped at exactly 40%.
        $this->assertSame(40.0, $result['gross_pct']);
    }

    public function testIdenticalPropertiesNoAdjustments(): void
    {
        $props = (object) [
            'close_price'     => 500000,
            'bedrooms_total'  => 3,
            'bathrooms_total' => 2.0,
            'living_area'     => 2000,
            'year_built'      => 2010,
            'garage_spaces'   => 2,
            'lot_size_acres'  => 0.5,
        ];

        $result = $this->service->calculateAdjustments($props, $props);

        // All adjustments should be zero.
        $this->assertSame(0.0, $result['total']);
        $this->assertSame(500000.0, $result['adjusted_price']);
        $this->assertSame(0.0, $result['gross_pct']);
    }

    // ------------------------------------------------------------------
    // calculateConfidence
    // ------------------------------------------------------------------

    public function testConfidenceWithNoComparables(): void
    {
        $subject = (object) ['bedrooms_total' => 3, 'bathrooms_total' => 2];
        $result = $this->service->calculateConfidence([], $subject);

        $this->assertSame(0.0, $result['factors']['sample_size']);
        $this->assertSame('insufficient', $result['level']);
    }

    public function testConfidenceWithHighSampleSize(): void
    {
        $comparables = [];
        for ($i = 0; $i < 10; $i++) {
            $comparables[] = (object) [
                'adjusted_price' => 500000,
                'close_price'    => 500000,
                'close_date'     => date('Y-m-d', strtotime('-1 month')),
                'distance_miles' => 0.3,
                'gross_pct'      => 5,
            ];
        }

        $subject = (object) [
            'bedrooms_total'  => 3,
            'bathrooms_total' => 2,
            'living_area'     => 2000,
            'year_built'      => 2010,
            'lot_size_acres'  => 0.5,
        ];

        $result = $this->service->calculateConfidence($comparables, $subject);

        // 10 comps => 25 points for sample size.
        $this->assertSame(25.0, $result['factors']['sample_size']);
        // All 5 fields filled => 20 points for completeness.
        $this->assertSame(20.0, $result['factors']['data_completeness']);
        // All identical prices => CV = 0 => 20 points.
        $this->assertSame(20.0, $result['factors']['market_stability']);
        $this->assertSame('high', $result['level']);
    }

    public function testConfidenceWithModerateSampleSize(): void
    {
        $comparables = [];
        for ($i = 0; $i < 5; $i++) {
            $comparables[] = (object) [
                'adjusted_price' => 500000,
                'close_price'    => 500000,
                'close_date'     => date('Y-m-d', strtotime('-2 months')),
                'distance_miles' => 1.5,
                'gross_pct'      => 12,
            ];
        }

        $subject = (object) [
            'bedrooms_total'  => 3,
            'bathrooms_total' => 2,
            'living_area'     => 2000,
            'year_built'      => 2010,
            'lot_size_acres'  => 0.5,
        ];

        $result = $this->service->calculateConfidence($comparables, $subject);

        // 5 comps => 15.0 + (5-5)*2 = 15.0
        $this->assertSame(15.0, $result['factors']['sample_size']);
    }

    // ------------------------------------------------------------------
    // getConfidenceLevel
    // ------------------------------------------------------------------

    public function testConfidenceLevelHigh(): void
    {
        $this->assertSame('high', $this->service->getConfidenceLevel(80.0));
        $this->assertSame('high', $this->service->getConfidenceLevel(95.0));
    }

    public function testConfidenceLevelMedium(): void
    {
        $this->assertSame('medium', $this->service->getConfidenceLevel(60.0));
        $this->assertSame('medium', $this->service->getConfidenceLevel(79.9));
    }

    public function testConfidenceLevelLow(): void
    {
        $this->assertSame('low', $this->service->getConfidenceLevel(40.0));
        $this->assertSame('low', $this->service->getConfidenceLevel(59.9));
    }

    public function testConfidenceLevelInsufficient(): void
    {
        $this->assertSame('insufficient', $this->service->getConfidenceLevel(0.0));
        $this->assertSame('insufficient', $this->service->getConfidenceLevel(39.9));
    }

    // ------------------------------------------------------------------
    // calculateValuation
    // ------------------------------------------------------------------

    public function testValuationWithNoPrices(): void
    {
        $result = $this->service->calculateValuation([]);

        $this->assertSame(0.0, $result['low']);
        $this->assertSame(0.0, $result['mid']);
        $this->assertSame(0.0, $result['high']);
    }

    public function testValuationWithSinglePrice(): void
    {
        $result = $this->service->calculateValuation([500000.0]);

        // Single comp => +/- 5%.
        $this->assertSame(475000.0, $result['low']);
        $this->assertSame(500000.0, $result['mid']);
        $this->assertSame(525000.0, $result['high']);
    }

    public function testValuationWithMultiplePrices(): void
    {
        $result = $this->service->calculateValuation([500000.0, 520000.0, 480000.0]);

        // Mean = 500000. Stddev calculated from variance.
        $this->assertSame(500000.0, $result['mid']);
        $this->assertTrue($result['low'] < $result['mid']);
        $this->assertTrue($result['high'] > $result['mid']);
        // low + high should be symmetric around mid.
        $this->assertEqualsWithDelta($result['mid'] * 2, $result['low'] + $result['high'], 0.01);
    }

    public function testValuationFiltersZeroPrices(): void
    {
        $result = $this->service->calculateValuation([0.0, 500000.0, 0.0]);

        // Only the 500000 price should be used => single comp logic.
        $this->assertSame(500000.0, $result['mid']);
    }

    // ------------------------------------------------------------------
    // gradeComparable
    // ------------------------------------------------------------------

    public function testGradeA(): void
    {
        $this->assertSame('A', $this->service->gradeComparable(0.0));
        $this->assertSame('A', $this->service->gradeComparable(9.99));
    }

    public function testGradeB(): void
    {
        $this->assertSame('B', $this->service->gradeComparable(10.0));
        $this->assertSame('B', $this->service->gradeComparable(14.99));
    }

    public function testGradeC(): void
    {
        $this->assertSame('C', $this->service->gradeComparable(15.0));
        $this->assertSame('C', $this->service->gradeComparable(24.99));
    }

    public function testGradeD(): void
    {
        $this->assertSame('D', $this->service->gradeComparable(25.0));
        $this->assertSame('D', $this->service->gradeComparable(34.99));
    }

    public function testGradeF(): void
    {
        $this->assertSame('F', $this->service->gradeComparable(35.0));
        $this->assertSame('F', $this->service->gradeComparable(100.0));
    }
}
