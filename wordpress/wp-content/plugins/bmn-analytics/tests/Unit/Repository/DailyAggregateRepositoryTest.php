<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Repository;

use BMN\Analytics\Repository\DailyAggregateRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DailyAggregateRepository.
 */
final class DailyAggregateRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private DailyAggregateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';

        $this->repository = new DailyAggregateRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // upsert()
    // ------------------------------------------------------------------

    public function testUpsertReturnsTrueOnSuccess(): void
    {
        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('query')->willReturn(1);

        $result = $this->repository->upsert([
            'aggregate_date' => '2026-02-15',
            'metric_type'    => 'pageviews',
            'metric_value'   => 150,
            'dimension'      => null,
        ]);

        $this->assertTrue($result);
    }

    public function testUpsertReturnsFalseOnFailure(): void
    {
        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('query')->willReturn(false);

        $result = $this->repository->upsert([
            'aggregate_date' => '2026-02-15',
            'metric_type'    => 'pageviews',
            'metric_value'   => 100,
        ]);

        $this->assertFalse($result);
    }

    public function testUpsertJsonEncodesMetadataArray(): void
    {
        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function (string $sql): bool {
                    return str_contains($sql, 'ON DUPLICATE KEY UPDATE');
                }),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn('prepared-sql');

        $this->wpdb->method('query')->willReturn(1);

        $this->repository->upsert([
            'aggregate_date' => '2026-02-15',
            'metric_type'    => 'pageviews',
            'metric_value'   => 100,
            'metadata'       => ['source' => 'cron'],
        ]);
    }

    // ------------------------------------------------------------------
    // getByDateRange()
    // ------------------------------------------------------------------

    public function testGetByDateRangeReturnsRowsWithoutDimension(): void
    {
        $row = (object) [
            'aggregate_date' => '2026-02-15',
            'metric_type' => 'pageviews',
            'metric_value' => 120,
        ];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$row]);

        $result = $this->repository->getByDateRange('pageviews', '2026-02-01', '2026-02-28');

        $this->assertCount(1, $result);
        $this->assertSame('pageviews', $result[0]->metric_type);
    }

    public function testGetByDateRangeFiltersByDimensionWhenProvided(): void
    {
        $row = (object) [
            'aggregate_date' => '2026-02-15',
            'metric_type' => 'pageviews',
            'metric_value' => 50,
            'dimension' => '/listings',
        ];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$row]);

        $result = $this->repository->getByDateRange('pageviews', '2026-02-01', '2026-02-28', '/listings');

        $this->assertCount(1, $result);
        $this->assertSame('/listings', $result[0]->dimension);
    }

    // ------------------------------------------------------------------
    // getTotals()
    // ------------------------------------------------------------------

    public function testGetTotalsReturnsSummedMetrics(): void
    {
        $row1 = (object) ['metric_type' => 'pageviews', 'total_value' => 500];
        $row2 = (object) ['metric_type' => 'property_views', 'total_value' => 200];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$row1, $row2]);

        $result = $this->repository->getTotals('2026-02-01', '2026-02-28');

        $this->assertCount(2, $result);
        $this->assertSame('pageviews', $result[0]->metric_type);
        $this->assertSame(500, $result[0]->total_value);
    }

    // ------------------------------------------------------------------
    // getTopDimensions()
    // ------------------------------------------------------------------

    public function testGetTopDimensionsReturnsRankedDimensions(): void
    {
        $row1 = (object) ['dimension' => '/listings', 'total_value' => 300];
        $row2 = (object) ['dimension' => '/about', 'total_value' => 100];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$row1, $row2]);

        $result = $this->repository->getTopDimensions('pageviews', '2026-02-01', '2026-02-28', 10);

        $this->assertCount(2, $result);
        $this->assertSame('/listings', $result[0]->dimension);
    }
}
