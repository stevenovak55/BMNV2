<?php

declare(strict_types=1);

namespace BMN\Extractor\Service;

use BMN\Extractor\Repository\ExtractionRepository;
use BMN\Extractor\Repository\MediaRepository;
use BMN\Extractor\Repository\AgentRepository;
use BMN\Extractor\Repository\OfficeRepository;
use BMN\Extractor\Repository\OpenHouseRepository;
use BMN\Extractor\Repository\PropertyHistoryRepository;
use BMN\Extractor\Repository\PropertyRepository;
use RuntimeException;
use wpdb;

/**
 * Core orchestrator for MLS data extraction.
 *
 * Manages the full lifecycle of an extraction run:
 *   1. Acquire MySQL lock to prevent concurrent runs
 *   2. Create extraction record for tracking
 *   3. Fetch listings from Bridge API in 200-listing batches
 *   4. Normalize and upsert into bmn_properties
 *   5. Fetch related data (agents, offices, media, open houses)
 *   6. Track changes in property history
 *   7. Pause at 1000-listing session limit for cron continuation
 */
class ExtractionEngine
{
    private const LOCK_NAME = 'bmn_extraction_lock';
    private const LOCK_TIMEOUT = 10;
    private const SESSION_LIMIT = 1000;
    private const BATCH_SIZE = 200;
    private const MAX_CONSECUTIVE_ERRORS = 5;

    private wpdb $wpdb;
    private BridgeApiClient $apiClient;
    private DataNormalizer $normalizer;
    private PropertyRepository $properties;
    private MediaRepository $media;
    private AgentRepository $agents;
    private OfficeRepository $offices;
    private OpenHouseRepository $openHouses;
    private ExtractionRepository $extractions;
    private PropertyHistoryRepository $history;

    public function __construct(
        wpdb $wpdb,
        BridgeApiClient $apiClient,
        DataNormalizer $normalizer,
        PropertyRepository $properties,
        MediaRepository $media,
        AgentRepository $agents,
        OfficeRepository $offices,
        OpenHouseRepository $openHouses,
        ExtractionRepository $extractions,
        PropertyHistoryRepository $history,
    ) {
        $this->wpdb = $wpdb;
        $this->apiClient = $apiClient;
        $this->normalizer = $normalizer;
        $this->properties = $properties;
        $this->media = $media;
        $this->agents = $agents;
        $this->offices = $offices;
        $this->openHouses = $openHouses;
        $this->extractions = $extractions;
        $this->history = $history;
    }

    /**
     * Run an extraction session.
     *
     * @param bool   $isResync    If true, fetches all listings (not incremental).
     * @param string $triggeredBy Who triggered the run ('cron', 'manual', 'continuation').
     * @return array{extraction_id: int, status: string, processed: int, created: int, updated: int}
     */
    public function run(bool $isResync = false, string $triggeredBy = 'cron'): array
    {
        // Check credentials first.
        if (! $this->apiClient->hasCredentials()) {
            throw new RuntimeException('Bridge API credentials not configured.');
        }

        // Acquire database-level lock.
        $lockAcquired = $this->acquireLock();
        if (! $lockAcquired) {
            throw new RuntimeException('Could not acquire extraction lock — another extraction may be running.');
        }

        // Check for paused run to continue.
        $extractionId = null;
        $lastModified = null;

        if ($triggeredBy === 'continuation') {
            $paused = $this->extractions->getLastPausedRun();
            if ($paused) {
                $extractionId = (int) $paused->id;
                $lastModified = $paused->last_modification_timestamp;
                $this->extractions->updateMetrics($extractionId, ['status' => 'running']);
            }
        }

        if ($extractionId === null) {
            $type = $isResync ? 'full' : 'incremental';
            $extractionId = $this->extractions->startRun($type, $triggeredBy);
        }

        // Build filter.
        if ($isResync) {
            $filter = $this->apiClient->buildResyncFilter();
        } else {
            if ($lastModified === null) {
                $lastModified = $this->properties->getLastModificationTimestamp();
            }
            $filter = $this->apiClient->buildIncrementalFilter($lastModified);
        }

        $stats = [
            'extraction_id' => $extractionId,
            'status' => 'completed',
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'archived' => 0,
            'errors' => 0,
        ];

        try {
            $sessionProcessed = 0;

            $this->apiClient->fetchListings(
                $filter,
                function (array $listings, int $totalProcessed) use ($extractionId, &$stats, &$sessionProcessed): ?array {
                    $batchResult = $this->processBatch($extractionId, $listings);

                    $stats['processed'] += $batchResult['processed'];
                    $stats['created'] += $batchResult['created'];
                    $stats['updated'] += $batchResult['updated'];
                    $stats['errors'] += $batchResult['errors'];
                    $sessionProcessed += $batchResult['processed'];

                    // Update extraction metrics.
                    $this->extractions->updateMetrics($extractionId, [
                        'listings_processed' => $stats['processed'],
                        'listings_created' => $stats['created'],
                        'listings_updated' => $stats['updated'],
                        'errors_count' => $stats['errors'],
                    ]);

                    // Check session limit.
                    if ($sessionProcessed >= self::SESSION_LIMIT) {
                        return ['stop_session' => true];
                    }

                    return null;
                },
                self::SESSION_LIMIT
            );

            // Determine final status.
            if ($sessionProcessed >= self::SESSION_LIMIT) {
                // Paused — schedule continuation.
                $lastMod = $this->properties->getLastModificationTimestamp();
                $this->extractions->updateMetrics($extractionId, [
                    'last_modification_timestamp' => $lastMod,
                ]);
                $this->extractions->pauseRun($extractionId);
                $stats['status'] = 'paused';
            } else {
                $this->extractions->completeRun($extractionId);
                $stats['status'] = 'completed';
            }
        } catch (\Throwable $e) {
            $this->extractions->failRun($extractionId, $e->getMessage());
            $stats['status'] = 'failed';
            throw $e;
        } finally {
            $this->releaseLock();
        }

        return $stats;
    }

    /**
     * Process a batch of API listings.
     *
     * @return array{processed: int, created: int, updated: int, errors: int}
     */
    private function processBatch(int $extractionId, array $listings): array
    {
        $result = ['processed' => 0, 'created' => 0, 'updated' => 0, 'errors' => 0];

        // Collect IDs for related data fetch.
        $listingKeys = [];
        $agentIds = [];
        $officeIds = [];
        $consecutiveErrors = 0;

        foreach ($listings as $apiListing) {
            try {
                $normalized = $this->normalizer->normalizeProperty($apiListing);
                $listingKey = $normalized['listing_key'] ?? null;

                if ($listingKey === null) {
                    $result['errors']++;
                    $consecutiveErrors++;
                    if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                        throw new RuntimeException(
                            "Aborting batch: {$consecutiveErrors} consecutive errors exceeded threshold."
                        );
                    }
                    continue;
                }

                // Check for existing record to detect changes.
                $existing = $this->properties->findByListingKey($listingKey);

                if ($existing) {
                    $changes = $this->normalizer->detectChanges($existing, $normalized);
                    if (! empty($changes)) {
                        $this->history->logChanges($listingKey, $changes);
                    }
                }

                // Upsert property.
                $action = $this->properties->upsert($normalized);
                $result[$action]++;
                $result['processed']++;
                $consecutiveErrors = 0;

                $listingKeys[] = $listingKey;

                // Collect agent/office IDs for batch lookup.
                if (! empty($normalized['list_agent_mls_id'])) {
                    $agentIds[] = $normalized['list_agent_mls_id'];
                }
                if (! empty($normalized['buyer_agent_mls_id'])) {
                    $agentIds[] = $normalized['buyer_agent_mls_id'];
                }
                if (! empty($normalized['list_office_mls_id'])) {
                    $officeIds[] = $normalized['list_office_mls_id'];
                }
                if (! empty($normalized['buyer_office_mls_id'])) {
                    $officeIds[] = $normalized['buyer_office_mls_id'];
                }
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $result['errors']++;
                $consecutiveErrors++;
                if ($consecutiveErrors >= self::MAX_CONSECUTIVE_ERRORS) {
                    throw new RuntimeException(
                        "Aborting batch: {$consecutiveErrors} consecutive errors exceeded threshold."
                    );
                }
                error_log("BMN Extractor: Error processing listing: " . $e->getMessage());
            }
        }

        // Fetch and store related data.
        $this->processRelatedData($listingKeys, $agentIds, $officeIds);

        return $result;
    }

    /**
     * Fetch and store agents, offices, and media for the processed batch.
     */
    private function processRelatedData(array $listingKeys, array $agentIds, array $officeIds): void
    {
        // Agents.
        if (! empty($agentIds)) {
            try {
                $apiAgents = $this->apiClient->fetchRelatedResource('Member', 'MemberMlsId', $agentIds);
                foreach ($apiAgents as $apiAgent) {
                    $normalized = $this->normalizer->normalizeAgent($apiAgent);
                    if (! empty($normalized['agent_mls_id'])) {
                        $this->agents->upsert($normalized);
                    }
                }
            } catch (\Throwable $e) {
                error_log("BMN Extractor: Error fetching agents: " . $e->getMessage());
            }
        }

        // Offices.
        if (! empty($officeIds)) {
            try {
                $apiOffices = $this->apiClient->fetchRelatedResource('Office', 'OfficeMlsId', $officeIds);
                foreach ($apiOffices as $apiOffice) {
                    $normalized = $this->normalizer->normalizeOffice($apiOffice);
                    if (! empty($normalized['office_mls_id'])) {
                        $this->offices->upsert($normalized);
                    }
                }
            } catch (\Throwable $e) {
                error_log("BMN Extractor: Error fetching offices: " . $e->getMessage());
            }
        }

        // Media.
        if (! empty($listingKeys)) {
            try {
                $apiMedia = $this->apiClient->fetchMediaForListings($listingKeys);

                // Group media by listing key.
                $grouped = [];
                foreach ($apiMedia as $item) {
                    $key = $item['ResourceRecordKey'] ?? null;
                    if ($key !== null) {
                        $grouped[$key][] = $item;
                    }
                }

                foreach ($grouped as $listingKey => $mediaItems) {
                    $normalizedMedia = [];
                    foreach ($mediaItems as $item) {
                        $normalizedMedia[] = $this->normalizer->normalizeMedia($item, $listingKey);
                    }
                    $this->media->replaceForListing($listingKey, $normalizedMedia);

                    // Update main_photo_url on the property.
                    if (! empty($normalizedMedia)) {
                        usort($normalizedMedia, fn($a, $b) => ($a['order_index'] ?? 0) <=> ($b['order_index'] ?? 0));
                        $mainPhotoUrl = $normalizedMedia[0]['media_url'] ?? null;
                        if ($mainPhotoUrl) {
                            $property = $this->properties->findByListingKey($listingKey);
                            if ($property) {
                                $this->properties->update($property->id, [
                                    'main_photo_url' => $mainPhotoUrl,
                                    'photo_count' => count($normalizedMedia),
                                ]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log("BMN Extractor: Error fetching media: " . $e->getMessage());
            }
        }

        // Open houses.
        if (!empty($listingKeys)) {
            try {
                $apiOpenHouses = $this->apiClient->fetchRelatedResource('OpenHouse', 'ListingKey', $listingKeys);
                $grouped = [];
                foreach ($apiOpenHouses as $item) {
                    $key = $item['ListingKey'] ?? null;
                    if ($key !== null) {
                        $grouped[$key][] = $item;
                    }
                }
                foreach ($grouped as $listingKey => $items) {
                    $normalized = array_map(
                        fn(array $oh) => $this->normalizer->normalizeOpenHouse($oh, $listingKey),
                        $items
                    );
                    $this->openHouses->replaceForListing($listingKey, $normalized);
                }
            } catch (\Throwable $e) {
                error_log("BMN Extractor: Error fetching open houses: " . $e->getMessage());
            }
        }
    }

    /**
     * Acquire MySQL advisory lock.
     */
    private function acquireLock(): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT GET_LOCK(%s, %d)",
                self::LOCK_NAME,
                self::LOCK_TIMEOUT
            )
        );

        return (int) $result === 1;
    }

    /**
     * Release MySQL advisory lock.
     */
    private function releaseLock(): void
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "SELECT RELEASE_LOCK(%s)",
                self::LOCK_NAME
            )
        );
    }
}
