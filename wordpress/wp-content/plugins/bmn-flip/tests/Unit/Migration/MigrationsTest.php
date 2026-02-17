<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Migration;

use BMN\Flip\Migration\CreateFlipAnalysesTable;
use BMN\Flip\Migration\CreateFlipComparablesTable;
use BMN\Flip\Migration\CreateFlipReportsTable;
use BMN\Flip\Migration\CreateMonitorSeenTable;
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
    // CreateFlipAnalysesTable
    // ------------------------------------------------------------------

    public function testCreateFlipAnalysesTableUp(): void
    {
        $migration = new CreateFlipAnalysesTable();
        $migration->up();

        // dbDelta is a stub that returns []; verify no exception was thrown.
        $this->assertTrue(true);
    }

    public function testCreateFlipAnalysesTableDown(): void
    {
        $migration = new CreateFlipAnalysesTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_flip_analyses', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // CreateFlipComparablesTable
    // ------------------------------------------------------------------

    public function testCreateFlipComparablesTableUp(): void
    {
        $migration = new CreateFlipComparablesTable();
        $migration->up();

        $this->assertTrue(true);
    }

    public function testCreateFlipComparablesTableDown(): void
    {
        $migration = new CreateFlipComparablesTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_flip_comparables', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // CreateFlipReportsTable
    // ------------------------------------------------------------------

    public function testCreateFlipReportsTableUp(): void
    {
        $migration = new CreateFlipReportsTable();
        $migration->up();

        $this->assertTrue(true);
    }

    public function testCreateFlipReportsTableDown(): void
    {
        $migration = new CreateFlipReportsTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_flip_reports', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // CreateMonitorSeenTable
    // ------------------------------------------------------------------

    public function testCreateMonitorSeenTableUp(): void
    {
        $migration = new CreateMonitorSeenTable();
        $migration->up();

        $this->assertTrue(true);
    }

    public function testCreateMonitorSeenTableDown(): void
    {
        $migration = new CreateMonitorSeenTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS wp_bmn_flip_monitor_seen', $lastQuery['sql']);
    }
}
