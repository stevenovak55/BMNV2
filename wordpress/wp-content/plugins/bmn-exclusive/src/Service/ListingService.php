<?php

declare(strict_types=1);

namespace BMN\Exclusive\Service;

use BMN\Exclusive\Repository\ExclusiveListingRepository;
use BMN\Exclusive\Repository\ExclusivePhotoRepository;

/**
 * Orchestration service for exclusive listing CRUD operations.
 *
 * Uses ExclusiveListingRepository, ExclusivePhotoRepository, and ValidationService
 * to coordinate listing creation, updates, deletion, and retrieval with ownership
 * checks and validation.
 */
class ListingService
{
    public function __construct(
        private readonly ExclusiveListingRepository $listingRepo,
        private readonly ExclusivePhotoRepository $photoRepo,
        private readonly ValidationService $validator,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Create a new exclusive listing.
     *
     * Sanitizes input, validates required fields, generates a unique listing_id,
     * and persists the listing with 'draft' status by default.
     *
     * @param int                  $agentUserId The agent creating the listing.
     * @param array<string, mixed> $data        Listing data.
     *
     * @return array{success: bool, listing_id?: int, listing_number?: int, errors?: array<string, string>}
     */
    public function createListing(int $agentUserId, array $data): array
    {
        // Sanitize first.
        $data = $this->validator->sanitizeListingData($data);

        // Validate for creation (all required fields checked).
        $validation = $this->validator->validateCreate($data);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors'  => $validation['errors'],
            ];
        }

        // Generate unique listing_id.
        $listingNumber = $this->listingRepo->getNextListingId();

        // Set agent and defaults.
        $data['agent_user_id'] = $agentUserId;
        $data['listing_id']    = $listingNumber;
        $data['status']        = $data['status'] ?? 'draft';

        // Persist.
        $id = $this->listingRepo->create($data);

        if ($id === false) {
            return [
                'success' => false,
                'errors'  => ['database' => 'Failed to create listing.'],
            ];
        }

        return [
            'success'        => true,
            'listing_id'     => $id,
            'listing_number' => $listingNumber,
        ];
    }

    /**
     * Update an existing exclusive listing.
     *
     * Verifies ownership, sanitizes input, validates partial update data,
     * and handles status transitions.
     *
     * @param int                  $id          The auto-increment listing ID.
     * @param int                  $agentUserId The agent requesting the update.
     * @param array<string, mixed> $data        Fields to update.
     *
     * @return array{success: bool, updated?: bool, errors?: array<string, string>}
     */
    public function updateListing(int $id, int $agentUserId, array $data): array
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($id);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return [
                'success' => false,
                'errors'  => ['listing' => 'Listing not found or access denied.'],
            ];
        }

        // Sanitize.
        $data = $this->validator->sanitizeListingData($data);

        // Validate partial update.
        $validation = $this->validator->validateUpdate($data);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors'  => $validation['errors'],
            ];
        }

        // If status change requested, validate the transition.
        if (isset($data['status']) && $data['status'] !== $listing->status) {
            if (!$this->validator->validateStatusTransition($listing->status, $data['status'])) {
                return [
                    'success' => false,
                    'errors'  => [
                        'status' => "Cannot transition from '{$listing->status}' to '{$data['status']}'.",
                    ],
                ];
            }
        }

        // Prevent changing ownership or listing_id.
        unset($data['agent_user_id'], $data['listing_id']);

        // Persist.
        $updated = $this->listingRepo->update($id, $data);

        if (!$updated) {
            return [
                'success' => false,
                'errors'  => ['database' => 'Failed to update listing.'],
            ];
        }

        return [
            'success' => true,
            'updated' => true,
        ];
    }

    /**
     * Delete an exclusive listing and its associated photos.
     *
     * @param int $id          The auto-increment listing ID.
     * @param int $agentUserId The agent requesting deletion.
     */
    public function deleteListing(int $id, int $agentUserId): bool
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($id);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return false;
        }

        // Delete photos first (cascade).
        $this->photoRepo->deleteByListing($id);

        // Delete the listing.
        return $this->listingRepo->delete($id);
    }

    /**
     * Get a single listing with its photos.
     *
     * @param int $id          The auto-increment listing ID.
     * @param int $agentUserId The agent requesting the listing.
     *
     * @return array<string, mixed>|null The listing data with photos, or null if not found/denied.
     */
    public function getListing(int $id, int $agentUserId): ?array
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($id);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return null;
        }

        // Attach photos.
        $photos = $this->photoRepo->findByListing($id);

        $result           = (array) $listing;
        $result['photos'] = $photos;

        return $result;
    }

    /**
     * Get paginated listings for an agent.
     *
     * @param int         $agentUserId The agent's user ID.
     * @param int         $page        Page number (1-based).
     * @param int         $perPage     Results per page.
     * @param string|null $status      Optional status filter.
     *
     * @return array{listings: array, total: int}
     */
    public function getAgentListings(int $agentUserId, int $page = 1, int $perPage = 20, ?string $status = null): array
    {
        $offset   = ($page - 1) * $perPage;
        $listings = $this->listingRepo->findByAgent($agentUserId, $perPage, $offset, $status);
        $total    = $this->listingRepo->countByAgent($agentUserId, $status);

        return [
            'listings' => $listings,
            'total'    => $total,
        ];
    }

    /**
     * Update the status of a listing with transition validation.
     *
     * @param int    $id          The auto-increment listing ID.
     * @param int    $agentUserId The agent requesting the status change.
     * @param string $newStatus   The desired new status.
     *
     * @return array{success: bool, errors?: array<string, string>}
     */
    public function updateStatus(int $id, int $agentUserId, string $newStatus): array
    {
        // Find listing and check ownership.
        $listing = $this->listingRepo->find($id);

        if ($listing === null || (int) $listing->agent_user_id !== $agentUserId) {
            return [
                'success' => false,
                'errors'  => ['listing' => 'Listing not found or access denied.'],
            ];
        }

        // Validate the new status value.
        if (!in_array($newStatus, ValidationService::STATUSES, true)) {
            return [
                'success' => false,
                'errors'  => ['status' => 'Invalid status: ' . $newStatus . '.'],
            ];
        }

        // Validate the transition.
        if (!$this->validator->validateStatusTransition($listing->status, $newStatus)) {
            return [
                'success' => false,
                'errors'  => [
                    'status' => "Cannot transition from '{$listing->status}' to '{$newStatus}'.",
                ],
            ];
        }

        // Persist.
        $updated = $this->listingRepo->update($id, ['status' => $newStatus]);

        if (!$updated) {
            return [
                'success' => false,
                'errors'  => ['database' => 'Failed to update status.'],
            ];
        }

        return [
            'success' => true,
        ];
    }
}
