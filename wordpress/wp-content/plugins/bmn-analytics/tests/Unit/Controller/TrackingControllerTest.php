<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Controller;

use BMN\Analytics\Controller\TrackingController;
use BMN\Analytics\Service\TrackingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Tests for TrackingController.
 */
final class TrackingControllerTest extends TestCase
{
    private TrackingService&MockObject $trackingService;
    private TrackingController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackingService = $this->createMock(TrackingService::class);
        $this->controller = new TrackingController($this->trackingService, null);
    }

    // ------------------------------------------------------------------
    // recordEvent()
    // ------------------------------------------------------------------

    public function testRecordEventReturns201OnSuccess(): void
    {
        $this->trackingService->method('recordEvent')->willReturn(42);

        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/event');
        $request->set_param('event_type', 'search');
        $request->set_param('session_id', 'sess-abc');

        $response = $this->controller->recordEvent($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(42, $data['data']['event_id']);
    }

    public function testRecordEventReturns422WhenEventTypeMissing(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/event');
        // No event_type set.

        $response = $this->controller->recordEvent($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function testRecordEventReturns500OnServiceFailure(): void
    {
        $this->trackingService->method('recordEvent')->willReturn(false);

        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/event');
        $request->set_param('event_type', 'search');

        $response = $this->controller->recordEvent($request);

        $this->assertSame(500, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    // ------------------------------------------------------------------
    // recordPageview()
    // ------------------------------------------------------------------

    public function testRecordPageviewReturns201OnSuccess(): void
    {
        $this->trackingService->method('recordPageview')->willReturn(10);

        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/pageview');
        $request->set_param('path', '/listings');
        $request->set_param('session_id', 'sess-pv');

        $response = $this->controller->recordPageview($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(10, $data['data']['event_id']);
    }

    public function testRecordPageviewReturns422WhenPathMissing(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/pageview');

        $response = $this->controller->recordPageview($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testRecordPageviewPassesExtraFieldsToService(): void
    {
        $this->trackingService->expects($this->once())
            ->method('recordPageview')
            ->with(
                '/about',
                'sess-extra',
                5,
                $this->callback(function (array $extra): bool {
                    return $extra['ip_address'] === '10.0.0.1'
                        && $extra['user_agent'] === 'TestAgent';
                })
            )
            ->willReturn(99);

        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/pageview');
        $request->set_param('path', '/about');
        $request->set_param('session_id', 'sess-extra');
        $request->set_param('user_id', 5);
        $request->set_param('ip_address', '10.0.0.1');
        $request->set_param('user_agent', 'TestAgent');

        $this->controller->recordPageview($request);
    }

    // ------------------------------------------------------------------
    // recordPropertyView()
    // ------------------------------------------------------------------

    public function testRecordPropertyViewReturns201OnSuccess(): void
    {
        $this->trackingService->method('recordPropertyView')->willReturn(20);

        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/property-view');
        $request->set_param('listing_id', 'MLS12345');

        $response = $this->controller->recordPropertyView($request);

        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertSame(20, $data['data']['event_id']);
    }

    public function testRecordPropertyViewReturns422WhenListingIdMissing(): void
    {
        $request = new WP_REST_Request('POST', '/bmn/v1/analytics/property-view');

        $response = $this->controller->recordPropertyView($request);

        $this->assertSame(422, $response->get_status());
    }

    // ------------------------------------------------------------------
    // getActiveVisitors()
    // ------------------------------------------------------------------

    public function testGetActiveVisitorsReturnsCountWithDefaultWindow(): void
    {
        $this->trackingService->expects($this->once())
            ->method('getActiveVisitors')
            ->with(15)
            ->willReturn(8);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/active-visitors');

        $response = $this->controller->getActiveVisitors($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(8, $data['data']['active_visitors']);
        $this->assertSame(15, $data['data']['window_minutes']);
    }

    public function testGetActiveVisitorsUsesCustomMinutesParam(): void
    {
        $this->trackingService->expects($this->once())
            ->method('getActiveVisitors')
            ->with(30)
            ->willReturn(12);

        $request = new WP_REST_Request('GET', '/bmn/v1/analytics/active-visitors');
        $request->set_param('minutes', 30);

        $response = $this->controller->getActiveVisitors($request);

        $data = $response->get_data();
        $this->assertSame(12, $data['data']['active_visitors']);
        $this->assertSame(30, $data['data']['window_minutes']);
    }
}
