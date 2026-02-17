<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Repository;

use BMN\Flip\Repository\FlipReportRepository;
use PHPUnit\Framework\TestCase;

final class FlipReportRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private FlipReportRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->repo = new FlipReportRepository($this->wpdb);
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
        $this->wpdb->get_results_result = [];
        $this->repo->findByUser(1);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_flip_reports', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // create() — JSON-encodes cities and filters
    // ------------------------------------------------------------------

    public function testCreateJsonEncodesFields(): void
    {
        $this->wpdb->insert_id = 0;

        $id = $this->repo->create([
            'user_id' => 42,
            'name'    => 'Boston Flip Search',
            'cities'  => ['Boston', 'Cambridge', 'Somerville'],
            'filters' => ['min_price' => 200000, 'max_price' => 600000],
        ]);

        $this->assertSame(1, $id);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_flip_reports', $lastQuery['sql']);
        $insertData = $lastQuery['args'];
        $this->assertSame('["Boston","Cambridge","Somerville"]', $insertData['cities']);
        $this->assertSame('{"min_price":200000,"max_price":600000}', $insertData['filters']);
    }

    // ------------------------------------------------------------------
    // findByUser()
    // ------------------------------------------------------------------

    public function testFindByUser(): void
    {
        $row1 = (object) ['id' => 1, 'user_id' => 42, 'name' => 'Report A'];
        $row2 = (object) ['id' => 2, 'user_id' => 42, 'name' => 'Report B'];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->findByUser(42, 20, 0);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }

    public function testFindByUserReturnsEmptyArray(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByUser(99);

        $this->assertSame([], $results);
    }

    // ------------------------------------------------------------------
    // countByUser()
    // ------------------------------------------------------------------

    public function testCountByUser(): void
    {
        $this->wpdb->get_var_result = '7';

        $count = $this->repo->countByUser(42);

        $this->assertSame(7, $count);
    }

    // ------------------------------------------------------------------
    // findActiveMonitors()
    // ------------------------------------------------------------------

    public function testFindActiveMonitors(): void
    {
        $row = (object) [
            'id'                => 3,
            'type'              => 'monitor',
            'status'            => 'active',
            'monitor_frequency' => 'daily',
        ];
        $this->wpdb->get_results_result = [$row];

        $results = $this->repo->findActiveMonitors();

        $this->assertCount(1, $results);
        $this->assertSame('monitor', $results[0]->type);
        $this->assertSame('active', $results[0]->status);

        // Verify the query filters by type and status.
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('monitor', $lastQuery['sql']);
        $this->assertStringContainsString('active', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // toggleFavorite()
    // ------------------------------------------------------------------

    public function testToggleFavorite(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->toggleFavorite(5);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('is_favorite = 1 - is_favorite', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // incrementRunCount()
    // ------------------------------------------------------------------

    public function testIncrementRunCount(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->incrementRunCount(5, 120, 35);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('run_count = run_count + 1', $lastQuery['sql']);
        $this->assertStringContainsString('property_count', $lastQuery['sql']);
        $this->assertStringContainsString('viable_count', $lastQuery['sql']);
        $this->assertStringContainsString('last_run_date', $lastQuery['sql']);
    }
}
