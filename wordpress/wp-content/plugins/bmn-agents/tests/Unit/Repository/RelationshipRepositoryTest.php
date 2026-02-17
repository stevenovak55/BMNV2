<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\RelationshipRepository;
use PHPUnit\Framework\TestCase;

final class RelationshipRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private RelationshipRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new RelationshipRepository($this->wpdb);
    }

    public function testCreateInsertsWithTimestamps(): void
    {
        $result = $this->repo->create([
            'agent_user_id'  => 1,
            'client_user_id' => 2,
            'status'         => 'active',
            'source'         => 'manual',
        ]);

        $this->assertIsInt($result);
        $insertQuery = $this->wpdb->queries[0];
        $this->assertStringContainsString('INSERT', $insertQuery['sql']);
        $this->assertArrayHasKey('assigned_at', $insertQuery['args']);
        $this->assertArrayHasKey('updated_at', $insertQuery['args']);
    }

    public function testFindActiveForClientQueriesCorrectly(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'agent_user_id' => 10, 'client_user_id' => 20, 'status' => 'active',
        ];

        $result = $this->repo->findActiveForClient(20);

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString("status = 'active'", $sql);
        $this->assertStringContainsString('client_user_id', $sql);
    }

    public function testFindActiveForClientReturnsNullWhenNone(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findActiveForClient(999);

        $this->assertNull($result);
    }

    public function testFindByAgentAndClientQueriesCorrectly(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'agent_user_id' => 10, 'client_user_id' => 20,
        ];

        $result = $this->repo->findByAgentAndClient(10, 20);

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('client_user_id', $sql);
    }

    public function testFindClientsByAgentWithoutStatusFilter(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'client_user_id' => 20],
        ];

        $result = $this->repo->findClientsByAgent(10);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('ORDER BY assigned_at DESC', $sql);
    }

    public function testFindClientsByAgentWithStatusFilter(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findClientsByAgent(10, 'active');

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('status', $sql);
    }

    public function testFindClientsByAgentWithPagination(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findClientsByAgent(10, null, 20, 40);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testCountClientsByAgentQueriesCorrectly(): void
    {
        $this->wpdb->get_var_result = '5';

        $result = $this->repo->countClientsByAgent(10);

        $this->assertSame(5, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('COUNT(*)', $sql);
    }

    public function testCountClientsByAgentWithStatusFilter(): void
    {
        $this->wpdb->get_var_result = '3';

        $result = $this->repo->countClientsByAgent(10, 'active');

        $this->assertSame(3, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('status', $sql);
    }

    public function testUpdateSetsUpdatedAt(): void
    {
        $this->repo->update(1, ['status' => 'inactive']);

        $updateQuery = $this->wpdb->queries[0];
        $this->assertStringContainsString('UPDATE', $updateQuery['sql']);
        $this->assertArrayHasKey('updated_at', $updateQuery['args']);
    }
}
