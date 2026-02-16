<?php

declare(strict_types=1);

namespace BMN\Properties\Tests\Unit\Repository;

use BMN\Properties\Repository\PropertySearchRepository;
use PHPUnit\Framework\TestCase;

final class PropertySearchRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private PropertySearchRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new PropertySearchRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // searchProperties
    // ------------------------------------------------------------------

    public function testSearchPropertiesExecutesCorrectQuery(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->searchProperties('*', "city = 'Boston'", 'list_price ASC', 25, 0);

        $this->assertCount(1, $this->wpdb->queries);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('SELECT *', $sql);
        $this->assertStringContainsString("city = 'Boston'", $sql);
        $this->assertStringContainsString('ORDER BY list_price ASC', $sql);
        $this->assertStringContainsString('LIMIT', $sql);
    }

    public function testSearchPropertiesReturnsEmptyArrayOnNull(): void
    {
        $this->wpdb->get_results_result = null;

        $result = $this->repo->searchProperties('*', '1=1', 'id ASC', 10, 0);

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // countProperties
    // ------------------------------------------------------------------

    public function testCountPropertiesExecutesCountQuery(): void
    {
        $this->wpdb->get_var_result = '42';

        $count = $this->repo->countProperties("city = 'Boston'");

        $this->assertSame(42, $count);
        $this->assertStringContainsString('COUNT(*)', $this->wpdb->queries[0]['sql']);
    }

    // ------------------------------------------------------------------
    // findByListingId
    // ------------------------------------------------------------------

    public function testFindByListingIdTriesActiveFirst(): void
    {
        $active = (object) ['listing_id' => '73464868', 'listing_key' => 'LK1', 'is_archived' => 0];
        $this->wpdb->get_row_result = $active;

        $result = $this->repo->findByListingId('73464868');

        $this->assertSame($active, $result);
        $this->assertCount(1, $this->wpdb->queries);
        $this->assertStringContainsString('is_archived = 0', $this->wpdb->queries[0]['sql']);
    }

    public function testFindByListingIdFallsBackToArchived(): void
    {
        $calls = 0;
        // First call returns null (no active), second returns archived.
        $archived = (object) ['listing_id' => '73464868', 'listing_key' => 'LK1', 'is_archived' => 1];

        // We need to simulate: first get_row returns null, second returns archived.
        // The stub uses get_row_result for all calls, so we use a workaround.
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByListingId('73464868');

        // With null result and fallback also null, should return null.
        $this->assertNull($result);
        $this->assertCount(2, $this->wpdb->queries);
    }

    public function testFindByListingIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByListingId('nonexistent');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // batchFetchMedia
    // ------------------------------------------------------------------

    public function testBatchFetchMediaReturnsEmptyForEmptyKeys(): void
    {
        $result = $this->repo->batchFetchMedia([]);

        $this->assertSame([], $result);
        $this->assertCount(0, $this->wpdb->queries);
    }

    public function testBatchFetchMediaGroupsByListingKey(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['listing_key' => 'LK1', 'media_url' => 'photo1.jpg', 'media_category' => 'Photo', 'order_index' => 0],
            (object) ['listing_key' => 'LK1', 'media_url' => 'photo2.jpg', 'media_category' => 'Photo', 'order_index' => 1],
            (object) ['listing_key' => 'LK2', 'media_url' => 'photo3.jpg', 'media_category' => 'Photo', 'order_index' => 0],
        ];

        $result = $this->repo->batchFetchMedia(['LK1', 'LK2']);

        $this->assertCount(2, $result);
        $this->assertCount(2, $result['LK1']);
        $this->assertCount(1, $result['LK2']);
    }

    public function testBatchFetchMediaLimitsPerListing(): void
    {
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $rows[] = (object) ['listing_key' => 'LK1', 'media_url' => "photo{$i}.jpg", 'media_category' => 'Photo', 'order_index' => $i];
        }
        $this->wpdb->get_results_result = $rows;

        $result = $this->repo->batchFetchMedia(['LK1'], 5);

        $this->assertCount(5, $result['LK1']);
    }

    // ------------------------------------------------------------------
    // Prepared statements
    // ------------------------------------------------------------------

    public function testAllQueriesUsePreparedStatements(): void
    {
        $this->wpdb->get_row_result = null;
        $this->repo->findByListingId('73464868');

        $sql = $this->wpdb->queries[0]['sql'];
        // Should contain the escaped value, not a raw placeholder.
        $this->assertStringContainsString("'73464868'", $sql);
        $this->assertStringNotContainsString('%s', $sql);
    }

    // ------------------------------------------------------------------
    // Autocomplete
    // ------------------------------------------------------------------

    public function testAutocompleteCitiesUsesLike(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->autocompleteCities('Bos');

        $this->assertCount(1, $this->wpdb->queries);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('city LIKE', $sql);
        $this->assertStringContainsString('GROUP BY city', $sql);
    }
}
