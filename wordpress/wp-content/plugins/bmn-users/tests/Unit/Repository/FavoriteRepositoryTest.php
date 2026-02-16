<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Repository;

use BMN\Users\Repository\FavoriteRepository;
use PHPUnit\Framework\TestCase;

final class FavoriteRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private FavoriteRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new FavoriteRepository($this->wpdb);
    }

    public function testTimestampsAreDisabled(): void
    {
        // Verify that adding a favorite doesn't set updated_at.
        $this->wpdb->insert_result = true;
        $this->repo->addFavorite(1, '73464868');

        $sql = $this->wpdb->queries[0]['sql'];
        $args = $this->wpdb->queries[0]['args'];

        // Should have created_at but not updated_at.
        $this->assertArrayHasKey('created_at', $args);
        $this->assertArrayNotHasKey('updated_at', $args);
    }

    public function testFindByUserExecutesCorrectQuery(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByUser(1, 25, 0);

        $this->assertCount(1, $this->wpdb->queries);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('user_id', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
        $this->assertSame([], $result);
    }

    public function testFindByUserReturnsFavorites(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'user_id' => 1, 'listing_id' => '73464868'],
            (object) ['id' => 2, 'user_id' => 1, 'listing_id' => '73464869'],
        ];

        $result = $this->repo->findByUser(1, 25, 0);

        $this->assertCount(2, $result);
    }

    public function testCountByUserDelegates(): void
    {
        $this->wpdb->get_var_result = '5';

        $count = $this->repo->countByUser(1);

        $this->assertSame(5, $count);
    }

    public function testFindByUserAndListingReturnsRow(): void
    {
        $expected = (object) ['id' => 1, 'user_id' => 1, 'listing_id' => '73464868'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByUserAndListing(1, '73464868');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('user_id', $sql);
        $this->assertStringContainsString('listing_id', $sql);
    }

    public function testFindByUserAndListingReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByUserAndListing(1, 'nonexistent');

        $this->assertNull($result);
    }

    public function testAddFavoriteInsertsRow(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->addFavorite(1, '73464868');

        $this->assertIsInt($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['user_id']);
        $this->assertSame('73464868', $args['listing_id']);
        $this->assertArrayHasKey('created_at', $args);
    }

    public function testAddFavoriteReturnsFalseOnFailure(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->addFavorite(1, '73464868');

        $this->assertFalse($result);
    }

    public function testRemoveFavoriteDeletesRow(): void
    {
        $result = $this->repo->removeFavorite(1, '73464868');

        $this->assertTrue($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['user_id']);
        $this->assertSame('73464868', $args['listing_id']);
    }

    public function testRemoveAllForUserDeletesByUserId(): void
    {
        $result = $this->repo->removeAllForUser(1);

        $this->assertSame(1, $result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['user_id']);
    }

    public function testGetListingIdsForUserReturnsArray(): void
    {
        $this->wpdb->get_col_result = ['73464868', '73464869', '73464870'];

        $result = $this->repo->getListingIdsForUser(1);

        $this->assertSame(['73464868', '73464869', '73464870'], $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('listing_id', $sql);
        $this->assertStringContainsString('user_id', $sql);
    }

    public function testGetListingIdsForUserReturnsEmptyArray(): void
    {
        $this->wpdb->get_col_result = [];

        $result = $this->repo->getListingIdsForUser(99);

        $this->assertSame([], $result);
    }
}
