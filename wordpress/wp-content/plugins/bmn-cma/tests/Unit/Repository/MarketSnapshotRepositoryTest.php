<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Repository;

use BMN\CMA\Repository\MarketSnapshotRepository;
use PHPUnit\Framework\TestCase;

final class MarketSnapshotRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private MarketSnapshotRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $this->repo = new MarketSnapshotRepository($this->wpdb);
    }

    public function testGetLatestReturnsSnapshotObject(): void
    {
        $snapshot = (object) [
            'id'            => 1,
            'city'          => 'Boston',
            'property_type' => 'all',
            'median_price'  => 750000.00,
        ];
        $this->wpdb->get_row_result = $snapshot;

        $result = $this->repo->getLatest('Boston', 'all');

        $this->assertNotNull($result);
        $this->assertSame('Boston', $result->city);
        $this->assertSame(750000.00, $result->median_price);
    }

    public function testGetLatestReturnsNullWhenNoResult(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->getLatest('NowhereVille');

        $this->assertNull($result);
    }

    public function testGetLatestReturnNullOnFalsyResult(): void
    {
        // wpdb->get_row returns false when no matching row; Repository coerces to null.
        $this->wpdb->get_row_result = null;

        $result = $this->repo->getLatest('Boston', 'Condo');

        $this->assertNull($result);
    }

    public function testGetRangeReturnsSnapshots(): void
    {
        $s1 = (object) ['id' => 1, 'snapshot_date' => '2025-01-01'];
        $s2 = (object) ['id' => 2, 'snapshot_date' => '2025-06-01'];
        $this->wpdb->get_results_result = [$s1, $s2];

        $results = $this->repo->getRange('Boston', 'all', '2025-01-01', '2025-12-31');

        $this->assertCount(2, $results);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('BETWEEN', $lastQuery['sql']);
        $this->assertStringContainsString('ORDER BY snapshot_date ASC', $lastQuery['sql']);
    }

    public function testGetRangeReturnsEmptyOnNull(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->getRange('Boston', 'all', '2025-01-01', '2025-12-31');

        $this->assertSame([], $results);
    }

    public function testUpsertEncodesMetadataJsonAndReturnsTrue(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->upsert([
            'city'          => 'Boston',
            'property_type' => 'all',
            'snapshot_date' => '2025-06-01',
            'active_listings' => 100,
            'median_price'  => 750000.00,
            'metadata'      => ['source' => 'extractor'],
        ]);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_market_snapshots', $lastQuery['sql']);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $lastQuery['sql']);
    }

    public function testUpsertReturnsFalseOnFailure(): void
    {
        $this->wpdb->query_result = false;

        $result = $this->repo->upsert([
            'city'          => 'Boston',
            'snapshot_date' => '2025-06-01',
        ]);

        $this->assertFalse($result);
    }
}
