<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Repository;

use BMN\Extractor\Repository\PropertyHistoryRepository;
use PHPUnit\Framework\TestCase;

class PropertyHistoryRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private PropertyHistoryRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new PropertyHistoryRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // logChange
    // ------------------------------------------------------------------

    public function testLogChangeInsertsRecord(): void
    {
        $result = $this->repo->logChange('LK1', 'price_change', 'list_price', '500000', '525000');

        $this->assertIsInt($result);
        $this->assertNotEmpty($this->wpdb->queries);
        $insertData = $this->wpdb->queries[0]['args'];
        $this->assertSame('LK1', $insertData['listing_key']);
        $this->assertSame('price_change', $insertData['change_type']);
        $this->assertSame('list_price', $insertData['field_name']);
        $this->assertSame('500000', $insertData['old_value']);
        $this->assertSame('525000', $insertData['new_value']);
    }

    // ------------------------------------------------------------------
    // logChanges — auto-classification
    // ------------------------------------------------------------------

    public function testLogChangesClassifiesPriceChange(): void
    {
        $changes = [
            ['field' => 'list_price', 'old_value' => '500000', 'new_value' => '525000'],
        ];

        $logged = $this->repo->logChanges('LK1', $changes);

        $this->assertSame(1, $logged);
        $insertData = $this->wpdb->queries[0]['args'];
        $this->assertSame('price_change', $insertData['change_type']);
    }

    public function testLogChangesClassifiesStatusChange(): void
    {
        $changes = [
            ['field' => 'standard_status', 'old_value' => 'Active', 'new_value' => 'Pending'],
        ];

        $logged = $this->repo->logChanges('LK1', $changes);

        $this->assertSame(1, $logged);
        $insertData = $this->wpdb->queries[0]['args'];
        $this->assertSame('status_change', $insertData['change_type']);
    }

    public function testLogChangesClassifiesMlsStatusChange(): void
    {
        $changes = [
            ['field' => 'mls_status', 'old_value' => 'Active', 'new_value' => 'Closed'],
        ];

        $this->repo->logChanges('LK1', $changes);
        $this->assertSame('status_change', $this->wpdb->queries[0]['args']['change_type']);
    }

    public function testLogChangesClassifiesFieldChange(): void
    {
        $changes = [
            ['field' => 'city', 'old_value' => 'Boston', 'new_value' => 'Cambridge'],
        ];

        $this->repo->logChanges('LK1', $changes);
        $this->assertSame('field_change', $this->wpdb->queries[0]['args']['change_type']);
    }

    public function testLogChangesHandlesNullValues(): void
    {
        $changes = [
            ['field' => 'city', 'old_value' => null, 'new_value' => 'Boston'],
        ];

        $logged = $this->repo->logChanges('LK1', $changes);

        $this->assertSame(1, $logged);
        $insertData = $this->wpdb->queries[0]['args'];
        $this->assertNull($insertData['old_value']);
        $this->assertSame('Boston', $insertData['new_value']);
    }

    // ------------------------------------------------------------------
    // getForListing
    // ------------------------------------------------------------------

    public function testGetForListingReturnsResults(): void
    {
        $rows = [
            (object) ['id' => 1, 'listing_key' => 'LK1', 'change_type' => 'price_change'],
        ];
        $this->wpdb->get_results_result = $rows;

        $result = $this->repo->getForListing('LK1');

        $this->assertCount(1, $result);
        $this->assertStringContainsString('listing_key', $this->wpdb->queries[0]['sql']);
        $this->assertStringNotContainsString('change_type =', $this->wpdb->queries[0]['sql']);
    }

    public function testGetForListingFiltersByChangeType(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->getForListing('LK1', 'price_change');

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('change_type', $sql);
        $this->assertStringContainsString("'price_change'", $sql);
    }
}
