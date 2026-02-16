<?php

declare(strict_types=1);

namespace BMN\Users\Service;

use BMN\Users\Repository\SavedSearchRepository;
use RuntimeException;

/**
 * Service for managing user saved searches.
 */
class SavedSearchService
{
    /** Maximum saved searches per user. */
    private const MAX_SEARCHES_PER_USER = 25;

    private readonly SavedSearchRepository $repository;

    public function __construct(SavedSearchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return object[]
     */
    public function listSearches(int $userId): array
    {
        $searches = $this->repository->findByUser($userId);

        return array_map(static function (object $search): array {
            return self::formatSearch($search);
        }, $searches);
    }

    /**
     * Create a new saved search.
     *
     * @return int The new search ID.
     *
     * @throws RuntimeException If user has reached the maximum limit.
     */
    public function createSearch(int $userId, string $name, array $filters, ?array $polygonShapes = null): int
    {
        $count = $this->repository->countByUser($userId);

        if ($count >= self::MAX_SEARCHES_PER_USER) {
            throw new RuntimeException(
                sprintf('Maximum of %d saved searches reached.', self::MAX_SEARCHES_PER_USER)
            );
        }

        $data = [
            'user_id'        => $userId,
            'name'           => $name,
            'filters'        => wp_json_encode($filters),
            'polygon_shapes' => $polygonShapes !== null ? wp_json_encode($polygonShapes) : null,
            'is_active'      => 1,
            'result_count'   => 0,
            'new_count'      => 0,
        ];

        $id = $this->repository->create($data);

        if ($id === false) {
            throw new RuntimeException('Failed to create saved search.');
        }

        return $id;
    }

    /**
     * Get a single saved search with ownership check.
     *
     * @throws RuntimeException If not found or not owned by user.
     */
    public function getSearch(int $userId, int $searchId): array
    {
        $search = $this->repository->find($searchId);

        if ($search === null || (int) $search->user_id !== $userId) {
            throw new RuntimeException('Saved search not found.');
        }

        return self::formatSearch($search);
    }

    /**
     * Update a saved search with ownership check.
     *
     * @throws RuntimeException If not found or not owned by user.
     */
    public function updateSearch(int $userId, int $searchId, array $data): bool
    {
        $search = $this->repository->find($searchId);

        if ($search === null || (int) $search->user_id !== $userId) {
            throw new RuntimeException('Saved search not found.');
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['filters'])) {
            $updateData['filters'] = wp_json_encode($data['filters']);
        }

        if (array_key_exists('polygon_shapes', $data)) {
            $updateData['polygon_shapes'] = $data['polygon_shapes'] !== null
                ? wp_json_encode($data['polygon_shapes'])
                : null;
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        if ($updateData === []) {
            return true;
        }

        return $this->repository->update($searchId, $updateData);
    }

    /**
     * Delete a saved search with ownership check.
     *
     * @throws RuntimeException If not found or not owned by user.
     */
    public function deleteSearch(int $userId, int $searchId): bool
    {
        $search = $this->repository->find($searchId);

        if ($search === null || (int) $search->user_id !== $userId) {
            throw new RuntimeException('Saved search not found.');
        }

        return $this->repository->delete($searchId);
    }

    /**
     * @return object[]
     */
    public function getSearchesForAlertProcessing(): array
    {
        return $this->repository->findActiveForAlerts();
    }

    public function markAlertProcessed(int $searchId, int $resultCount, int $newCount): void
    {
        $this->repository->updateAlertTimestamp($searchId, $resultCount, $newCount);
    }

    public function removeAllForUser(int $userId): int
    {
        return $this->repository->removeAllForUser($userId);
    }

    /**
     * Format a saved search row into an API-ready array.
     */
    private static function formatSearch(object $search): array
    {
        return [
            'id'             => (int) $search->id,
            'name'           => $search->name,
            'filters'        => json_decode($search->filters ?? '{}', true),
            'polygon_shapes' => $search->polygon_shapes !== null
                ? json_decode($search->polygon_shapes, true)
                : null,
            'is_active'      => (bool) $search->is_active,
            'last_alert_at'  => $search->last_alert_at,
            'result_count'   => (int) ($search->result_count ?? 0),
            'new_count'      => (int) ($search->new_count ?? 0),
            'created_at'     => $search->created_at,
            'updated_at'     => $search->updated_at,
        ];
    }
}
