<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Controller;

use BMN\CMA\Controller\MarketController;
use BMN\CMA\Service\MarketConditionsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class MarketControllerTest extends TestCase
{
    private MarketConditionsService&MockObject $marketService;
    private MarketController $controller;

    protected function setUp(): void
    {
        $this->marketService = $this->createMock(MarketConditionsService::class);
        $this->controller = new MarketController($this->marketService, null);
    }

    // ------------------------------------------------------------------
    // getConditions
    // ------------------------------------------------------------------

    public function testGetConditionsReturns422WhenCityMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions');

        $response = $this->controller->getConditions($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertStringContainsString('City query parameter', $data['meta']['error']);
    }

    public function testGetConditionsReturnsSuccessWithCity(): void
    {
        $conditions = [
            'city'            => 'Boston',
            'property_type'   => 'all',
            'active_listings' => 100,
            'trend'           => 'sellers',
        ];
        $this->marketService->method('getConditions')->willReturn($conditions);

        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions');
        $request->set_param('city', 'Boston');

        $response = $this->controller->getConditions($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('Boston', $data['data']['city']);
    }

    public function testGetConditionsPassesPropertyTypeFilter(): void
    {
        $this->marketService->expects($this->once())
            ->method('getConditions')
            ->with('Boston', 'Condo')
            ->willReturn(['city' => 'Boston', 'property_type' => 'Condo']);

        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions');
        $request->set_param('city', 'Boston');
        $request->set_param('property_type', 'Condo');

        $this->controller->getConditions($request);
    }

    // ------------------------------------------------------------------
    // getSummary
    // ------------------------------------------------------------------

    public function testGetSummaryReturns422WhenCityMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions/summary');

        $response = $this->controller->getSummary($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testGetSummaryReturnsSuccessWithCity(): void
    {
        $summary = ['city' => 'Boston', 'summary' => [], 'as_of' => '2025-06-01'];
        $this->marketService->method('getSummary')->willReturn($summary);

        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions/summary');
        $request->set_param('city', 'Boston');

        $response = $this->controller->getSummary($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    // ------------------------------------------------------------------
    // getTrends
    // ------------------------------------------------------------------

    public function testGetTrendsReturns422WhenCityMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions/trends');

        $response = $this->controller->getTrends($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testGetTrendsReturnsSuccessWithCity(): void
    {
        $trends = [(object) ['snapshot_date' => '2025-01-01']];
        $this->marketService->method('getHistoricalTrends')->willReturn($trends);

        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions/trends');
        $request->set_param('city', 'Boston');

        $response = $this->controller->getTrends($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('Boston', $data['data']['city']);
        $this->assertCount(1, $data['data']['trends']);
    }

    public function testGetTrendsClampsMonthsParameter(): void
    {
        $this->marketService->expects($this->once())
            ->method('getHistoricalTrends')
            ->with('Boston', 'all', 60) // max clamped to 60.
            ->willReturn([]);

        $request = new WP_REST_Request('GET', '/bmn/v1/market-conditions/trends');
        $request->set_param('city', 'Boston');
        $request->set_param('months', 120); // Above max.

        $response = $this->controller->getTrends($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(60, $data['data']['months']);
    }
}
