<?php

declare(strict_types=1);

namespace BMN\Properties\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Properties\Repository\PropertySearchRepository;

/**
 * Type-ahead autocomplete service for the property search box.
 *
 * Searches cities, zips, neighborhoods, street names, addresses, and MLS
 * numbers in parallel, then merges and ranks results by type priority.
 */
class AutocompleteService
{
    private PropertySearchRepository $repository;
    private CacheService $cache;

    private const CACHE_GROUP = 'autocomplete';
    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_RESULTS = 10;

    /**
     * Priority order for suggestion types (lower = higher priority).
     */
    private const TYPE_PRIORITY = [
        'mls'          => 1,
        'city'         => 2,
        'zip'          => 3,
        'neighborhood' => 4,
        'street'       => 5,
        'address'      => 6,
    ];

    public function __construct(
        PropertySearchRepository $repository,
        CacheService $cache,
    ) {
        $this->repository = $repository;
        $this->cache = $cache;
    }

    /**
     * Get autocomplete suggestions for a search term.
     *
     * @param string $term Search term (minimum 2 characters).
     * @return array<int, array{value: string, type: string, count?: int}>
     */
    public function suggest(string $term): array
    {
        $term = trim($term);

        if (strlen($term) < 2) {
            return [];
        }

        $cacheKey = 'ac_' . md5($term);

        return $this->cache->remember($cacheKey, self::CACHE_TTL, function () use ($term) {
            return $this->buildSuggestions($term);
        }, self::CACHE_GROUP);
    }

    /**
     * Build suggestions from all sources (uncached).
     */
    private function buildSuggestions(string $term): array
    {
        $suggestions = [];

        // MLS number matches.
        $mlsResults = $this->repository->autocompleteMlsNumbers($term);
        foreach ($mlsResults as $row) {
            $suggestions[] = [
                'value' => $row->value,
                'type' => 'mls',
            ];
        }

        // City matches.
        $cityResults = $this->repository->autocompleteCities($term);
        foreach ($cityResults as $row) {
            $suggestions[] = [
                'value' => $row->value,
                'type' => 'city',
                'count' => (int) $row->count,
            ];
        }

        // ZIP matches.
        $zipResults = $this->repository->autocompleteZips($term);
        foreach ($zipResults as $row) {
            $suggestions[] = [
                'value' => $row->value,
                'type' => 'zip',
                'count' => (int) $row->count,
            ];
        }

        // Neighborhood matches.
        $neighborhoodResults = $this->repository->autocompleteNeighborhoods($term);
        foreach ($neighborhoodResults as $row) {
            $suggestions[] = [
                'value' => $row->value,
                'type' => 'neighborhood',
                'count' => (int) $row->count,
            ];
        }

        // Street name matches.
        $streetResults = $this->repository->autocompleteStreetNames($term);
        foreach ($streetResults as $row) {
            $suggestions[] = [
                'value' => $row->value,
                'type' => 'street',
                'count' => (int) $row->count,
            ];
        }

        // Address matches.
        $addressResults = $this->repository->autocompleteAddresses($term);
        foreach ($addressResults as $row) {
            $suggestions[] = [
                'value' => $row->value,
                'type' => 'address',
            ];
        }

        // Deduplicate (same value, keep highest priority type).
        $suggestions = $this->deduplicate($suggestions);

        // Sort by priority, then alphabetically.
        usort($suggestions, function (array $a, array $b): int {
            $priorityA = self::TYPE_PRIORITY[$a['type']] ?? 99;
            $priorityB = self::TYPE_PRIORITY[$b['type']] ?? 99;

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return strcasecmp($a['value'], $b['value']);
        });

        return array_slice($suggestions, 0, self::MAX_RESULTS);
    }

    /**
     * Deduplicate suggestions by value, keeping the highest-priority type.
     *
     * @param array $suggestions
     * @return array
     */
    private function deduplicate(array $suggestions): array
    {
        $seen = [];

        foreach ($suggestions as $suggestion) {
            $key = strtolower($suggestion['value'] ?? '');

            if ($key === '') {
                continue;
            }

            if (!isset($seen[$key])) {
                $seen[$key] = $suggestion;
                continue;
            }

            // Keep the one with higher priority (lower number).
            $existingPriority = self::TYPE_PRIORITY[$seen[$key]['type']] ?? 99;
            $newPriority = self::TYPE_PRIORITY[$suggestion['type']] ?? 99;

            if ($newPriority < $existingPriority) {
                $seen[$key] = $suggestion;
            }
        }

        return array_values($seen);
    }
}
