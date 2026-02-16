<?php

declare(strict_types=1);

namespace BMN\Users\Service;

use BMN\Users\Repository\FavoriteRepository;

/**
 * Service for managing user favorites.
 */
class FavoriteService
{
    private readonly FavoriteRepository $repository;

    public function __construct(FavoriteRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * List paginated favorites for a user.
     *
     * @return array{listing_ids: string[], total: int, page: int, per_page: int}
     */
    public function listFavorites(int $userId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        $rows = $this->repository->findByUser($userId, $perPage, $offset);
        $total = $this->repository->countByUser($userId);

        $listingIds = array_map(
            static fn (object $row): string => $row->listing_id,
            $rows
        );

        return [
            'listing_ids' => $listingIds,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
        ];
    }

    /**
     * Toggle a favorite. Returns true if added, false if removed.
     */
    public function toggleFavorite(int $userId, string $listingId): bool
    {
        $existing = $this->repository->findByUserAndListing($userId, $listingId);

        if ($existing !== null) {
            $this->repository->removeFavorite($userId, $listingId);
            return false;
        }

        $this->repository->addFavorite($userId, $listingId);
        return true;
    }

    /**
     * Add a favorite (idempotent).
     */
    public function addFavorite(int $userId, string $listingId): void
    {
        $existing = $this->repository->findByUserAndListing($userId, $listingId);

        if ($existing === null) {
            $this->repository->addFavorite($userId, $listingId);
        }
    }

    /**
     * Remove a favorite.
     */
    public function removeFavorite(int $userId, string $listingId): void
    {
        $this->repository->removeFavorite($userId, $listingId);
    }

    public function isFavorited(int $userId, string $listingId): bool
    {
        return $this->repository->findByUserAndListing($userId, $listingId) !== null;
    }

    /**
     * @return string[]
     */
    public function getFavoriteListingIds(int $userId): array
    {
        return $this->repository->getListingIdsForUser($userId);
    }

    public function removeAllForUser(int $userId): int
    {
        return $this->repository->removeAllForUser($userId);
    }
}
