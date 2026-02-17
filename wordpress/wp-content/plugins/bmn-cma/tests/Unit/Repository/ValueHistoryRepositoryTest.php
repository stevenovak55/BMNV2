<?php

declare(strict_types=1);

namespace BMN\CMA\Tests\Unit\Repository;

use BMN\CMA\Repository\ValueHistoryRepository;
use PHPUnit\Framework\TestCase;

final class ValueHistoryRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ValueHistoryRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->wpdb->prefix = 'wp_';
        $this->repo = new ValueHistoryRepository($this->wpdb);
    }

    public function testCreateSetsCreatedAtAutomatically(): void
    {
        $this->wpdb->insert_id = 0;

        $id = $this->repo->create([
            'listing_id'          => 'MLS100',
            'estimated_value_mid' => 500000.00,
        ]);

        $this->assertSame(1, $id);

        $insertData = end($this->wpdb->queries)['args'];
        $this->assertArrayHasKey('created_at', $insertData);
        $this->assertNotEmpty($insertData['created_at']);
    }

    public function testCreateDoesNotSetUpdatedAt(): void
    {
        // timestamps is false, so parent::create should not set updated_at.
        // However, our create() also sets created_at explicitly.
        $this->repo->create(['listing_id' => 'MLS100']);

        $insertData = end($this->wpdb->queries)['args'];
        // The parent sets updated_at only when $this->timestamps is true.
        // Since timestamps = false, updated_at should NOT be in the data.
        $this->assertArrayNotHasKey('updated_at', $insertData);
    }

    public function testFindByListingReturnsResults(): void
    {
        $row1 = (object) ['id' => 1, 'listing_id' => 'MLS100', 'estimated_value_mid' => 500000];
        $row2 = (object) ['id' => 2, 'listing_id' => 'MLS100', 'estimated_value_mid' => 510000];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->findByListing('MLS100', 50);

        $this->assertCount(2, $results);
        $this->assertSame('MLS100', $results[0]->listing_id);
    }

    public function testFindByListingReturnsEmptyOnNull(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->findByListing('MLS100');

        $this->assertSame([], $results);
    }

    public function testFindByUserReturnsResults(): void
    {
        $row = (object) ['id' => 1, 'user_id' => 42];
        $this->wpdb->get_results_result = [$row];

        $results = $this->repo->findByUser(42, 50);

        $this->assertCount(1, $results);
        $this->assertSame(42, $results[0]->user_id);
    }

    public function testGetTrendsReturnsChronologicalData(): void
    {
        $row1 = (object) ['id' => 1, 'listing_id' => 'MLS100', 'created_at' => '2025-01-01'];
        $row2 = (object) ['id' => 2, 'listing_id' => 'MLS100', 'created_at' => '2025-06-01'];
        $this->wpdb->get_results_result = [$row1, $row2];

        $results = $this->repo->getTrends('MLS100');

        $this->assertCount(2, $results);

        // Verify the query uses ASC ordering (for charting).
        $lastQuery = end($this->wpdb->queries);
        $this->assertStringContainsString('ORDER BY created_at ASC', $lastQuery['sql']);
    }

    public function testGetTrendsReturnsEmptyOnNull(): void
    {
        $this->wpdb->get_results_result = null;

        $results = $this->repo->getTrends('MLS100');

        $this->assertSame([], $results);
    }
}
