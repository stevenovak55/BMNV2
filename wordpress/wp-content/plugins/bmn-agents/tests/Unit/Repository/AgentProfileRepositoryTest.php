<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\AgentProfileRepository;
use PHPUnit\Framework\TestCase;

final class AgentProfileRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private AgentProfileRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new AgentProfileRepository($this->wpdb);
    }

    public function testFindByMlsIdQueriesCorrectTable(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'agent_mls_id' => 'AGT001', 'bio' => 'Test bio',
        ];

        $result = $this->repo->findByMlsId('AGT001');

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('bmn_agent_profiles', $sql);
    }

    public function testFindByMlsIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByMlsId('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testFindByUserIdQueriesCorrectColumn(): void
    {
        $this->wpdb->get_row_result = (object) ['id' => 1, 'user_id' => 42];

        $result = $this->repo->findByUserId(42);

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('user_id', $sql);
    }

    public function testUpsertCreatesNewProfile(): void
    {
        $this->wpdb->get_row_result = null; // Not found, so creates.

        $result = $this->repo->upsert('AGT001', ['bio' => 'New bio']);

        $this->assertIsInt($result);
        // Verify insert was called.
        $insertQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT', $insertQuery['sql']);
    }

    public function testUpsertUpdatesExistingProfile(): void
    {
        $this->wpdb->get_row_result = (object) ['id' => 5, 'agent_mls_id' => 'AGT001'];

        $result = $this->repo->upsert('AGT001', ['bio' => 'Updated bio']);

        $this->assertSame(5, $result);
        // Verify update was called.
        $updateQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('UPDATE', $updateQuery['sql']);
    }

    public function testFindFeaturedQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'is_featured' => 1, 'is_active' => 1],
        ];

        $result = $this->repo->findFeatured();

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_featured = 1', $sql);
        $this->assertStringContainsString('is_active = 1', $sql);
        $this->assertStringContainsString('ORDER BY display_order ASC', $sql);
    }

    public function testFindActiveQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findActive(10, 0);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_active = 1', $sql);
    }

    public function testCountActiveQueriesCorrectly(): void
    {
        $this->wpdb->get_var_result = '25';

        $result = $this->repo->countActive();

        $this->assertSame(25, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_active = 1', $sql);
    }
}
