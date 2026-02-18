<?php

declare(strict_types=1);

namespace BMN\Properties\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Properties\Model\PropertyListItem;
use BMN\Properties\Repository\PropertySearchRepository;
use BMN\Properties\Service\Filter\FilterBuilder;
use BMN\Properties\Service\Filter\FilterResult;

/**
 * Search orchestration service.
 *
 * Coordinates FilterBuilder, repository, caching, and post-query hooks
 * (school filtering) to produce paginated property search results.
 */
class PropertySearchService
{
    private PropertySearchRepository $repository;
    private FilterBuilder $filterBuilder;
    private CacheService $cache;

    private const CACHE_GROUP = 'property_search';
    private const CACHE_TTL = 120; // 2 minutes

    public function __construct(
        PropertySearchRepository $repository,
        FilterBuilder $filterBuilder,
        CacheService $cache,
    ) {
        $this->repository = $repository;
        $this->filterBuilder = $filterBuilder;
        $this->cache = $cache;
    }

    /**
     * Search properties with filters, pagination, and caching.
     *
     * @param array<string, mixed> $filters   Key-value filter pairs from the request.
     * @param int                  $page      Current page (1-based).
     * @param int                  $perPage   Items per page (clamped to [1, 250]).
     *
     * @return array{data: array, total: int, page: int, per_page: int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        // Clamp per_page.
        $perPage = max(1, min(250, $perPage));
        $page = max(1, $page);

        $cacheKey = $this->buildCacheKey($filters, $page, $perPage);

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($filters, $page, $perPage) {
            return $this->executeSearch($filters, $page, $perPage);
        }, self::CACHE_GROUP);
    }

    /**
     * Execute the actual search (uncached).
     */
    private function executeSearch(array $filters, int $page, int $perPage): array
    {
        $filterResult = $this->filterBuilder->build($filters);

        // Calculate fetch limit (overfetch for school filters).
        $fetchLimit = $perPage * $filterResult->overfetchMultiplier;
        $offset = ($page - 1) * $perPage;

        // For overfetch, adjust offset to fetch from the beginning of the overfetch window.
        $fetchOffset = $filterResult->hasSchoolFilters ? 0 : $offset;
        if ($filterResult->hasSchoolFilters) {
            $fetchLimit = max($fetchLimit, ($page * $perPage) * $filterResult->overfetchMultiplier);
        }

        // Query database — use explicit column list to reduce I/O (Fix 7, Session 24).
        $rows = $this->repository->searchProperties(
            PropertySearchRepository::LIST_SELECT,
            $filterResult->where,
            $filterResult->orderBy,
            $fetchLimit,
            $fetchOffset,
        );

        $total = $this->repository->countProperties($filterResult->where);

        // Apply school post-filtering if needed (no-op until Phase 5).
        if ($filterResult->hasSchoolFilters && $rows !== []) {
            $filtered = apply_filters('bmn_filter_by_school', $rows, $filterResult->schoolCriteria);
            if (is_array($filtered)) {
                $total = count($filtered);
                $rows = array_slice($filtered, $offset, $perPage);
            }
        } elseif ($filterResult->hasSchoolFilters) {
            // No rows to filter.
        }

        if ($rows === []) {
            return [
                'data' => [],
                'total' => $filterResult->hasSchoolFilters ? $total : $total,
                'page' => $page,
                'per_page' => $perPage,
            ];
        }

        // Extract listing keys for batch operations.
        $listingKeys = array_map(
            static fn (object $row): string => $row->listing_key,
            $rows
        );

        // Batch-fetch photos and next open houses.
        $mediaByKey = $this->repository->batchFetchMedia($listingKeys, 5);
        $openHousesByKey = $this->repository->batchFetchNextOpenHouses($listingKeys);

        // Format results.
        $data = array_map(
            static function (object $row) use ($mediaByKey, $openHousesByKey): array {
                return PropertyListItem::fromRow(
                    $row,
                    $mediaByKey[$row->listing_key] ?? [],
                    $openHousesByKey[$row->listing_key] ?? null,
                );
            },
            $rows
        );

        return [
            'data' => array_values($data),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Build a deterministic cache key from filters and pagination.
     */
    private function buildCacheKey(array $filters, int $page, int $perPage): string
    {
        ksort($filters);

        return 'search_' . md5(serialize($filters) . "_{$page}_{$perPage}");
    }
}
