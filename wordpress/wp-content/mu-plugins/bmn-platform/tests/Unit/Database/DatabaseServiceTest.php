<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Database;

use BMN\Platform\Database\DatabaseService;
use BMN\Platform\Database\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DatabaseService.
 *
 * Tests table prefixing, health checks, table existence queries,
 * batch insert/update operations, query builder instantiation,
 * and timezone configuration.
 */
class DatabaseServiceTest extends TestCase
{
    private \wpdb $wpdb;
    private DatabaseService $db;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->db = new DatabaseService($this->wpdb);
    }

    // ------------------------------------------------------------------
    // 1. getTable prepends prefix
    // ------------------------------------------------------------------

    public function testGetTablePrependsPrefix(): void
    {
        $result = $this->db->getTable('bmn_properties');

        $this->assertSame('wp_bmn_properties', $result, 'getTable should prepend the wpdb prefix.');
    }

    // ------------------------------------------------------------------
    // 2. getWpdb returns the wpdb instance
    // ------------------------------------------------------------------

    public function testGetWpdbReturnsInstance(): void
    {
        $result = $this->db->getWpdb();

        $this->assertSame($this->wpdb, $result, 'getWpdb should return the injected wpdb instance.');
    }

    // ------------------------------------------------------------------
    // 3. healthCheck returns true when connected
    // ------------------------------------------------------------------

    public function testHealthCheckReturnsTrueWhenConnected(): void
    {
        $this->wpdb->get_var_result = '1';

        $health = $this->db->healthCheck();

        $this->assertTrue($health['connected'], 'healthCheck should report connected when SELECT 1 returns "1".');
        $this->assertSame('wp_', $health['prefix']);
        $this->assertSame('utf8mb4', $health['charset']);
    }

    // ------------------------------------------------------------------
    // 4. healthCheck returns false when disconnected
    // ------------------------------------------------------------------

    public function testHealthCheckReturnsFalseWhenDisconnected(): void
    {
        $this->wpdb->get_var_result = null;

        $health = $this->db->healthCheck();

        $this->assertFalse($health['connected'], 'healthCheck should report disconnected when SELECT 1 returns null.');
    }

    // ------------------------------------------------------------------
    // 5. tableExists returns true when table is found
    // ------------------------------------------------------------------

    public function testTableExistsReturnsTrueWhenTableFound(): void
    {
        $this->wpdb->get_var_result = 'wp_bmn_properties';

        $result = $this->db->tableExists('wp_bmn_properties');

        $this->assertTrue($result, 'tableExists should return true when get_var returns the table name.');
    }

    // ------------------------------------------------------------------
    // 6. tableExists returns false when table is not found
    // ------------------------------------------------------------------

    public function testTableExistsReturnsFalseWhenNotFound(): void
    {
        $this->wpdb->get_var_result = null;

        $result = $this->db->tableExists('wp_nonexistent_table');

        $this->assertFalse($result, 'tableExists should return false when get_var returns null.');
    }

    // ------------------------------------------------------------------
    // 7. batchInsert returns zero for empty array
    // ------------------------------------------------------------------

    public function testBatchInsertReturnsZeroForEmptyArray(): void
    {
        $result = $this->db->batchInsert('wp_bmn_properties', []);

        $this->assertSame(0, $result, 'batchInsert with no rows should return 0.');
    }

    // ------------------------------------------------------------------
    // 8. batchInsert executes query and returns row count
    // ------------------------------------------------------------------

    public function testBatchInsertExecutesQuery(): void
    {
        $this->wpdb->query_result = 3;

        $rows = [
            ['city' => 'Boston', 'price' => 500000],
            ['city' => 'Cambridge', 'price' => 600000],
            ['city' => 'Somerville', 'price' => 550000],
        ];

        $result = $this->db->batchInsert('wp_bmn_properties', $rows);

        $this->assertSame(3, $result, 'batchInsert should return the number of rows inserted.');
        $this->assertNotEmpty($this->wpdb->queries, 'batchInsert should execute at least one query.');
    }

    // ------------------------------------------------------------------
    // 9. batchUpdate returns zero for empty array
    // ------------------------------------------------------------------

    public function testBatchUpdateReturnsZeroForEmptyArray(): void
    {
        $result = $this->db->batchUpdate('wp_bmn_properties', []);

        $this->assertSame(0, $result, 'batchUpdate with no updates should return 0.');
    }

    // ------------------------------------------------------------------
    // 10. batchUpdate executes query
    // ------------------------------------------------------------------

    public function testBatchUpdateExecutesQuery(): void
    {
        $this->wpdb->query_result = 2;

        $updates = [
            ['id' => 1, 'status' => 'active', 'price' => 500000],
            ['id' => 2, 'status' => 'sold', 'price' => 450000],
        ];

        $result = $this->db->batchUpdate('wp_bmn_properties', $updates, 'id');

        $this->assertSame(2, $result, 'batchUpdate should return the number of rows affected.');
        $this->assertNotEmpty($this->wpdb->queries, 'batchUpdate should execute at least one query.');
    }

    // ------------------------------------------------------------------
    // 11. getQueryBuilder returns a QueryBuilder instance
    // ------------------------------------------------------------------

    public function testGetQueryBuilderReturnsInstance(): void
    {
        $builder = $this->db->getQueryBuilder();

        $this->assertInstanceOf(QueryBuilder::class, $builder, 'getQueryBuilder should return a QueryBuilder instance.');
    }

    // ------------------------------------------------------------------
    // 12. setTimezone executes a SET time_zone query
    // ------------------------------------------------------------------

    public function testSetTimezoneExecutesQuery(): void
    {
        $this->db->setTimezone('America/New_York');

        $this->assertNotEmpty($this->wpdb->queries, 'setTimezone should execute a query.');

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('time_zone', $lastQuery['sql'], 'The query should set the time_zone variable.');
    }
}
