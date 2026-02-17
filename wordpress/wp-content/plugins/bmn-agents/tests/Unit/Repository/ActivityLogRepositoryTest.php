<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\ActivityLogRepository;
use PHPUnit\Framework\TestCase;

final class ActivityLogRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ActivityLogRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new ActivityLogRepository($this->wpdb);
    }

    public function testCreateInsertsWithCreatedAt(): void
    {
        $result = $this->repo->create([
            'agent_user_id'  => 10,
            'client_user_id' => 20,
            'activity_type'  => 'favorite_added',
            'entity_id'      => 'MLS001',
            'entity_type'    => 'property',
        ]);

        $this->assertIsInt($result);
        $query = $this->wpdb->queries[0];
        $this->assertArrayHasKey('created_at', $query['args']);
    }

    public function testCreateEncodesMetadata(): void
    {
        $this->repo->create([
            'agent_user_id'  => 10,
            'client_user_id' => 20,
            'activity_type'  => 'search_created',
            'metadata'       => ['query' => 'Boston condos'],
        ]);

        $query = $this->wpdb->queries[0];
        $this->assertIsString($query['args']['metadata']);
        $this->assertStringContainsString('Boston condos', $query['args']['metadata']);
    }

    public function testFindByAgentQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'activity_type' => 'login'],
        ];

        $result = $this->repo->findByAgent(10, 50, 0);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testFindByAgentAndClientQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findByAgentAndClient(10, 20, 25);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('client_user_id', $sql);
    }

    public function testCountActiveClientsQueriesCorrectly(): void
    {
        $this->wpdb->get_var_result = '8';

        $result = $this->repo->countActiveClients(10, 30);

        $this->assertSame(8, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('COUNT(DISTINCT client_user_id)', $sql);
        $this->assertStringContainsString('created_at >=', $sql);
    }

    public function testCountByTypeReturnsGrouped(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['activity_type' => 'favorite_added', 'cnt' => '10'],
            (object) ['activity_type' => 'client_login', 'cnt' => '5'],
        ];

        $result = $this->repo->countByType(10, 30);

        $this->assertSame(10, $result['favorite_added']);
        $this->assertSame(5, $result['client_login']);
    }

    public function testCountRecentQueriesCorrectly(): void
    {
        $this->wpdb->get_var_result = '42';

        $result = $this->repo->countRecent(10, 30);

        $this->assertSame(42, $result);
    }

    public function testFindByAgentEmptyResult(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByAgent(10);

        $this->assertSame([], $result);
    }
}
