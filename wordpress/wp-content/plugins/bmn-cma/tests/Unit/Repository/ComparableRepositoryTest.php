<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Repository;

use BMN\CMA\Repository\ComparableRepository;
use PHPUnit\Framework\TestCase;

final class ComparableRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ComparableRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $this->repo = new ComparableRepository($this->wpdb);
    }

    public function testCreateEncodesJsonFields(): void
    {
        $this->wpdb->insert_id = 0;

        $id = $this->repo->create([
            'report_id'     => 1,
            'listing_id'    => 'MLS100',
            'adjustments'   => ['bedrooms' => ['adjustment' => 500]],
            'property_data' => ['address' => '123 Main St'],
        ]);

        $this->assertSame(1, $id);

        $insertData = end($this->wpdb->queries)['args'];
        $this->assertSame('{"bedrooms":{"adjustment":500}}', $insertData['adjustments']);
        $this->assertSame('{"address":"123 Main St"}', $insertData['property_data']);
    }

    public function testCreateDoesNotEncodeStringValues(): void
    {
        $this->repo->create([
            'report_id'   => 1,
            'listing_id'  => 'MLS100',
            'adjustments' => '{"already":"json"}',
        ]);

        $insertData = end($this->wpdb->queries)['args'];
        $this->assertSame('{"already":"json"}', $insertData['adjustments']);
    }

    public function testFindByReportReturnsResults(): void
    {
        $comp1 = (object) ['id' => 1, 'report_id' => 10, 'comparability_score' => 85.0];
        $comp2 = (object) ['id' => 2, 'report_id' => 10, 'comparability_score' => 70.0];
        $this->wpdb->get_results_result = [$comp1, $comp2];

        $results = $this->repo->findByReport(10);

        $this->assertCount(2, $results);
        $this->assertSame(85.0, $results[0]->comparability_score);
    }

    public function testFindByReportReturnsEmptyOnNull(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByReport(10);

        $this->assertSame([], $results);
    }

    public function testDeleteByReportReturnsCount(): void
    {
        $this->wpdb->query_result = 3;

        $count = $this->repo->deleteByReport(10);

        $this->assertSame(3, $count);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DELETE FROM wp_bmn_comparables', $lastQuery['sql']);
        $this->assertStringContainsString('report_id', $lastQuery['sql']);
    }

    public function testDeleteByReportReturnsZeroOnFailure(): void
    {
        $this->wpdb->query_result = false;

        $count = $this->repo->deleteByReport(10);

        $this->assertSame(0, $count);
    }

    public function testUpsertEncodesJsonFieldsAndReturnsTrue(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->upsert([
            'report_id'     => 1,
            'listing_id'    => 'MLS100',
            'close_price'   => 500000.00,
            'adjusted_price' => 510000.00,
            'adjustments'   => ['bedrooms' => ['adjustment' => 5000]],
            'property_data' => ['address' => '123 Main St'],
            'is_selected'   => 1,
        ]);

        $this->assertTrue($result);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_comparables', $lastQuery['sql']);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $lastQuery['sql']);
    }

    public function testUpsertReturnsFalseOnFailure(): void
    {
        $this->wpdb->query_result = false;

        $result = $this->repo->upsert([
            'report_id'  => 1,
            'listing_id' => 'MLS100',
        ]);

        $this->assertFalse($result);
    }
}
