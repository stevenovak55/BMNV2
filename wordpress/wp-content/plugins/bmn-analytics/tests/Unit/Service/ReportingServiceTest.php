<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Service;

use BMN\Analytics\Repository\DailyAggregateRepository;
use BMN\Analytics\Repository\EventRepository;
use BMN\Analytics\Repository\SessionRepository;
use BMN\Analytics\Service\ReportingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ReportingService.
 */
final class ReportingServiceTest extends TestCase
{
    private EventRepository&MockObject $eventRepo;
    private SessionRepository&MockObject $sessionRepo;
    private DailyAggregateRepository&MockObject $dailyRepo;
    private ReportingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepo = $this->createMock(EventRepository::class);
        $this->sessionRepo = $this->createMock(SessionRepository::class);
        $this->dailyRepo = $this->createMock(DailyAggregateRepository::class);

        $this->service = new ReportingService(
            $this->eventRepo,
            $this->sessionRepo,
            $this->dailyRepo,
        );
    }

    // ------------------------------------------------------------------
    // getTrends()
    // ------------------------------------------------------------------

    public function testGetTrendsReturnsDailyDataForAllMetricTypes(): void
    {
        $pageviewRow = (object) ['aggregate_date' => '2026-02-15', 'metric_value' => 100];

        // getByDateRange called 4 times (pageviews, unique_visitors, property_views, searches).
        $this->dailyRepo->method('getByDateRange')
            ->willReturn([$pageviewRow]);

        // getTotals called once.
        $totalsRow = (object) ['metric_type' => 'pageviews', 'total_value' => 700];
        $this->dailyRepo->method('getTotals')->willReturn([$totalsRow]);

        $result = $this->service->getTrends('2026-02-01', '2026-02-28', 'day');

        $this->assertArrayHasKey('pageviews', $result);
        $this->assertArrayHasKey('unique_visitors', $result);
        $this->assertArrayHasKey('property_views', $result);
        $this->assertArrayHasKey('searches', $result);
        $this->assertArrayHasKey('totals', $result);

        // Each metric should have transformed data.
        $this->assertCount(1, $result['pageviews']);
        $this->assertSame('2026-02-15', $result['pageviews'][0]['date']);
        $this->assertSame(100, $result['pageviews'][0]['value']);

        // Totals should be keyed by metric_type.
        $this->assertSame(700, $result['totals']['pageviews']);
    }

    public function testGetTrendsAggregatesByWeekWhenIntervalIsWeek(): void
    {
        // Provide two daily rows in the same ISO week.
        $row1 = (object) ['aggregate_date' => '2026-02-16', 'metric_value' => 50]; // Monday
        $row2 = (object) ['aggregate_date' => '2026-02-17', 'metric_value' => 30]; // Tuesday

        $this->dailyRepo->method('getByDateRange')->willReturn([$row1, $row2]);
        $this->dailyRepo->method('getTotals')->willReturn([]);

        $result = $this->service->getTrends('2026-02-16', '2026-02-23', 'week');

        // Both rows should be combined into one weekly bucket.
        $this->assertCount(1, $result['pageviews']);
        $this->assertSame(80, $result['pageviews'][0]['value']);
    }

    // ------------------------------------------------------------------
    // getTopProperties()
    // ------------------------------------------------------------------

    public function testGetTopPropertiesDelegatesToEventRepository(): void
    {
        $row = (object) ['entity_id' => 'MLS123', 'view_count' => 50];

        $this->eventRepo->expects($this->once())
            ->method('getTopEntities')
            ->with('property_view', '2026-02-01', '2026-02-28', 10)
            ->willReturn([$row]);

        $result = $this->service->getTopProperties('2026-02-01', '2026-02-28', 10);

        $this->assertCount(1, $result);
        $this->assertSame('MLS123', $result[0]->entity_id);
    }

    // ------------------------------------------------------------------
    // getTopContent()
    // ------------------------------------------------------------------

    public function testGetTopContentDelegatesToEventRepository(): void
    {
        $row = (object) ['entity_id' => '/listings', 'view_count' => 200];

        $this->eventRepo->expects($this->once())
            ->method('getTopEntities')
            ->with('pageview', '2026-02-01', '2026-02-28', 5)
            ->willReturn([$row]);

        $result = $this->service->getTopContent('2026-02-01', '2026-02-28', 5);

        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // getTrafficSources()
    // ------------------------------------------------------------------

    public function testGetTrafficSourcesDelegatesToSessionRepository(): void
    {
        $row = (object) ['traffic_source' => 'organic', 'session_count' => 100];

        $this->sessionRepo->expects($this->once())
            ->method('getTrafficSources')
            ->with('2026-02-01', '2026-02-28')
            ->willReturn([$row]);

        $result = $this->service->getTrafficSources('2026-02-01', '2026-02-28');

        $this->assertCount(1, $result);
        $this->assertSame('organic', $result[0]->traffic_source);
    }

    // ------------------------------------------------------------------
    // getPropertyStats()
    // ------------------------------------------------------------------

    public function testGetPropertyStatsReturnsStructuredData(): void
    {
        $event1 = (object) [
            'id' => 1,
            'session_id' => 'sess-a',
            'referrer' => 'https://google.com',
            'event_type' => 'property_view',
        ];
        $event2 = (object) [
            'id' => 2,
            'session_id' => 'sess-b',
            'referrer' => 'https://google.com',
            'event_type' => 'property_view',
        ];
        $event3 = (object) [
            'id' => 3,
            'session_id' => 'sess-a',
            'referrer' => 'https://facebook.com',
            'event_type' => 'property_view',
        ];

        $this->eventRepo->method('getRecentByEntity')
            ->with('MLS789', 'property', 50)
            ->willReturn([$event1, $event2, $event3]);

        $result = $this->service->getPropertyStats('MLS789');

        $this->assertSame('MLS789', $result['listing_id']);
        $this->assertSame(3, $result['total_views']);
        $this->assertSame(2, $result['unique_viewers']); // sess-a and sess-b
        $this->assertCount(3, $result['recent_events']);
        $this->assertArrayHasKey('https://google.com', $result['referrers']);
        $this->assertSame(2, $result['referrers']['https://google.com']);
        $this->assertSame(1, $result['referrers']['https://facebook.com']);
    }

    public function testGetPropertyStatsHandlesEmptyEvents(): void
    {
        $this->eventRepo->method('getRecentByEntity')->willReturn([]);

        $result = $this->service->getPropertyStats('MLS999');

        $this->assertSame(0, $result['total_views']);
        $this->assertSame(0, $result['unique_viewers']);
        $this->assertEmpty($result['recent_events']);
        $this->assertEmpty($result['referrers']);
    }

    // ------------------------------------------------------------------
    // aggregateDaily()
    // ------------------------------------------------------------------

    public function testAggregateDailyUpsertsAllMetricsForDate(): void
    {
        $this->eventRepo->method('countByType')
            ->willReturnMap([
                ['pageview', '2026-02-15 00:00:00', '2026-02-15 23:59:59', 150],
                ['property_view', '2026-02-15 00:00:00', '2026-02-15 23:59:59', 40],
                ['search', '2026-02-15 00:00:00', '2026-02-15 23:59:59', 20],
            ]);

        $this->sessionRepo->method('countUnique')
            ->with('2026-02-15 00:00:00', '2026-02-15 23:59:59')
            ->willReturn(80);

        // upsert called 4 times: pageviews, property_views, searches, unique_visitors.
        $this->dailyRepo->expects($this->exactly(4))
            ->method('upsert')
            ->willReturn(true);

        $result = $this->service->aggregateDaily('2026-02-15');

        $this->assertSame(150, $result['pageviews']);
        $this->assertSame(40, $result['property_views']);
        $this->assertSame(20, $result['searches']);
        $this->assertSame(80, $result['unique_visitors']);
    }
}
