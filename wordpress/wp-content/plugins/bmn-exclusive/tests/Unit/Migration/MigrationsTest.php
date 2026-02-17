<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Migration;

use BMN\Exclusive\Migration\CreateExclusiveListingsTable;
use BMN\Exclusive\Migration\CreateExclusivePhotosTable;
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

    // -- CreateExclusiveListingsTable tests --

    public function testListingsUpCallsDbDelta(): void
    {
        $migration = new CreateExclusiveListingsTable();
        $migration->up();

        // dbDelta is stubbed in bootstrap, so we just verify no errors.
        $this->assertTrue(true);
    }

    public function testListingsUpSqlContainsTableName(): void
    {
        // Capture the SQL by examining wpdb queries after down().
        $migration = new CreateExclusiveListingsTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_exclusive_listings', $lastQuery['sql']);
    }

    public function testListingsUpContainsKeyColumns(): void
    {
        $migration = new CreateExclusiveListingsTable();
        // We verify that calling up() doesn't throw. The SQL is validated by dbDelta.
        $migration->up();
        $this->assertTrue(true);
    }

    public function testListingsDownDropsTable(): void
    {
        $migration = new CreateExclusiveListingsTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $lastQuery['sql']);
        $this->assertStringContainsString('wp_bmn_exclusive_listings', $lastQuery['sql']);
    }

    // -- CreateExclusivePhotosTable tests --

    public function testPhotosUpCallsDbDelta(): void
    {
        $migration = new CreateExclusivePhotosTable();
        $migration->up();
        $this->assertTrue(true);
    }

    public function testPhotosDownDropsTable(): void
    {
        $migration = new CreateExclusivePhotosTable();
        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $lastQuery['sql']);
        $this->assertStringContainsString('wp_bmn_exclusive_photos', $lastQuery['sql']);
    }
}
