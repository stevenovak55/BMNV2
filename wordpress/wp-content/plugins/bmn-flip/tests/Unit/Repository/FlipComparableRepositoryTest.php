<?php

declare(strict_types=1);

namespace BMN\Flip\Tests\Unit\Repository;

use BMN\Flip\Repository\FlipComparableRepository;
use PHPUnit\Framework\TestCase;

final class FlipComparableRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private FlipComparableRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->repo = new FlipComparableRepository($this->wpdb);
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
        $this->repo->findByAnalysis(1);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_flip_comparables', $lastQuery['sql']);
    }

    // ------------------------------------------------------------------
    // create() — JSON-encodes adjustments
    // ------------------------------------------------------------------

    public function testCreateJsonEncodesAdjustments(): void
    {
        $this->wpdb->insert_id = 0;

        $id = $this->repo->create([
            'analysis_id'      => 1,
            'listing_id'       => 'MLS789',
            'close_price'      => 450000,
            'adjustments'      => ['sqft' => -5000, 'garage' => 3000, 'age' => -2000],
        ]);

        $this->assertSame(1, $id);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_flip_comparables', $lastQuery['sql']);
        $insertData = $lastQuery['args'];
        $this->assertSame('{"sqft":-5000,"garage":3000,"age":-2000}', $insertData['adjustments']);
    }

    // ------------------------------------------------------------------
    // findByAnalysis()
    // ------------------------------------------------------------------

    public function testFindByAnalysis(): void
    {
        $row1 = (object) ['id' => 1, 'analysis_id' => 5, 'weight' => 0.85];
        $row2 = (object) ['id' => 2, 'analysis_id' => 5, 'weight' => 0.72];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->findByAnalysis(5);

        $this->assertCount(2, $results);
        $this->assertSame(1, $results[0]->id);
        $this->assertSame(2, $results[1]->id);
    }

    public function testFindByAnalysisReturnsEmptyWhenNull(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByAnalysis(99);

        $this->assertSame([], $results);
    }

    // ------------------------------------------------------------------
    // deleteByAnalysis()
    // ------------------------------------------------------------------

    public function testDeleteByAnalysis(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->deleteByAnalysis(5);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DELETE FROM wp_bmn_flip_comparables', $lastQuery['sql']);
        $this->assertStringContainsString('analysis_id', $lastQuery['sql']);
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
        $this->assertStringContainsString('DELETE FROM wp_bmn_flip_comparables', $lastQuery['sql']);
        $this->assertStringContainsString('wp_bmn_flip_analyses', $lastQuery['sql']);
        $this->assertStringContainsString('report_id', $lastQuery['sql']);
    }
}
