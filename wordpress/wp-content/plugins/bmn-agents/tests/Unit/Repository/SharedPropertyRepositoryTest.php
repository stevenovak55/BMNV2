<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\SharedPropertyRepository;
use PHPUnit\Framework\TestCase;

final class SharedPropertyRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private SharedPropertyRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new SharedPropertyRepository($this->wpdb);
    }

    public function testCreateInsertsWithTimestamps(): void
    {
        $result = $this->repo->create([
            'agent_user_id'  => 1,
            'client_user_id' => 2,
            'listing_id'     => 'MLS123',
        ]);

        $this->assertIsInt($result);
        $query = $this->wpdb->queries[0];
        $this->assertArrayHasKey('shared_at', $query['args']);
        $this->assertArrayHasKey('updated_at', $query['args']);
    }

    public function testFindByAgentClientListingQueriesCorrectly(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'agent_user_id' => 1, 'client_user_id' => 2, 'listing_id' => 'MLS123',
        ];

        $result = $this->repo->findByAgentClientListing(1, 2, 'MLS123');

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('client_user_id', $sql);
        $this->assertStringContainsString('listing_id', $sql);
    }

    public function testFindByAgentClientListingReturnsNull(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByAgentClientListing(1, 2, 'NONEXISTENT');

        $this->assertNull($result);
    }

    public function testFindForClientExcludesDismissedByDefault(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findForClient(2);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_dismissed = 0', $sql);
        $this->assertStringContainsString('ORDER BY shared_at DESC', $sql);
    }

    public function testFindForClientIncludesDismissedWhenRequested(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findForClient(2, true);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringNotContainsString('is_dismissed', $sql);
    }

    public function testFindForClientWithPagination(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findForClient(2, false, 10, 20);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testCountForClientQueriesCorrectly(): void
    {
        $this->wpdb->get_var_result = '15';

        $result = $this->repo->countForClient(2);

        $this->assertSame(15, $result);
    }

    public function testFindByAgentQueriesCorrectly(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findByAgent(1, 10, 0);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('ORDER BY shared_at DESC', $sql);
    }

    public function testRecordViewIncrementsCount(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->recordView(1);

        $this->assertTrue($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('view_count = view_count + 1', $sql);
        $this->assertStringContainsString('COALESCE(first_viewed_at', $sql);
    }

    public function testUpdateSetsUpdatedAt(): void
    {
        $this->repo->update(1, ['client_response' => 'interested']);

        $query = $this->wpdb->queries[0];
        $this->assertArrayHasKey('updated_at', $query['args']);
    }
}
