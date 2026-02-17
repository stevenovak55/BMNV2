<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Repository;

use BMN\Exclusive\Repository\ExclusiveListingRepository;
use PHPUnit\Framework\TestCase;

final class ExclusiveListingRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ExclusiveListingRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->repo = new ExclusiveListingRepository($this->wpdb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    // -- Table name --

    public function testTableName(): void
    {
        $this->wpdb->get_results_result = [];
        $this->repo->findByAgent(1);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_exclusive_listings', $lastQuery['sql']);
    }

    // -- findByAgent --

    public function testFindByAgentWithoutStatusFilter(): void
    {
        $row1 = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'active'];
        $row2 = (object) ['id' => 2, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->findByAgent(42, 20, 0);

        $this->assertCount(2, $results);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('agent_user_id', $lastQuery['sql']);
        $this->assertStringContainsString('ORDER BY updated_at DESC', $lastQuery['sql']);
        $this->assertStringContainsString('LIMIT', $lastQuery['sql']);
    }

    public function testFindByAgentWithStatusFilter(): void
    {
        $row = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'active'];
        $this->wpdb->get_results_result = [$row];

        $results = $this->repo->findByAgent(42, 20, 0, 'active');

        $this->assertCount(1, $results);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('status', $lastQuery['sql']);
    }

    public function testFindByAgentReturnsEmptyArray(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByAgent(99);

        $this->assertSame([], $results);
    }

    // -- countByAgent --

    public function testCountByAgentWithoutStatus(): void
    {
        $this->wpdb->get_var_result = '5';

        $count = $this->repo->countByAgent(42);

        $this->assertSame(5, $count);
    }

    public function testCountByAgentWithStatus(): void
    {
        $this->wpdb->get_var_result = '3';

        $count = $this->repo->countByAgent(42, 'active');

        $this->assertSame(3, $count);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('status', $lastQuery['sql']);
    }

    // -- findByListingId --

    public function testFindByListingIdFound(): void
    {
        $row = (object) ['id' => 1, 'listing_id' => 42];
        $this->wpdb->get_row_result = $row;

        $result = $this->repo->findByListingId(42);

        $this->assertNotNull($result);
        $this->assertSame(42, $result->listing_id);
    }

    public function testFindByListingIdNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByListingId(999);

        $this->assertNull($result);
    }

    // -- getNextListingId --

    public function testGetNextListingIdFromEmpty(): void
    {
        // SQL COALESCE(MAX(listing_id), 0) + 1 returns 1 for empty table.
        // The stub returns the raw value, so simulate the SQL result.
        $this->wpdb->get_var_result = '1';

        $next = $this->repo->getNextListingId();

        $this->assertSame(1, $next);
    }

    public function testGetNextListingIdFromExisting(): void
    {
        $this->wpdb->get_var_result = '5';

        $next = $this->repo->getNextListingId();

        $this->assertSame(5, $next);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('COALESCE(MAX(listing_id), 0) + 1', $lastQuery['sql']);
    }

    // -- updatePhotoInfo --

    public function testUpdatePhotoInfo(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->updatePhotoInfo(1, 5, 'https://example.com/photo.jpg');

        $this->assertTrue($result);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('photo_count', $lastQuery['sql']);
        $this->assertStringContainsString('main_photo_url', $lastQuery['sql']);
    }

    public function testUpdatePhotoInfoWithNullUrl(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->updatePhotoInfo(1, 0, null);

        $this->assertTrue($result);
    }

    // -- markSynced --

    public function testMarkSynced(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->markSynced(1);

        $this->assertTrue($result);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('synced_to_properties = 1', $lastQuery['sql']);
    }

    // -- Inherited CRUD from base Repository --

    public function testCreateListing(): void
    {
        $this->wpdb->insert_id = 0;

        $id = $this->repo->create([
            'agent_user_id' => 42,
            'listing_id' => 1,
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);

        $this->assertSame(1, $id);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('INSERT INTO wp_bmn_exclusive_listings', $lastQuery['sql']);
    }

    public function testFindListing(): void
    {
        $row = (object) ['id' => 1, 'listing_id' => 1, 'city' => 'Boston'];
        $this->wpdb->get_row_result = $row;

        $result = $this->repo->find(1);

        $this->assertNotNull($result);
        $this->assertSame('Boston', $result->city);
    }

    public function testUpdateListing(): void
    {
        $this->wpdb->query_result = 1;

        $updated = $this->repo->update(1, ['list_price' => 550000]);

        $this->assertTrue($updated);
    }

    public function testDeleteListing(): void
    {
        $this->wpdb->query_result = 1;

        $deleted = $this->repo->delete(1);

        $this->assertTrue($deleted);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DELETE FROM', $lastQuery['sql']);
    }
}
