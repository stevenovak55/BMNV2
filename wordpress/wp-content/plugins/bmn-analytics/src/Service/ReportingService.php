<?php

declare(strict_types=1);

namespace BMN\Analytics\Service;

use BMN\Analytics\Repository\DailyAggregateRepository;
use BMN\Analytics\Repository\EventRepository;
use BMN\Analytics\Repository\SessionRepository;

/**
 * Service for generating analytics reports and aggregating daily metrics.
 *
 * Provides pre-built report methods for dashboard consumption and a
 * daily aggregation routine intended to be run via WP-Cron.
 */
class ReportingService
{
    private readonly EventRepository $eventRepo;
    private readonly SessionRepository $sessionRepo;
    private readonly DailyAggregateRepository $dailyRepo;

    public function __construct(
        EventRepository $eventRepo,
        SessionRepository $sessionRepo,
        DailyAggregateRepository $dailyRepo,
    ) {
        $this->eventRepo = $eventRepo;
        $this->sessionRepo = $sessionRepo;
        $this->dailyRepo = $dailyRepo;
    }

    /**
     * Get trend data for a date range.
     *
     * Returns daily (or weekly) metrics: pageviews, unique_visitors,
     * property_views, and searches from the daily aggregates table.
     *
     * @param string $startDate Start date (Y-m-d, inclusive).
     * @param string $endDate   End date (Y-m-d, exclusive).
     * @param string $interval  'day' or 'week'.
     *
     * @return array<string, mixed> Trend data keyed by metric type.
     */
    public function getTrends(string $startDate, string $endDate, string $interval = 'day'): array
    {
        $metricTypes = ['pageviews', 'unique_visitors', 'property_views', 'searches'];
        $trends = [];

        foreach ($metricTypes as $metricType) {
            $rows = $this->dailyRepo->getByDateRange($metricType, $startDate, $endDate);

            if ($interval === 'week') {
                $rows = $this->aggregateByWeek($rows);
            }

            $trends[$metricType] = array_map(static fn (object $row): array => [
                'date'  => $row->aggregate_date ?? $row->week_start ?? '',
                'value' => (int) ($row->metric_value ?? $row->total_value ?? 0),
            ], $rows);
        }

        // Include totals.
        $totals = $this->dailyRepo->getTotals($startDate, $endDate);
        $trends['totals'] = [];

        foreach ($totals as $row) {
            $trends['totals'][$row->metric_type] = (int) $row->total_value;
        }

        return $trends;
    }

    /**
     * Get the most viewed properties within a date range.
     *
     * @return object[] Each row has entity_id (listing_id) and view_count.
     */
    public function getTopProperties(string $startDate, string $endDate, int $limit = 10): array
    {
        return $this->eventRepo->getTopEntities('property_view', $startDate, $endDate, $limit);
    }

    /**
     * Get the most viewed pages within a date range.
     *
     * @return object[] Each row has entity_id (page path) and view_count.
     */
    public function getTopContent(string $startDate, string $endDate, int $limit = 10): array
    {
        return $this->eventRepo->getTopEntities('pageview', $startDate, $endDate, $limit);
    }

    /**
     * Get session traffic source breakdown for a date range.
     *
     * @return object[] Each row has traffic_source and session_count.
     */
    public function getTrafficSources(string $startDate, string $endDate): array
    {
        return $this->sessionRepo->getTrafficSources($startDate, $endDate);
    }

    /**
     * Get analytics for a specific property (by listing_id / MLS number).
     *
     * @return array{total_views: int, unique_viewers: int, recent_events: object[], referrers: array}
     */
    public function getPropertyStats(string $listingId): array
    {
        $recentEvents = $this->eventRepo->getRecentByEntity($listingId, 'property', 50);

        // Count total views.
        $totalViews = count($recentEvents);

        // Count unique viewers (distinct session_ids).
        $uniqueSessions = [];
        $referrers = [];

        foreach ($recentEvents as $event) {
            if (!empty($event->session_id)) {
                $uniqueSessions[$event->session_id] = true;
            }
            if (!empty($event->referrer)) {
                $referrers[$event->referrer] = ($referrers[$event->referrer] ?? 0) + 1;
            }
        }

        // Sort referrers by count descending.
        arsort($referrers);

        return [
            'listing_id'     => $listingId,
            'total_views'    => $totalViews,
            'unique_viewers' => count($uniqueSessions),
            'recent_events'  => $recentEvents,
            'referrers'      => $referrers,
        ];
    }

    /**
     * Roll up events for a specific date into daily aggregates.
     *
     * Counts events by type, counts distinct sessions, and upserts
     * the results into the daily aggregates table.
     *
     * Intended to be called via WP-Cron for the previous day.
     *
     * @param string $date The date to aggregate (Y-m-d).
     *
     * @return array<string, int> Map of metric_type => aggregated value.
     */
    public function aggregateDaily(string $date): array
    {
        $startDate = $date . ' 00:00:00';
        $endDate = $date . ' 23:59:59';
        $now = current_time('mysql');
        $results = [];

        // Pageviews.
        $pageviews = $this->eventRepo->countByType('pageview', $startDate, $endDate);
        $this->dailyRepo->upsert([
            'aggregate_date' => $date,
            'metric_type'    => 'pageviews',
            'metric_value'   => $pageviews,
            'dimension'      => null,
            'created_at'     => $now,
        ]);
        $results['pageviews'] = $pageviews;

        // Property views.
        $propertyViews = $this->eventRepo->countByType('property_view', $startDate, $endDate);
        $this->dailyRepo->upsert([
            'aggregate_date' => $date,
            'metric_type'    => 'property_views',
            'metric_value'   => $propertyViews,
            'dimension'      => null,
            'created_at'     => $now,
        ]);
        $results['property_views'] = $propertyViews;

        // Searches.
        $searches = $this->eventRepo->countByType('search', $startDate, $endDate);
        $this->dailyRepo->upsert([
            'aggregate_date' => $date,
            'metric_type'    => 'searches',
            'metric_value'   => $searches,
            'dimension'      => null,
            'created_at'     => $now,
        ]);
        $results['searches'] = $searches;

        // Unique visitors (distinct sessions for the day).
        $uniqueVisitors = $this->sessionRepo->countUnique($startDate, $endDate);
        $this->dailyRepo->upsert([
            'aggregate_date' => $date,
            'metric_type'    => 'unique_visitors',
            'metric_value'   => $uniqueVisitors,
            'dimension'      => null,
            'created_at'     => $now,
        ]);
        $results['unique_visitors'] = $uniqueVisitors;

        return $results;
    }

    /**
     * Aggregate daily rows into weekly buckets.
     *
     * Groups by ISO week and sums metric_value.
     *
     * @param object[] $rows Daily aggregate rows with aggregate_date and metric_value.
     *
     * @return object[] Weekly aggregate objects with week_start and total_value.
     */
    private function aggregateByWeek(array $rows): array
    {
        $weeks = [];

        foreach ($rows as $row) {
            $date = new \DateTimeImmutable($row->aggregate_date);
            // Monday of the ISO week.
            $weekStart = $date->modify('monday this week')->format('Y-m-d');

            if (!isset($weeks[$weekStart])) {
                $weeks[$weekStart] = 0;
            }

            $weeks[$weekStart] += (int) $row->metric_value;
        }

        $result = [];
        foreach ($weeks as $weekStart => $total) {
            $obj = new \stdClass();
            $obj->week_start = $weekStart;
            $obj->aggregate_date = $weekStart;
            $obj->total_value = $total;
            $obj->metric_value = $total;
            $result[] = $obj;
        }

        return $result;
    }
}
