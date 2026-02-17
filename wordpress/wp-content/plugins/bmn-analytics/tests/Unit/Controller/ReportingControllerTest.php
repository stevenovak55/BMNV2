<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Controller;

use BMN\Analytics\Controller\ReportingController;
use BMN\Analytics\Service\ReportingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Tests for ReportingController.
 */
final class ReportingControllerTest extends TestCase
{
    private ReportingService&MockObject $reportingService;
    private ReportingController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportingService = $this->createMock(ReportingService::class);
        $this->controller = new ReportingController($this->reportingService, null);
    }

    // ------------------------------------------------------------------
    // getTrends()
    // ------------------------------------------------------------------

    public function testGetTrendsReturns200WithTrendData(): void
    {
        $trends = [
            'pageviews' => [['date' => '2026-02-15', 'value' => 100]],
            'totals'    => ['pageviews' => 700],
        ];

        $this->reportingService->expects($this->once())
            ->method('getTrends')
            ->with('2026-02-01', '2026-02-28', 'day')
            ->willReturn($trends);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/trends');
        $request->set_param('start_date', '2026-02-01');
        $request->set_param('end_date', '2026-02-28');

        $response = $this->controller->getTrends($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame($trends, $data['data']);
    }

    public function testGetTrendsReturns422WhenStartDateMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/trends');
        $request->set_param('end_date', '2026-02-28');

        $response = $this->controller->getTrends($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testGetTrendsReturns422WhenEndDateMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/trends');
        $request->set_param('start_date', '2026-02-01');

        $response = $this->controller->getTrends($request);

        $this->assertSame(422, $response->get_status());
    }

    // ------------------------------------------------------------------
    // getTopProperties()
    // ------------------------------------------------------------------

    public function testGetTopPropertiesReturns200WithResults(): void
    {
        $properties = [(object) ['entity_id' => 'MLS123', 'view_count' => 50]];

        $this->reportingService->expects($this->once())
            ->method('getTopProperties')
            ->with('2026-02-01', '2026-02-28', 10)
            ->willReturn($properties);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/top-properties');
        $request->set_param('start_date', '2026-02-01');
        $request->set_param('end_date', '2026-02-28');

        $response = $this->controller->getTopProperties($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testGetTopPropertiesUsesCustomLimit(): void
    {
        $this->reportingService->expects($this->once())
            ->method('getTopProperties')
            ->with('2026-02-01', '2026-02-28', 5)
            ->willReturn([]);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/top-properties');
        $request->set_param('start_date', '2026-02-01');
        $request->set_param('end_date', '2026-02-28');
        $request->set_param('limit', 5);

        $this->controller->getTopProperties($request);
    }

    // ------------------------------------------------------------------
    // getTopContent()
    // ------------------------------------------------------------------

    public function testGetTopContentReturns200WithResults(): void
    {
        $content = [(object) ['entity_id' => '/listings', 'view_count' => 200]];

        $this->reportingService->method('getTopContent')->willReturn($content);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/top-content');
        $request->set_param('start_date', '2026-02-01');
        $request->set_param('end_date', '2026-02-28');

        $response = $this->controller->getTopContent($request);

        $this->assertSame(200, $response->get_status());
    }

    // ------------------------------------------------------------------
    // getTrafficSources()
    // ------------------------------------------------------------------

    public function testGetTrafficSourcesReturns200WithResults(): void
    {
        $sources = [(object) ['traffic_source' => 'organic', 'session_count' => 100]];

        $this->reportingService->expects($this->once())
            ->method('getTrafficSources')
            ->with('2026-02-01', '2026-02-28')
            ->willReturn($sources);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/traffic-sources');
        $request->set_param('start_date', '2026-02-01');
        $request->set_param('end_date', '2026-02-28');

        $response = $this->controller->getTrafficSources($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testGetTrafficSourcesReturns422WhenDatesAreMissing(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/traffic-sources');

        $response = $this->controller->getTrafficSources($request);

        $this->assertSame(422, $response->get_status());
    }

    // ------------------------------------------------------------------
    // getPropertyStats()
    // ------------------------------------------------------------------

    public function testGetPropertyStatsReturns200WithStats(): void
    {
        $stats = [
            'listing_id'     => 'MLS789',
            'total_views'    => 10,
            'unique_viewers' => 5,
            'recent_events'  => [],
            'referrers'      => [],
        ];

        $this->reportingService->expects($this->once())
            ->method('getPropertyStats')
            ->with('MLS789')
            ->willReturn($stats);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/property/MLS789');
        $request->set_param('listing_id', 'MLS789');

        $response = $this->controller->getPropertyStats($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame('MLS789', $data['data']['listing_id']);
    }

    public function testGetPropertyStatsReturns422WhenListingIdIsEmpty(): void
    {
        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/property/');
        $request->set_param('listing_id', '');

        $response = $this->controller->getPropertyStats($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }
}
