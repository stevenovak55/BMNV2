<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Repository;

use BMN\Exclusive\Repository\ExclusivePhotoRepository;
use PHPUnit\Framework\TestCase;

final class ExclusivePhotoRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ExclusivePhotoRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->repo = new ExclusivePhotoRepository($this->wpdb);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
    }

    // -- Table name --

    public function testTableName(): void
    {
        $this->wpdb->get_results_result = [];
        $this->repo->findByListing(1);

        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('wp_bmn_exclusive_photos', $lastQuery['sql']);
    }

    // -- findByListing --

    public function testFindByListing(): void
    {
        $photo1 = (object) ['id' => 1, 'sort_order' => 0, 'is_primary' => 1];
        $photo2 = (object) ['id' => 2, 'sort_order' => 1, 'is_primary' => 0];
        $this->wpdb->get_results_result = [$photo1, $photo2];

        $results = $this->repo->findByListing(10);

        $this->assertCount(2, $results);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('ORDER BY sort_order ASC', $lastQuery['sql']);
    }

    public function testFindByListingReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByListing(99);

        $this->assertSame([], $results);
    }

    // -- countByListing --

    public function testCountByListing(): void
    {
        $this->wpdb->get_var_result = '3';

        $count = $this->repo->countByListing(10);

        $this->assertSame(3, $count);
    }

    public function testCountByListingZero(): void
    {
        $this->wpdb->get_var_result = '0';

        $count = $this->repo->countByListing(99);

        $this->assertSame(0, $count);
    }

    // -- deleteByListing --

    public function testDeleteByListing(): void
    {
        $this->wpdb->query_result = 3;

        $result = $this->repo->deleteByListing(10);

        $this->assertTrue($result);
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('DELETE FROM', $lastQuery['sql']);
        $this->assertStringContainsString('exclusive_listing_id', $lastQuery['sql']);
    }

    public function testDeleteByListingReturnsTrueForZeroRows(): void
    {
        $this->wpdb->query_result = 0;

        $result = $this->repo->deleteByListing(99);

        $this->assertTrue($result);
    }

    public function testDeleteByListingReturnsFalseOnError(): void
    {
        $this->wpdb->query_result = false;

        $result = $this->repo->deleteByListing(10);

        $this->assertFalse($result);
    }

    // -- updateSortOrders --

    public function testUpdateSortOrders(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->updateSortOrders([
            ['id' => 1, 'sort_order' => 2],
            ['id' => 2, 'sort_order' => 0],
            ['id' => 3, 'sort_order' => 1],
        ]);

        $this->assertTrue($result);
    }

    public function testUpdateSortOrdersFailsOnError(): void
    {
        // First call succeeds, second fails.
        $this->wpdb->query_result = false;

        $result = $this->repo->updateSortOrders([
            ['id' => 1, 'sort_order' => 0],
        ]);

        $this->assertFalse($result);
    }

    // -- setPrimary --

    public function testSetPrimary(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->setPrimary(10, 5);

        $this->assertTrue($result);

        // Should have 2 queries: clear all, set one.
        $queries = $this->wpdb->queries;
        $clearQuery = $queries[count($queries) - 2];
        $setQuery = end($queries);

        $this->assertStringContainsString('is_primary = 0', $clearQuery['sql']);
        $this->assertStringContainsString('is_primary = 1', $setQuery['sql']);
    }

    public function testSetPrimaryFailsOnClearError(): void
    {
        $this->wpdb->query_result = false;

        $result = $this->repo->setPrimary(10, 5);

        $this->assertFalse($result);
    }

    // -- Inherited CRUD --

    public function testCreatePhoto(): void
    {
        $this->wpdb->insert_id = 0;

        $id = $this->repo->create([
            'exclusive_listing_id' => 10,
            'media_url' => 'https://example.com/photo.jpg',
            'sort_order' => 0,
            'is_primary' => 1,
        ]);

        $this->assertSame(1, $id);
    }

    public function testDeletePhoto(): void
    {
        $this->wpdb->query_result = 1;

        $deleted = $this->repo->delete(5);

        $this->assertTrue($deleted);
    }
}
