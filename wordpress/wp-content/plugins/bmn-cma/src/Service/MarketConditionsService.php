<?php

declare(strict_types=1);

namespace BMN\CMA\Service;

use BMN\CMA\Repository\MarketSnapshotRepository;
use wpdb;

/**
 * Computes live market conditions from extractor data and stored snapshots.
 */
class MarketConditionsService
{
    private readonly MarketSnapshotRepository $snapshotRepo;
    private readonly wpdb $wpdb;
    private readonly string $propertiesTable;

    public function __construct(
        MarketSnapshotRepository $snapshotRepo,
        wpdb $wpdb,
    ) {
        $this->snapshotRepo = $snapshotRepo;
        $this->wpdb = $wpdb;
        $this->propertiesTable = $this->wpdb->prefix . 'bmn_properties';
    }

    /**
     * Get current market conditions for a city and property type.
     *
     * Queries bmn_properties for live stats: active count, closed count,
     * median/avg prices, DOM, months supply, and trend assessment.
     */
    public function getConditions(string $city, string $propertyType = 'all'): array
    {
        $now = current_time('mysql');

        // Active listings count.
        $activeWhere = "standard_status = 'Active' AND city = %s";
        $activeParams = [$city];
        if ($propertyType !== 'all') {
            $activeWhere .= ' AND property_type = %s';
            $activeParams[] = $propertyType;
        }

        $activeCount = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->propertiesTable} WHERE {$activeWhere}",
                ...$activeParams
            )
        );

        // Closed sales in last 6 months.
        $closedWhere = "standard_status = 'Closed' AND city = %s AND close_date >= DATE_SUB(%s, INTERVAL 6 MONTH)";
        $closedParams = [$city, $now];
        if ($propertyType !== 'all') {
            $closedWhere .= ' AND property_type = %s';
            $closedParams[] = $propertyType;
        }

        $closedCount = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->propertiesTable} WHERE {$closedWhere}",
                ...$closedParams
            )
        );

        // Median and average close price (last 6 months).
        $priceStats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT AVG(close_price) AS avg_price
                 FROM {$this->propertiesTable}
                 WHERE {$closedWhere} AND close_price > 0",
                ...$closedParams
            )
        );

        // Median close price via subquery.
        $medianPrice = $this->calculateMedian($closedWhere, $closedParams, 'close_price');

        // Median days on market.
        $medianDom = $this->calculateMedian($closedWhere, $closedParams, 'days_on_market');

        // Calculate months supply.
        $monthlyRate = $closedCount > 0 ? $closedCount / 6.0 : 0;
        $monthsSupply = $monthlyRate > 0 ? round($activeCount / $monthlyRate, 1) : 0;

        // Determine market trend.
        if ($monthsSupply > 0 && $monthsSupply < 4) {
            $trend = 'sellers';
        } elseif ($monthsSupply > 6) {
            $trend = 'buyers';
        } else {
            $trend = 'balanced';
        }

        return [
            'city'            => $city,
            'property_type'   => $propertyType,
            'active_listings' => $activeCount,
            'closed_sales_6mo' => $closedCount,
            'median_price'    => $medianPrice,
            'avg_price'       => $priceStats !== null ? round((float) ($priceStats->avg_price ?? 0), 2) : 0,
            'median_dom'      => $medianDom,
            'months_supply'   => $monthsSupply,
            'trend'           => $trend,
            'as_of'           => $now,
        ];
    }

    /**
     * Get market summary for all property types in a city.
     */
    public function getSummary(string $city): array
    {
        $propertyTypes = ['all', 'Single Family', 'Condo', 'Multi-Family'];
        $summary = [];

        foreach ($propertyTypes as $type) {
            $summary[$type] = $this->getConditions($city, $type);
        }

        return [
            'city'    => $city,
            'summary' => $summary,
            'as_of'   => current_time('mysql'),
        ];
    }

    /**
     * Get historical trend data from stored snapshots.
     *
     * @return object[]
     */
    public function getHistoricalTrends(string $city, string $propertyType = 'all', int $months = 12): array
    {
        $endDate = current_time('mysql');
        $startDate = gmdate('Y-m-d', strtotime("-{$months} months", strtotime($endDate)));

        return $this->snapshotRepo->getRange($city, $propertyType, $startDate, $endDate);
    }

    /**
     * Calculate median for a numeric column using the given WHERE clause.
     *
     * @return float|null
     */
    private function calculateMedian(string $where, array $params, string $column): ?float
    {
        $countSql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->propertiesTable} WHERE {$where} AND {$column} > 0",
            ...$params
        );

        $total = (int) $this->wpdb->get_var($countSql);

        if ($total === 0) {
            return null;
        }

        $offset = (int) floor($total / 2);

        if ($total % 2 === 1) {
            // Odd count: middle value.
            $medianSql = $this->wpdb->prepare(
                "SELECT {$column} FROM {$this->propertiesTable}
                 WHERE {$where} AND {$column} > 0
                 ORDER BY {$column} ASC
                 LIMIT 1 OFFSET %d",
                ...array_merge($params, [$offset])
            );

            return (float) $this->wpdb->get_var($medianSql);
        }

        // Even count: average of two middle values.
        $medianSql = $this->wpdb->prepare(
            "SELECT {$column} FROM {$this->propertiesTable}
             WHERE {$where} AND {$column} > 0
             ORDER BY {$column} ASC
             LIMIT 2 OFFSET %d",
            ...array_merge($params, [$offset - 1])
        );

        $rows = $this->wpdb->get_col($medianSql);

        if (count($rows) < 2) {
            return !empty($rows) ? (float) $rows[0] : null;
        }

        return round(((float) $rows[0] + (float) $rows[1]) / 2, 2);
    }
}
