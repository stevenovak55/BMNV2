<?php

declare(strict_types=1);

namespace BMN\Exclusive\Service;

use BMN\Exclusive\Repository\ExclusiveListingRepository;
use BMN\Exclusive\Repository\ExclusivePhotoRepository;

/**
 * Photo management service for exclusive listings.
 *
 * Handles adding, deleting, reordering, and retrieving photos with
 * ownership checks and automatic listing photo metadata updates.
 */
class PhotoService
{
    /** Maximum number of photos per listing. */
    public const MAX_PHOTOS = 100;

    public function __construct(
        private readonly ExclusiveListingRepository $listingRepo,
        private readonly ExclusivePhotoRepository $photoRepo,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Add a photo to a listing.
     *
     * Verifies ownership, enforces MAX_PHOTOS limit, assigns sort_order,
     * and sets as primary if it's the first photo.
     *
     * @param int    $listingId   The auto-increment listing ID.
     * @param int    $agentUserId The agent requesting the addition.
     * @param string $mediaUrl    The media URL for the photo.
     *
     * @return array{success: bool, photo_id?: int, errors?: array<string, string>}
     */
    public function addPhoto(int $listingId, int $agentUserId, string $mediaUrl): array
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($listingId);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return [
                'success' => false,
                'errors'  => ['listing' => 'Listing not found or access denied.'],
            ];
        }

        // Check photo count limit.
        $currentCount = $this->photoRepo->countByListing($listingId);

        if ($currentCount >= self::MAX_PHOTOS) {
            return [
                'success' => false,
                'errors'  => ['photos' => 'Maximum of ' . self::MAX_PHOTOS . ' photos per listing.'],
            ];
        }

        // Determine sort_order: max existing + 1.
        $existingPhotos = $this->photoRepo->findByListing($listingId);
        $maxSortOrder   = 0;

        foreach ($existingPhotos as $photo) {
            $sortOrder = (int) $photo->sort_order;
            if ($sortOrder > $maxSortOrder) {
                $maxSortOrder = $sortOrder;
            }
        }

        $newSortOrder = ($currentCount > 0) ? $maxSortOrder + 1 : 0;

        // First photo becomes primary.
        $isPrimary = ($currentCount === 0) ? 1 : 0;

        // Create photo record.
        $photoId = $this->photoRepo->create([
            'exclusive_listing_id' => $listingId,
            'media_url'            => $mediaUrl,
            'sort_order'           => $newSortOrder,
            'is_primary'           => $isPrimary,
        ]);

        if ($photoId === false) {
            return [
                'success' => false,
                'errors'  => ['database' => 'Failed to add photo.'],
            ];
        }

        // Update listing photo metadata.
        $this->refreshListingPhotoInfo($listingId);

        return [
            'success'  => true,
            'photo_id' => $photoId,
        ];
    }

    /**
     * Delete a photo from a listing.
     *
     * Verifies ownership and photo-listing relationship. If the deleted photo
     * was primary, reassigns primary to the photo with the lowest sort_order.
     *
     * @param int $listingId   The auto-increment listing ID.
     * @param int $agentUserId The agent requesting deletion.
     * @param int $photoId     The photo ID to delete.
     */
    public function deletePhoto(int $listingId, int $agentUserId, int $photoId): bool
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($listingId);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return false;
        }

        // Find the photo and verify it belongs to this listing.
        $photo = $this->photoRepo->find($photoId);

        if ($photo === null || (int) $photo->exclusive_listing_id !== $listingId) {
            return false;
        }

        $wasPrimary = (int) $photo->is_primary === 1;

        // Delete the photo.
        $deleted = $this->photoRepo->delete($photoId);

        if (!$deleted) {
            return false;
        }

        // If deleted photo was primary, set the next photo (lowest sort_order) as primary.
        if ($wasPrimary) {
            $remainingPhotos = $this->photoRepo->findByListing($listingId);

            if ($remainingPhotos !== []) {
                // Photos are returned ordered by sort_order ASC, so first is lowest.
                $newPrimaryId = (int) $remainingPhotos[0]->id;
                $this->photoRepo->setPrimary($listingId, $newPrimaryId);
            }
        }

        // Update listing photo metadata.
        $this->refreshListingPhotoInfo($listingId);

        return true;
    }

    /**
     * Reorder photos for a listing.
     *
     * Updates sort_order for each photo and refreshes the main_photo_url
     * to the photo with the lowest sort_order.
     *
     * @param int                                           $listingId   The auto-increment listing ID.
     * @param int                                           $agentUserId The agent requesting reorder.
     * @param array<int, array{id: int, sort_order: int}>   $photoOrders Array of photo ID => sort_order mappings.
     */
    public function reorderPhotos(int $listingId, int $agentUserId, array $photoOrders): bool
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($listingId);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return false;
        }

        // Update sort orders via repository.
        $updated = $this->photoRepo->updateSortOrders($photoOrders);

        if (!$updated) {
            return false;
        }

        // Find the photo with the lowest sort_order and set it as primary.
        $photos = $this->photoRepo->findByListing($listingId);

        if ($photos !== []) {
            // Photos are returned ordered by sort_order ASC, so first is the new primary.
            $newPrimaryId = (int) $photos[0]->id;
            $this->photoRepo->setPrimary($listingId, $newPrimaryId);
        }

        // Update listing photo metadata.
        $this->refreshListingPhotoInfo($listingId);

        return true;
    }

    /**
     * Get all photos for a listing, ordered by sort_order.
     *
     * @param int $listingId   The auto-increment listing ID.
     * @param int $agentUserId The agent requesting the photos.
     *
     * @return object[]|null Array of photo objects, or null if listing not found/denied.
     */
    public function getPhotos(int $listingId, int $agentUserId): ?array
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($listingId);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return null;
        }

        return $this->photoRepo->findByListing($listingId);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Refresh the photo_count and main_photo_url on the listing record.
     *
     * Counts current photos, finds the primary photo URL (or the first
     * photo by sort_order if no primary is set), and updates the listing.
     *
     * @param int $listingId The auto-increment listing ID.
     */
    private function refreshListingPhotoInfo(int $listingId): void
    {
        $photoCount   = $this->photoRepo->countByListing($listingId);
        $mainPhotoUrl = null;

        if ($photoCount > 0) {
            $photos = $this->photoRepo->findByListing($listingId);

            // Find the primary photo first.
            foreach ($photos as $photo) {
                if ((int) $photo->is_primary === 1) {
                    $mainPhotoUrl = $photo->media_url;
                    break;
                }
            }

            // Fallback to first photo by sort_order if no primary found.
            if ($mainPhotoUrl === null && $photos !== []) {
                $mainPhotoUrl = $photos[0]->media_url;
            }
        }

        $this->listingRepo->updatePhotoInfo($listingId, $photoCount, $mainPhotoUrl);
    }
}
