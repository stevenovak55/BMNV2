<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Migration;

use BMN\Analytics\Migration\CreateDailyAggregatesTable;
use BMN\Analytics\Migration\CreateEventsTable;
use BMN\Analytics\Migration\CreateSessionsTable;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all three analytics migration classes.
 */
final class MigrationsTest extends TestCase
{
    private \wpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up a real wpdb stub (from platform bootstrap) so that global $wpdb works.
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // CreateEventsTable
    // ------------------------------------------------------------------

    public function testEventsTableUpRunsWithoutError(): void
    {
        $migration = new CreateEventsTable();

        // up() calls dbDelta() which is stubbed to return [].
        $migration->up();

        // Verify a CREATE TABLE query was passed to dbDelta (via wpdb stub queries).
        // The stub's dbDelta returns [], but we verify no exception was thrown.
        $this->assertTrue(true, 'CreateEventsTable::up() executed without error.');
    }

    public function testEventsTableDownRunsDropQuery(): void
    {
        $migration = new CreateEventsTable();

        $migration->down();

        // Verify that a DROP TABLE query was issued.
        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $lastQuery['sql']);
        $this->assertStringContainsString('bmn_analytics_events', $lastQuery['sql']);
    }

    public function testEventsTableVersionReturnsClassName(): void
    {
        $migration = new CreateEventsTable();

        $this->assertSame('CreateEventsTable', $migration->getVersion());
    }

    // ------------------------------------------------------------------
    // CreateSessionsTable
    // ------------------------------------------------------------------

    public function testSessionsTableUpRunsWithoutError(): void
    {
        $migration = new CreateSessionsTable();

        $migration->up();

        $this->assertTrue(true, 'CreateSessionsTable::up() executed without error.');
    }

    public function testSessionsTableDownRunsDropQuery(): void
    {
        $migration = new CreateSessionsTable();

        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $lastQuery['sql']);
        $this->assertStringContainsString('bmn_analytics_sessions', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // CreateDailyAggregatesTable
    // ------------------------------------------------------------------

    public function testDailyAggregatesTableUpRunsWithoutError(): void
    {
        $migration = new CreateDailyAggregatesTable();

        $migration->up();

        $this->assertTrue(true, 'CreateDailyAggregatesTable::up() executed without error.');
    }

    public function testDailyAggregatesTableDownRunsDropQuery(): void
    {
        $migration = new CreateDailyAggregatesTable();

        $migration->down();

        $lastQuery = end($this->wpdb->queries);
        $this->assertNotFalse($lastQuery);
        $this->assertStringContainsString('DROP TABLE IF EXISTS', $lastQuery['sql']);
        $this->assertStringContainsString('bmn_analytics_daily', $lastQuery['sql']);
    }
}
