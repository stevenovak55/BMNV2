<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Service;

use BMN\Extractor\Repository\AgentRepository;
use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\MediaRepository;
use BMN\Extractor\Repository\OfficeRepository;
use BMN\Extractor\Repository\OpenHouseRepository;
use BMN\Extractor\Repository\PropertyHistoryRepository;
use BMN\Extractor\Repository\PropertyRepository;
use BMN\Extractor\Service\BridgeApiClient;
use BMN\Extractor\Service\DataNormalizer;
use BMN\Extractor\Service\ExtractionEngine;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExtractionEngineTest extends TestCase
{
    private \wpdb&MockObject $wpdb;
    private BridgeApiClient&MockObject $apiClient;
    private DataNormalizer&MockObject $normalizer;
    private PropertyRepository&MockObject $properties;
    private MediaRepository&MockObject $media;
    private AgentRepository&MockObject $agents;
    private OfficeRepository&MockObject $offices;
    private OpenHouseRepository&MockObject $openHouses;
    private ExtractionRepository&MockObject $extractions;
    private PropertyHistoryRepository&MockObject $history;
    private ExtractionEngine $engine;

    protected function setUp(): void
    {
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->apiClient = $this->createMock(BridgeApiClient::class);
        $this->normalizer = $this->createMock(DataNormalizer::class);
        $this->properties = $this->createMock(PropertyRepository::class);
        $this->media = $this->createMock(MediaRepository::class);
        $this->agents = $this->createMock(AgentRepository::class);
        $this->offices = $this->createMock(OfficeRepository::class);
        $this->openHouses = $this->createMock(OpenHouseRepository::class);
        $this->extractions = $this->createMock(ExtractionRepository::class);
        $this->history = $this->createMock(PropertyHistoryRepository::class);

        $this->engine = new ExtractionEngine(
            $this->wpdb,
            $this->apiClient,
            $this->normalizer,
            $this->properties,
            $this->media,
            $this->agents,
            $this->offices,
            $this->openHouses,
            $this->extractions,
            $this->history,
        );

        // Default: lock acquisition succeeds.
        $this->wpdb->method('get_var')->willReturn('1');
        $this->wpdb->method('query')->willReturn(1);
    }

    // ------------------------------------------------------------------
    // run() — credential check
    // ------------------------------------------------------------------

    public function testRunThrowsWithoutCredentials(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bridge API credentials not configured');

        $this->engine->run();
    }

    // ------------------------------------------------------------------
    // run() — lock acquisition
    // ------------------------------------------------------------------

    public function testRunThrowsWhenCannotAcquireLock(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        // Override default: lock fails.
        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->method('get_var')->willReturn('0');
        $this->wpdb->method('query')->willReturn(1);

        $engine = new ExtractionEngine(
            $this->wpdb,
            $this->apiClient,
            $this->normalizer,
            $this->properties,
            $this->media,
            $this->agents,
            $this->offices,
            $this->openHouses,
            $this->extractions,
            $this->history,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not acquire extraction lock');

        $engine->run();
    }

    // ------------------------------------------------------------------
    // run() — extraction record creation
    // ------------------------------------------------------------------

    public function testRunCreatesExtractionRecord(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->expects($this->once())
            ->method('startRun')
            ->with('incremental', 'cron')
            ->willReturn(42);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->apiClient->method('fetchListings')->willReturn(0);
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        $result = $this->engine->run(isResync: false, triggeredBy: 'cron');

        $this->assertSame(42, $result['extraction_id']);
    }

    public function testRunCreatesFullResyncRecord(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->expects($this->once())
            ->method('startRun')
            ->with('full', 'manual')
            ->willReturn(99);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('buildResyncFilter')->willReturn("StandardStatus eq 'Active'");
        $this->apiClient->method('fetchListings')->willReturn(0);

        $result = $this->engine->run(isResync: true, triggeredBy: 'manual');

        $this->assertSame(99, $result['extraction_id']);
    }

    // ------------------------------------------------------------------
    // run() — filter selection
    // ------------------------------------------------------------------

    public function testRunUsesIncrementalFilter(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('fetchListings')->willReturn(0);

        $this->properties->method('getLastModificationTimestamp')
            ->willReturn('2026-02-15 10:00:00');

        $this->apiClient->expects($this->once())
            ->method('buildIncrementalFilter')
            ->with('2026-02-15 10:00:00')
            ->willReturn('ModificationTimestamp gt 2026-02-15T10:00:00Z');

        $this->engine->run(isResync: false);
    }

    public function testRunUsesResyncFilter(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('fetchListings')->willReturn(0);

        $this->apiClient->expects($this->once())
            ->method('buildResyncFilter')
            ->willReturn("StandardStatus eq 'Active'");

        $this->engine->run(isResync: true);
    }

    // ------------------------------------------------------------------
    // run() — batch processing
    // ------------------------------------------------------------------

    public function testRunProcessesBatchAndReturnsStats(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        $listing = ['ListingKey' => 'LK1', 'ListingId' => 'MLS1', 'StandardStatus' => 'Active'];
        $normalized = ['listing_key' => 'LK1', 'listing_id' => 'MLS1', 'standard_status' => 'Active'];

        // fetchListings calls the callback with our listing batch.
        $this->apiClient->method('fetchListings')
            ->willReturnCallback(function ($filter, $callback) use ($listing) {
                $callback([$listing], 1);
                return 1;
            });

        $this->normalizer->method('normalizeProperty')->willReturn($normalized);
        $this->properties->method('findByListingKey')->willReturn(null);
        $this->properties->method('upsert')->willReturn('created');
        $this->apiClient->method('fetchRelatedResource')->willReturn([]);
        $this->apiClient->method('fetchMediaForListings')->willReturn([]);

        $result = $this->engine->run();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame('completed', $result['status']);
    }

    // ------------------------------------------------------------------
    // run() — session limit / pause
    // ------------------------------------------------------------------

    public function testRunPausesAtSessionLimit(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        // Simulate processing more than session limit (1000).
        // fetchListings will call the callback multiple times, each time with 200 listings.
        $this->apiClient->method('fetchListings')
            ->willReturnCallback(function ($filter, $callback) {
                $batch = array_fill(0, 200, ['ListingKey' => 'LK', 'ListingId' => 'MLS', 'StandardStatus' => 'Active']);
                for ($i = 0; $i < 6; $i++) {
                    $result = $callback($batch, ($i + 1) * 200);
                    if (is_array($result) && ($result['stop_session'] ?? false)) {
                        break;
                    }
                }
                return 1200;
            });

        $this->normalizer->method('normalizeProperty')->willReturn([
            'listing_key' => 'LK', 'listing_id' => 'MLS',
        ]);
        $this->properties->method('findByListingKey')->willReturn(null);
        $this->properties->method('upsert')->willReturn('created');
        $this->apiClient->method('fetchRelatedResource')->willReturn([]);
        $this->apiClient->method('fetchMediaForListings')->willReturn([]);

        $this->extractions->expects($this->once())
            ->method('pauseRun')
            ->with(1);

        $result = $this->engine->run();

        $this->assertSame('paused', $result['status']);
    }

    // ------------------------------------------------------------------
    // run() — continuation from paused
    // ------------------------------------------------------------------

    public function testRunContinuesFromPausedExtraction(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->apiClient->method('fetchListings')->willReturn(0);
        $this->apiClient->method('fetchRelatedResource')->willReturn([]);
        $this->apiClient->method('fetchMediaForListings')->willReturn([]);

        $pausedRun = (object) [
            'id' => 42,
            'last_modification_timestamp' => '2026-02-15 10:00:00',
        ];
        $this->extractions->method('getLastPausedRun')->willReturn($pausedRun);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);

        // Should NOT call startRun since we're continuing a paused one.
        $this->extractions->expects($this->never())->method('startRun');

        $result = $this->engine->run(isResync: false, triggeredBy: 'continuation');

        $this->assertSame(42, $result['extraction_id']);
    }

    // ------------------------------------------------------------------
    // run() — error handling
    // ------------------------------------------------------------------

    public function testRunFailsExtractionRecordOnException(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        $this->apiClient->method('fetchListings')
            ->willThrowException(new RuntimeException('API down'));

        $this->extractions->expects($this->once())
            ->method('failRun')
            ->with(1, 'API down');

        $this->expectException(RuntimeException::class);
        $this->engine->run();
    }

    // ------------------------------------------------------------------
    // processBatch — change detection
    // ------------------------------------------------------------------

    public function testProcessBatchDetectsAndLogsChanges(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        $normalized = ['listing_key' => 'LK1', 'list_price' => 600000];
        $existing = (object) ['listing_key' => 'LK1', 'list_price' => '500000'];

        $this->apiClient->method('fetchListings')
            ->willReturnCallback(function ($filter, $callback) {
                $callback([['ListingKey' => 'LK1']], 1);
                return 1;
            });

        $this->normalizer->method('normalizeProperty')->willReturn($normalized);
        $this->properties->method('findByListingKey')->willReturn($existing);
        $this->properties->method('upsert')->willReturn('updated');
        $this->normalizer->method('detectChanges')->willReturn([
            ['field' => 'list_price', 'old_value' => '500000', 'new_value' => 600000],
        ]);
        $this->apiClient->method('fetchRelatedResource')->willReturn([]);
        $this->apiClient->method('fetchMediaForListings')->willReturn([]);

        $this->history->expects($this->once())
            ->method('logChanges')
            ->with('LK1', $this->isType('array'));

        $this->engine->run();
    }

    // ------------------------------------------------------------------
    // processRelatedData — open houses
    // ------------------------------------------------------------------

    public function testProcessRelatedDataFetchesOpenHouses(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        $this->apiClient->method('fetchListings')
            ->willReturnCallback(function ($filter, $callback) {
                $callback([['ListingKey' => 'LK1', 'StandardStatus' => 'Active']], 1);
                return 1;
            });

        $this->normalizer->method('normalizeProperty')->willReturn([
            'listing_key' => 'LK1', 'standard_status' => 'Active',
        ]);
        $this->properties->method('findByListingKey')->willReturn(null);
        $this->properties->method('upsert')->willReturn('created');
        $this->apiClient->method('fetchMediaForListings')->willReturn([]);

        // Agents/offices return empty.
        $this->apiClient->method('fetchRelatedResource')
            ->willReturnCallback(function (string $resource) {
                if ($resource === 'OpenHouse') {
                    return [
                        ['ListingKey' => 'LK1', 'OpenHouseKey' => 'OH1', 'OpenHouseDate' => '2026-03-01'],
                    ];
                }
                return [];
            });

        $this->normalizer->method('normalizeOpenHouse')->willReturn([
            'open_house_key' => 'OH1', 'listing_key' => 'LK1',
        ]);

        $this->openHouses->expects($this->once())
            ->method('replaceForListing')
            ->with('LK1', $this->isType('array'));

        $this->engine->run();
    }

    // ------------------------------------------------------------------
    // Consecutive error tracking
    // ------------------------------------------------------------------

    public function testConsecutiveErrorsAbortsBatch(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);

        // Create 5 listings with no ListingKey to trigger consecutive errors.
        $badListings = array_fill(0, 5, ['StandardStatus' => 'Active']);

        $this->apiClient->method('fetchListings')
            ->willReturnCallback(function ($filter, $callback) use ($badListings) {
                $callback($badListings, 5);
                return 5;
            });

        // normalizeProperty returns data without listing_key.
        $this->normalizer->method('normalizeProperty')->willReturn([
            'standard_status' => 'Active',
            // listing_key is missing — triggers the null check.
        ]);

        $this->extractions->expects($this->once())
            ->method('failRun');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('consecutive errors');

        $this->engine->run();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function setupSuccessfulRun(): void
    {
        $this->apiClient->method('hasCredentials')->willReturn(true);
        $this->extractions->method('startRun')->willReturn(1);
        $this->extractions->method('updateMetrics')->willReturn(true);
        $this->extractions->method('completeRun')->willReturn(true);
        $this->apiClient->method('buildIncrementalFilter')->willReturn('test');
        $this->apiClient->method('fetchListings')->willReturn(0);
        $this->properties->method('getLastModificationTimestamp')->willReturn(null);
    }
}
