<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Repository;

use BMN\Flip\Repository\MonitorSeenRepository;
use PHPUnit\Framework\TestCase;

final class MonitorSeenRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private MonitorSeenRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->repo = new MonitorSeenRepository($this->wpdb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    // ------------------------------------------------------------------
    // Table name
    // ------------------------------------------------------------------

    public function testTableName(): void
    {
        $this->wpdb->get_col_result = [];
        $this->repo->getSeenListings(1);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_flip_monitor_seen', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // markSeen()
    // ------------------------------------------------------------------

    public function testMarkSeen(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->markSeen(10, 'MLS123', 82.5);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_flip_monitor_seen', $lastQuery['sql']);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // isNewListing()
    // ------------------------------------------------------------------

    public function testIsNewListingReturnsTrue(): void
    {
        // COUNT(*) returns 0 — listing has not been seen before.
        $this->wpdb->get_var_result = '0';

        $result = $this->repo->isNewListing(10, 'MLS999');

        $this->assertTrue($result);
    }

    public function testIsNewListingReturnsFalse(): void
    {
        // COUNT(*) returns 1 — listing has already been seen.
        $this->wpdb->get_var_result = '1';

        $result = $this->repo->isNewListing(10, 'MLS123');

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // getSeenListings()
    // ------------------------------------------------------------------

    public function testGetSeenListings(): void
    {
        $this->wpdb->get_col_result = ['MLS100', 'MLS200', 'MLS300'];

        $results = $this->repo->getSeenListings(10);

        $this->assertCount(3, $results);
        $this->assertSame('MLS100', $results[0]);
        $this->assertSame('MLS200', $results[1]);
        $this->assertSame('MLS300', $results[2]);

        // Verify query references the correct table and report_id filter.
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('listing_id', $lastQuery['sql']);
        $this->assertStringContainsString('report_id', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // deleteByReport()
    // ------------------------------------------------------------------

    public function testDeleteByReport(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->deleteByReport(10);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DELETE FROM wp_bmn_flip_monitor_seen', $lastQuery['sql']);
        $this->assertStringContainsString('report_id', $lastQuery['sql']);
    }
}
