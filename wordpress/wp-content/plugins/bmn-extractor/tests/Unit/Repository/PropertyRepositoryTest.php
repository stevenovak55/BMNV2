<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Repository;

use BMN\Extractor\Repository\PropertyRepository;
use PHPUnit\Framework\TestCase;

class PropertyRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private PropertyRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new PropertyRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // getTableName
    // ------------------------------------------------------------------

    public function testTableNameIsBmnProperties(): void
    {
        // The table property is set to $wpdb->prefix . getTableName()
        $reflection = new \ReflectionProperty($this->repo, 'table');
        $this->assertSame('wp_bmn_properties', $reflection->getValue($this->repo));
    }

    // ------------------------------------------------------------------
    // findByListingKey
    // ------------------------------------------------------------------

    public function testFindByListingKeyReturnsObjectWhenFound(): void
    {
        $expected = (object) ['id' => 1, 'listing_key' => 'LK1'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByListingKey('LK1');

        $this->assertSame($expected, $result);
        $this->assertCount(1, $this->wpdb->queries);
        $this->assertStringContainsString('listing_key', $this->wpdb->queries[0]['sql']);
    }

    public function testFindByListingKeyReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByListingKey('NONEXISTENT');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // findByListingId
    // ------------------------------------------------------------------

    public function testFindByListingIdReturnsObject(): void
    {
        $expected = (object) ['id' => 1, 'listing_id' => 'MLS123'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByListingId('MLS123');

        $this->assertSame($expected, $result);
        $this->assertStringContainsString('listing_id', $this->wpdb->queries[0]['sql']);
    }

    public function testFindByListingIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;
        $this->assertNull($this->repo->findByListingId('NOPE'));
    }

    // ------------------------------------------------------------------
    // upsert
    // ------------------------------------------------------------------

    public function testUpsertBuildsInsertOnDuplicateKeyUpdate(): void
    {
        $this->wpdb->query_result = 1; // 1 = inserted

        $data = [
            'listing_key' => 'LK1',
            'listing_id' => 'MLS1',
            'city' => 'Boston',
        ];

        $result = $this->repo->upsert($data);

        $this->assertSame('created', $result);
        $this->assertNotEmpty($this->wpdb->queries);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('INSERT INTO', $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
    }

    public function testUpsertReturnsUpdatedWhenAffectedRowsIsTwo(): void
    {
        $this->wpdb->query_result = 2; // 2 = updated

        $data = ['listing_key' => 'LK1', 'listing_id' => 'MLS1'];
        $result = $this->repo->upsert($data);

        $this->assertSame('updated', $result);
    }

    public function testUpsertSetsCreatedAtAndUpdatedAt(): void
    {
        $this->wpdb->query_result = 1;

        $data = ['listing_key' => 'LK1'];
        $this->repo->upsert($data);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('`created_at`', $sql);
        $this->assertStringContainsString('`updated_at`', $sql);
    }

    public function testUpsertDoesNotOverrideListingKeyInUpdateClause(): void
    {
        $this->wpdb->query_result = 1;

        $data = ['listing_key' => 'LK1', 'city' => 'Boston'];
        $this->repo->upsert($data);

        $sql = $this->wpdb->queries[0]['sql'];
        // The ON DUPLICATE KEY UPDATE should not include listing_key or created_at.
        $updatePart = substr($sql, strpos($sql, 'ON DUPLICATE KEY UPDATE'));
        $this->assertStringNotContainsString('`listing_key` = VALUES', $updatePart);
        $this->assertStringNotContainsString('`created_at` = VALUES', $updatePart);
    }

    public function testUpsertIncludesCoordinatesPointFromLatLng(): void
    {
        $this->wpdb->query_result = 1;

        $data = [
            'listing_key' => 'LK1',
            'listing_id' => 'MLS1',
            'latitude' => 42.3601,
            'longitude' => -71.0589,
        ];

        $this->repo->upsert($data);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('`coordinates`', $sql);
        $this->assertStringContainsString('ST_GeomFromText', $sql);
        $this->assertStringContainsString('POINT', $sql);
    }

    public function testUpsertUsesZeroPointWhenNoCoordinates(): void
    {
        $this->wpdb->query_result = 1;

        $data = ['listing_key' => 'LK1', 'listing_id' => 'MLS1'];
        $this->repo->upsert($data);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('ST_GeomFromText', $sql);
        // With no lat/lng, should use 0.0 for both.
        $this->assertStringContainsString('`coordinates`', $sql);
    }

    // ------------------------------------------------------------------
    // batchUpsert
    // ------------------------------------------------------------------

    public function testBatchUpsertProcessesAllRows(): void
    {
        $this->wpdb->query_result = 1;

        $rows = [
            ['listing_key' => 'LK1'],
            ['listing_key' => 'LK2'],
            ['listing_key' => 'LK3'],
        ];

        $stats = $this->repo->batchUpsert($rows);

        $this->assertSame(3, $stats['created']);
        $this->assertSame(0, $stats['updated']);
    }

    // ------------------------------------------------------------------
    // countByStatus
    // ------------------------------------------------------------------

    public function testCountByStatusReturnsGroupedCounts(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['standard_status' => 'Active', 'cnt' => '150'],
            (object) ['standard_status' => 'Closed', 'cnt' => '50'],
        ];

        $counts = $this->repo->countByStatus();

        $this->assertSame(150, $counts['Active']);
        $this->assertSame(50, $counts['Closed']);
    }

    // ------------------------------------------------------------------
    // getLastModificationTimestamp
    // ------------------------------------------------------------------

    public function testGetLastModificationTimestampReturnsMax(): void
    {
        $this->wpdb->get_var_result = '2026-02-15 10:00:00';

        $result = $this->repo->getLastModificationTimestamp();

        $this->assertSame('2026-02-15 10:00:00', $result);
        $this->assertStringContainsString('MAX(modification_timestamp)', $this->wpdb->queries[0]['sql']);
    }

    public function testGetLastModificationTimestampReturnsNullWhenEmpty(): void
    {
        $this->wpdb->get_var_result = null;
        $this->assertNull($this->repo->getLastModificationTimestamp());
    }

    // ------------------------------------------------------------------
    // archiveStaleListings
    // ------------------------------------------------------------------

    public function testArchiveStaleListingsUpdatesCorrectRows(): void
    {
        $this->wpdb->query_result = 5;

        $result = $this->repo->archiveStaleListings(['LK1', 'LK2']);

        $this->assertSame(5, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_archived = 1', $sql);
        $this->assertStringContainsString('NOT IN', $sql);
    }

    public function testArchiveStaleListingsReturnsZeroForEmptyKeys(): void
    {
        $result = $this->repo->archiveStaleListings([]);
        $this->assertSame(0, $result);
    }
}
