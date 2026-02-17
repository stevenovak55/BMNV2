<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Repository;

use BMN\CMA\Repository\CmaReportRepository;
use PHPUnit\Framework\TestCase;

final class CmaReportRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private CmaReportRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $this->repo = new CmaReportRepository($this->wpdb);
    }

    public function testCreateEncodesJsonFields(): void
    {
        $this->wpdb->insert_id = 0; // Will become 1 after insert.

        $id = $this->repo->create([
            'user_id'      => 42,
            'subject_data' => ['bedrooms_total' => 3],
            'cma_filters'  => ['radius_miles' => 5],
        ]);

        $this->assertSame(1, $id);

        // Check the insert was called with JSON-encoded values.
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_cma_reports', $lastQuery['sql']);
        $insertData = $lastQuery['args'];
        $this->assertSame('{"bedrooms_total":3}', $insertData['subject_data']);
        $this->assertSame('{"radius_miles":5}', $insertData['cma_filters']);
    }

    public function testCreateEncodesAllFourJsonFields(): void
    {
        $id = $this->repo->create([
            'subject_data'       => ['a' => 1],
            'subject_overrides'  => ['b' => 2],
            'cma_filters'        => ['c' => 3],
            'summary_statistics' => ['d' => 4],
        ]);

        $this->assertSame(1, $id);
        $insertData = end($this->wpdb->queries)['args'];
        $this->assertSame('{"a":1}', $insertData['subject_data']);
        $this->assertSame('{"b":2}', $insertData['subject_overrides']);
        $this->assertSame('{"c":3}', $insertData['cma_filters']);
        $this->assertSame('{"d":4}', $insertData['summary_statistics']);
    }

    public function testCreateDoesNotEncodeStringJsonFields(): void
    {
        $this->repo->create([
            'subject_data' => '{"already":"encoded"}',
        ]);

        $insertData = end($this->wpdb->queries)['args'];
        $this->assertSame('{"already":"encoded"}', $insertData['subject_data']);
    }

    public function testCreateReturnsFalseOnFailure(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->create(['user_id' => 1]);

        $this->assertFalse($result);
    }

    public function testFindByUserReturnsResults(): void
    {
        $row1 = (object) ['id' => 1, 'user_id' => 42, 'session_name' => 'Test'];
        $row2 = (object) ['id' => 2, 'user_id' => 42, 'session_name' => 'Test 2'];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->findByUser(42, 20, 0);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }

    public function testFindByUserReturnsEmptyArrayWhenNull(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByUser(42);

        $this->assertSame([], $results);
    }

    public function testCountByUserReturnsInteger(): void
    {
        $this->wpdb->get_var_result = '15';

        $count = $this->repo->countByUser(42);

        $this->assertSame(15, $count);
    }

    public function testFindByListingReturnsResults(): void
    {
        $row = (object) ['id' => 5, 'subject_listing_id' => 'MLS123'];
        $this->wpdb->get_results_result = [$row];

        $results = $this->repo->findByListing('MLS123', 10);

        $this->assertCount(1, $results);
        $this->assertSame('MLS123', $results[0]->subject_listing_id);
    }

    public function testToggleFavoriteReturnsTrue(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->toggleFavorite(5);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('is_favorite = 1 - is_favorite', $lastQuery['sql']);
    }

    public function testToggleFavoriteReturnsFalseOnFailure(): void
    {
        $this->wpdb->query_result = false;

        $result = $this->repo->toggleFavorite(5);

        $this->assertFalse($result);
    }
}
