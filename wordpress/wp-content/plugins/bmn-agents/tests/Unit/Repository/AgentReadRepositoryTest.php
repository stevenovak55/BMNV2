<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\AgentReadRepository;
use PHPUnit\Framework\TestCase;

final class AgentReadRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private AgentReadRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new AgentReadRepository($this->wpdb);
    }

    public function testFindByMlsIdQueriesCorrectTable(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'agent_mls_id' => 'AGT001', 'full_name' => 'John Smith',
        ];

        $result = $this->repo->findByMlsId('AGT001');

        $this->assertNotNull($result);
        $this->assertSame('AGT001', $result->agent_mls_id);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('bmn_agents', $sql);
        $this->assertStringContainsString('agent_mls_id', $sql);
    }

    public function testFindByMlsIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByMlsId('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testFindAllReturnsAgents(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'full_name' => 'Alice'],
            (object) ['id' => 2, 'full_name' => 'Bob'],
        ];

        $result = $this->repo->findAll();

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('ORDER BY full_name ASC', $sql);
    }

    public function testFindAllWithLimitAndOffset(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findAll(10, 20);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testSearchByNameUsesLike(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'full_name' => 'John Smith'],
        ];

        $result = $this->repo->searchByName('John');

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIKE', $sql);
        $this->assertStringContainsString('full_name', $sql);
    }

    public function testSearchByNameRespectsLimit(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->searchByName('Test', 5);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testCountQueriesCorrectTable(): void
    {
        $this->wpdb->get_var_result = '42';

        $result = $this->repo->count();

        $this->assertSame(42, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('COUNT(*)', $sql);
        $this->assertStringContainsString('bmn_agents', $sql);
    }

    public function testFindAllEmptyResult(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findAll();

        $this->assertSame([], $result);
    }
}
