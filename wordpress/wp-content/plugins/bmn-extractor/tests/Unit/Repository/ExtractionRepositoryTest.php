<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Repository;

use BMN\Extractor\Repository\ExtractionRepository;
use PHPUnit\Framework\TestCase;

class ExtractionRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ExtractionRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new ExtractionRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // startRun
    // ------------------------------------------------------------------

    public function testStartRunCreatesRecordAndReturnsId(): void
    {
        $this->wpdb->insert_id = 0; // Will be incremented to 1 by insert().

        $id = $this->repo->startRun('incremental', 'cron');

        $this->assertSame(1, $id);
        $this->assertNotEmpty($this->wpdb->queries);
        $insertData = $this->wpdb->queries[0]['args'];
        $this->assertSame('incremental', $insertData['extraction_type']);
        $this->assertSame('cron', $insertData['triggered_by']);
        $this->assertSame('running', $insertData['status']);
    }

    public function testStartRunWithFullResyncType(): void
    {
        $id = $this->repo->startRun('full', 'manual');

        $insertData = $this->wpdb->queries[0]['args'];
        $this->assertSame('full', $insertData['extraction_type']);
        $this->assertSame('manual', $insertData['triggered_by']);
    }

    // ------------------------------------------------------------------
    // updateMetrics
    // ------------------------------------------------------------------

    public function testUpdateMetricsDelegatesToUpdate(): void
    {
        $result = $this->repo->updateMetrics(42, ['listings_processed' => 100]);

        $this->assertTrue($result);
        $this->assertNotEmpty($this->wpdb->queries);
    }

    // ------------------------------------------------------------------
    // completeRun / failRun / pauseRun
    // ------------------------------------------------------------------

    public function testCompleteRunSetsCompletedStatus(): void
    {
        $result = $this->repo->completeRun(1);

        $this->assertTrue($result);
        $updateData = $this->wpdb->queries[0]['args'];
        $this->assertSame('completed', $updateData['status']);
        $this->assertArrayHasKey('completed_at', $updateData);
    }

    public function testFailRunSetsFailedStatusWithMessage(): void
    {
        $result = $this->repo->failRun(1, 'Something broke');

        $this->assertTrue($result);
        $updateData = $this->wpdb->queries[0]['args'];
        $this->assertSame('failed', $updateData['status']);
        $this->assertSame('Something broke', $updateData['error_message']);
    }

    public function testPauseRunSetsPausedStatus(): void
    {
        $result = $this->repo->pauseRun(1);

        $this->assertTrue($result);
        $updateData = $this->wpdb->queries[0]['args'];
        $this->assertSame('paused', $updateData['status']);
    }

    // ------------------------------------------------------------------
    // getLastRun / getLastCompletedRun / getLastPausedRun
    // ------------------------------------------------------------------

    public function testGetLastRunReturnsRow(): void
    {
        $expected = (object) ['id' => 1, 'status' => 'completed'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->getLastRun();
        $this->assertSame($expected, $result);
    }

    public function testGetLastCompletedRunQueriesForCompleted(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->getLastCompletedRun();

        $this->assertNull($result);
        $this->assertStringContainsString("'completed'", $this->wpdb->queries[0]['sql']);
    }

    public function testGetLastPausedRunQueriesForPaused(): void
    {
        $paused = (object) ['id' => 5, 'status' => 'paused', 'last_modification_timestamp' => '2026-02-15'];
        $this->wpdb->get_row_result = $paused;

        $result = $this->repo->getLastPausedRun();

        $this->assertSame($paused, $result);
        $this->assertStringContainsString("'paused'", $this->wpdb->queries[0]['sql']);
    }

    // ------------------------------------------------------------------
    // isRunning
    // ------------------------------------------------------------------

    public function testIsRunningReturnsTrueWhenRunning(): void
    {
        $this->wpdb->get_var_result = '1';
        $this->assertTrue($this->repo->isRunning());
    }

    public function testIsRunningReturnsFalseWhenNone(): void
    {
        $this->wpdb->get_var_result = '0';
        $this->assertFalse($this->repo->isRunning());
    }

    // ------------------------------------------------------------------
    // getHistory
    // ------------------------------------------------------------------

    public function testGetHistoryReturnsResults(): void
    {
        $runs = [
            (object) ['id' => 2, 'status' => 'completed'],
            (object) ['id' => 1, 'status' => 'failed'],
        ];
        $this->wpdb->get_results_result = $runs;

        $result = $this->repo->getHistory(10);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('LIMIT', $this->wpdb->queries[0]['sql']);
    }

    // ------------------------------------------------------------------
    // cleanupOld
    // ------------------------------------------------------------------

    public function testCleanupOldDeletesOldRecords(): void
    {
        $this->wpdb->query_result = 3;

        $deleted = $this->repo->cleanupOld(30);

        $this->assertSame(3, $deleted);
        $this->assertStringContainsString('DELETE', $this->wpdb->queries[0]['sql']);
        $this->assertStringContainsString('DATE_SUB', $this->wpdb->queries[0]['sql']);
    }
}
