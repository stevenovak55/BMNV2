<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Repository;

use BMN\Flip\Repository\FlipAnalysisRepository;
use PHPUnit\Framework\TestCase;

final class FlipAnalysisRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private FlipAnalysisRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->repo = new FlipAnalysisRepository($this->wpdb);
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
        // The fully-qualified table name is set in the constructor via prefix + getTableName().
        // We verify by calling down on a query and inspecting the table reference.
        $this->wpdb->get_results_result = [];
        $this->repo->findByReport(1);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_flip_analyses', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // create() — JSON-encodes structured fields
    // ------------------------------------------------------------------

    public function testCreateJsonEncodesFields(): void
    {
        $this->wpdb->insert_id = 0; // Will become 1 after insert.

        $id = $this->repo->create([
            'listing_id'         => 'MLS123',
            'list_price'         => 500000,
            'rental_analysis'    => ['cap_rate' => 5.0],
            'remarks_signals'    => ['distressed' => true],
            'applied_thresholds' => ['min_roi' => 15],
        ]);

        $this->assertSame(1, $id);

        // Verify JSON encoding was applied before insert.
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_flip_analyses', $lastQuery['sql']);
        $insertData = $lastQuery['args'];
        $this->assertSame('{"cap_rate":5}', $insertData['rental_analysis']);
        $this->assertSame('{"distressed":true}', $insertData['remarks_signals']);
        $this->assertSame('{"min_roi":15}', $insertData['applied_thresholds']);
    }

    // ------------------------------------------------------------------
    // findByReport()
    // ------------------------------------------------------------------

    public function testFindByReport(): void
    {
        $row1 = (object) ['id' => 1, 'report_id' => 10, 'total_score' => 85.0];
        $row2 = (object) ['id' => 2, 'report_id' => 10, 'total_score' => 72.5];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->findByReport(10, 100, 0);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }

    public function testFindByReportReturnsEmptyArray(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByReport(99);

        $this->assertSame([], $results);
    }

    // ------------------------------------------------------------------
    // countByReport()
    // ------------------------------------------------------------------

    public function testCountByReport(): void
    {
        $this->wpdb->get_var_result = '5';

        $count = $this->repo->countByReport(10);

        $this->assertSame(5, $count);
    }

    // ------------------------------------------------------------------
    // findByListing()
    // ------------------------------------------------------------------

    public function testFindByListing(): void
    {
        $row = (object) ['id' => 3, 'listing_id' => 'MLS456', 'run_date' => '2026-02-16'];
        $this->wpdb->get_results_result = [$row];

        $results = $this->repo->findByListing('MLS456', 10);

        $this->assertCount(1, $results);
        $this->assertSame('MLS456', $results[0]->listing_id);
    }

    // ------------------------------------------------------------------
    // findViable()
    // ------------------------------------------------------------------

    public function testFindViable(): void
    {
        $row = (object) ['id' => 4, 'report_id' => 10, 'disqualified' => 0, 'total_score' => 90.0];
        $this->wpdb->get_results_result = [$row];

        $results = $this->repo->findViable(10, 50, 0);

        $this->assertCount(1, $results);
        $this->assertSame(4, $results[0]->id);

        // Verify the query includes the disqualified filter.
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('disqualified = 0', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // getReportSummary()
    // ------------------------------------------------------------------

    public function testGetReportSummary(): void
    {
        $summary = (object) [
            'city'      => 'Boston',
            'total'     => 15,
            'viable'    => 8,
            'avg_score' => 72.3,
            'avg_roi'   => 18.5,
        ];
        $this->wpdb->get_results_result = [$summary];

        $result = $this->repo->getReportSummary(10);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Boston', $result[0]->city);
    }

    public function testGetReportSummaryReturnsNull(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->getReportSummary(99);

        $this->assertNull($result);
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
        $this->assertStringContainsString('DELETE FROM wp_bmn_flip_analyses', $lastQuery['sql']);
        $this->assertStringContainsString('report_id', $lastQuery['sql']);
    }
}
