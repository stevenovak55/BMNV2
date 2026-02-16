<?php

declare(strict_types=1);

namespace BMN\Properties\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Properties\Model\PropertyDetail;
use BMN\Properties\Repository\PropertySearchRepository;

/**
 * Single property detail service.
 *
 * Fetches a property by listing_id (MLS number), aggregates related data
 * (photos, agent, office, open houses, history), and caches the result.
 */
class PropertyDetailService
{
    private PropertySearchRepository $repository;
    private CacheService $cache;

    private const CACHE_GROUP = 'property_detail';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        PropertySearchRepository $repository,
        CacheService $cache,
    ) {
        $this->repository = $repository;
        $this->cache = $cache;
    }

    /**
     * Get full property detail by MLS listing_id.
     *
     * @param string $listingId MLS number (NOT listing_key hash).
     * @return array<string, mixed>|null Full property detail or null if not found.
     */
    public function getByListingId(string $listingId): ?array
    {
        $cacheKey = 'detail_' . $listingId;

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($listingId) {
            return $this->fetchDetail($listingId);
        }, self::CACHE_GROUP);
    }

    /**
     * Fetch and assemble the full property detail (uncached).
     */
    private function fetchDetail(string $listingId): ?array
    {
        $property = $this->repository->findByListingId($listingId);

        if ($property === null) {
            return null;
        }

        $listingKey = $property->listing_key;

        // Fetch related data.
        $photos = $this->repository->fetchAllMedia($listingKey);

        $agent = !empty($property->list_agent_mls_id)
            ? $this->repository->findAgent($property->list_agent_mls_id)
            : null;

        $office = !empty($property->list_office_mls_id)
            ? $this->repository->findOffice($property->list_office_mls_id)
            : null;

        $openHouses = $this->repository->fetchUpcomingOpenHouses($listingKey);
        $history = $this->repository->fetchPropertyHistory($listingKey);

        return PropertyDetail::fromData(
            $property,
            $photos,
            $agent,
            $office,
            $openHouses,
            $history,
        );
    }
}
