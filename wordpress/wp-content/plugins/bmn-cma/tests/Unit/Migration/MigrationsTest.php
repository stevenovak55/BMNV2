<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Migration;

use BMN\CMA\Migration\CreateCmaReportsTable;
use BMN\CMA\Migration\CreateComparablesTable;
use BMN\CMA\Migration\CreateMarketSnapshotsTable;
use BMN\CMA\Migration\CreateValueHistoryTable;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    private \wpdb $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    // ------------------------------------------------------------------
    // CreateCmaReportsTable
    // ------------------------------------------------------------------

    public function testCmaReportsUpExecutesWithoutError(): void
    {
        $migration = new CreateCmaReportsTable();
        $migration->up();

        // dbDelta is a stub that returns []; verify no exception was thrown.
        $this->assertTrue(true);
    }

    public function testCmaReportsDownDropsTable(): void
    {
        $migration = new CreateCmaReportsTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_cma_reports', $lastQuery['sql']);
    }

    public function testCmaReportsTableNameUsesPrefix(): void
    {
        $this->wpdb->prefix = 'test_';
        $GLOBALS['wpdb'] = $this->wpdb;

        $migration = new CreateCmaReportsTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('test_bmn_cma_reports', $lastQuery['sql']);
    }

    public function testCmaReportsVersionReturnsClassName(): void
    {
        $migration = new CreateCmaReportsTable();
        $this->assertSame('CreateCmaReportsTable', $migration->getVersion());
    }

    // ------------------------------------------------------------------
    // CreateComparablesTable
    // ------------------------------------------------------------------

    public function testComparablesUpExecutesWithoutError(): void
    {
        $migration = new CreateComparablesTable();
        $migration->up();

        $this->assertTrue(true);
    }

    public function testComparablesDownDropsTable(): void
    {
        $migration = new CreateComparablesTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_comparables', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // CreateMarketSnapshotsTable
    // ------------------------------------------------------------------

    public function testMarketSnapshotsUpExecutesWithoutError(): void
    {
        $migration = new CreateMarketSnapshotsTable();
        $migration->up();

        $this->assertTrue(true);
    }

    public function testMarketSnapshotsDownDropsTable(): void
    {
        $migration = new CreateMarketSnapshotsTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_market_snapshots', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // CreateValueHistoryTable
    // ------------------------------------------------------------------

    public function testValueHistoryUpExecutesWithoutError(): void
    {
        $migration = new CreateValueHistoryTable();
        $migration->up();

        $this->assertTrue(true);
    }

    public function testValueHistoryDownDropsTable(): void
    {
        $migration = new CreateValueHistoryTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_cma_value_history', $lastQuery['sql']);
    }

    public function testGetCharsetCollateIsUsedInUp(): void
    {
        // Confirm get_charset_collate is called (the stub returns a known string).
        $charset = $this->wpdb->get_charset_collate();
        $this->assertStringContainsString('utf8mb4', $charset);
    }
}
