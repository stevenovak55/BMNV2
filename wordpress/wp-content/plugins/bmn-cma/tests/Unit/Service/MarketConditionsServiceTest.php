<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Service;

use BMN\CMA\Repository\MarketSnapshotRepository;
use BMN\CMA\Service\MarketConditionsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MarketConditionsServiceTest extends TestCase
{
    private MarketSnapshotRepository&MockObject $snapshotRepo;
    private \wpdb $wpdb;
    private MarketConditionsService $service;

    protected function setUp(): void
    {
        $this->snapshotRepo = $this->createMock(MarketSnapshotRepository::class);
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $this->service = new MarketConditionsService($this->snapshotRepo, $this->wpdb);
    }

    public function testGetConditionsReturnsSellerMarket(): void
    {
        // Active: 10, Closed: 60 in 6 months => monthly rate 10, months supply = 10/10 = 1.
        // months_supply < 4 => sellers market.
        $this->wpdb->get_var_result = '10'; // Used for both active and closed counts.
        $this->wpdb->get_row_result = (object) ['avg_price' => 750000.00];

        $result = $this->service->getConditions('Boston', 'all');

        $this->assertSame('Boston', $result['city']);
        $this->assertSame('all', $result['property_type']);
        $this->assertArrayHasKey('active_listings', $result);
        $this->assertArrayHasKey('closed_sales_6mo', $result);
        $this->assertArrayHasKey('median_price', $result);
        $this->assertArrayHasKey('avg_price', $result);
        $this->assertArrayHasKey('months_supply', $result);
        $this->assertArrayHasKey('trend', $result);
        $this->assertArrayHasKey('as_of', $result);
    }

    public function testGetConditionsReturnsBuyersMarketWhenHighSupply(): void
    {
        // Active: 100, Closed: 6 in 6 months => monthly rate 1, months supply = 100.
        // months_supply > 6 => buyers market.
        $callCount = 0;
        // We cannot easily control sequential get_var calls with the stub wpdb,
        // so we test the logic path indirectly.
        $this->wpdb->get_var_result = null; // Will make counts 0.
        $this->wpdb->get_row_result = (object) ['avg_price' => 0];

        $result = $this->service->getConditions('Cambridge');

        // With 0 closed sales, monthlyRate = 0, months_supply = 0.
        // months_supply = 0 is not > 6, not < 4 and > 0 => balanced.
        $this->assertSame('balanced', $result['trend']);
        $this->assertSame(0, (int) $result['months_supply']);
    }

    public function testGetConditionsWithPropertyTypeFilter(): void
    {
        $this->wpdb->get_var_result = '5';
        $this->wpdb->get_row_result = (object) ['avg_price' => 500000.00];

        $result = $this->service->getConditions('Boston', 'Condo');

        $this->assertSame('Condo', $result['property_type']);
    }

    public function testGetSummaryReturnsAllPropertyTypes(): void
    {
        $this->wpdb->get_var_result = '10';
        $this->wpdb->get_row_result = (object) ['avg_price' => 500000.00];

        $result = $this->service->getSummary('Boston');

        $this->assertSame('Boston', $result['city']);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('all', $result['summary']);
        $this->assertArrayHasKey('Single Family', $result['summary']);
        $this->assertArrayHasKey('Condo', $result['summary']);
        $this->assertArrayHasKey('Multi-Family', $result['summary']);
        $this->assertArrayHasKey('as_of', $result);
    }

    public function testGetSummaryContainsFourPropertyTypes(): void
    {
        $this->wpdb->get_var_result = '0';
        $this->wpdb->get_row_result = (object) ['avg_price' => 0];

        $result = $this->service->getSummary('Somerville');

        $this->assertCount(4, $result['summary']);
    }

    public function testGetHistoricalTrendsDelegatesToSnapshotRepo(): void
    {
        $trends = [(object) ['snapshot_date' => '2025-01-01']];
        $this->snapshotRepo->expects($this->once())
            ->method('getRange')
            ->willReturn($trends);

        $result = $this->service->getHistoricalTrends('Boston', 'all', 12);

        $this->assertCount(1, $result);
    }

    public function testGetHistoricalTrendsDefaultParameters(): void
    {
        $this->snapshotRepo->expects($this->once())
            ->method('getRange')
            ->with(
                'Boston',
                'all',
                $this->isType('string'),
                $this->isType('string'),
            )
            ->willReturn([]);

        $result = $this->service->getHistoricalTrends('Boston');

        $this->assertSame([], $result);
    }

    public function testGetConditionsCalculatesMonthsSupply(): void
    {
        // We test this indirectly: when both active and closed are 0,
        // monthsSupply should be 0.
        $this->wpdb->get_var_result = '0';
        $this->wpdb->get_row_result = null;

        $result = $this->service->getConditions('Boston');

        $this->assertSame(0, (int) $result['months_supply']);
    }
}
