<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\OfficeReadRepository;
use PHPUnit\Framework\TestCase;

final class OfficeReadRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private OfficeReadRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new OfficeReadRepository($this->wpdb);
    }

    public function testFindByMlsIdQueriesCorrectTable(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'office_mls_id' => 'OFF001', 'office_name' => 'Test Office',
        ];

        $result = $this->repo->findByMlsId('OFF001');

        $this->assertNotNull($result);
        $this->assertSame('OFF001', $result->office_mls_id);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('bmn_offices', $sql);
    }

    public function testFindByMlsIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByMlsId('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testFindAllReturnsOffices(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'office_name' => 'Office A'],
            (object) ['id' => 2, 'office_name' => 'Office B'],
        ];

        $result = $this->repo->findAll();

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('ORDER BY office_name ASC', $sql);
    }

    public function testFindAllWithLimitAndOffset(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findAll(10, 5);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testCountQueriesCorrectTable(): void
    {
        $this->wpdb->get_var_result = '15';

        $result = $this->repo->count();

        $this->assertSame(15, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('COUNT(*)', $sql);
        $this->assertStringContainsString('bmn_offices', $sql);
    }

    public function testFindAllEmptyResult(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findAll();

        $this->assertSame([], $result);
    }
}
