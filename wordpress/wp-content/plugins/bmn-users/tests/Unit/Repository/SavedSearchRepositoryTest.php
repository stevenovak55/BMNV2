<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Repository;

use BMN\Users\Repository\SavedSearchRepository;
use PHPUnit\Framework\TestCase;

final class SavedSearchRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private SavedSearchRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new SavedSearchRepository($this->wpdb);
    }

    public function testFindByUserReturnsSearches(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'user_id' => 1, 'name' => 'Boston Condos'],
        ];

        $result = $this->repo->findByUser(1);

        $this->assertCount(1, $result);
        $this->assertSame('Boston Condos', $result[0]->name);
    }

    public function testFindByUserReturnsEmptyWhenNone(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByUser(99);

        $this->assertSame([], $result);
    }

    public function testCountByUser(): void
    {
        $this->wpdb->get_var_result = '3';

        $count = $this->repo->countByUser(1);

        $this->assertSame(3, $count);
    }

    public function testFindActiveForAlertsQueriesActiveOnly(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findActiveForAlerts();

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_active = 1', $sql);
        $this->assertStringContainsString('ORDER BY last_alert_at', $sql);
    }

    public function testUpdateAlertTimestampUpdatesFields(): void
    {
        $this->repo->updateAlertTimestamp(1, 100, 5);

        $args = $this->wpdb->queries[0]['args'];
        $this->assertArrayHasKey('last_alert_at', $args);
        $this->assertSame(100, $args['result_count']);
        $this->assertSame(5, $args['new_count']);
    }

    public function testRemoveAllForUserDeletesByUserId(): void
    {
        $result = $this->repo->removeAllForUser(1);

        $this->assertSame(1, $result);
    }

    public function testCreateSetsTimestamps(): void
    {
        $this->wpdb->insert_result = true;

        $this->repo->create([
            'user_id' => 1,
            'name'    => 'Test Search',
            'filters' => '{}',
        ]);

        $args = $this->wpdb->queries[0]['args'];
        $this->assertArrayHasKey('created_at', $args);
        $this->assertArrayHasKey('updated_at', $args);
    }

    public function testFindByIdDelegatesCorrectly(): void
    {
        $expected = (object) ['id' => 5, 'user_id' => 1, 'name' => 'My Search'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->find(5);

        $this->assertSame($expected, $result);
    }

    public function testDeleteDelegatesCorrectly(): void
    {
        $result = $this->repo->delete(5);

        $this->assertTrue($result);
    }

    public function testUpdateSetsUpdatedAt(): void
    {
        $this->repo->update(5, ['name' => 'Updated Name']);

        $args = $this->wpdb->queries[0]['args'];
        $this->assertArrayHasKey('updated_at', $args);
        $this->assertSame('Updated Name', $args['name']);
    }
}
