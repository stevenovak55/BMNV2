<?php

declare(strict_types=1);

namespace BMN\Agents\Service;

use BMN\Agents\Repository\SharedPropertyRepository;
use RuntimeException;

/**
 * Property sharing between agents and clients.
 */
class SharedPropertyService
{
    private readonly SharedPropertyRepository $sharedRepo;

    public function __construct(SharedPropertyRepository $sharedRepo)
    {
        $this->sharedRepo = $sharedRepo;
    }

    /**
     * Share listing(s) with client(s). Upserts to avoid duplicates.
     *
     * @param int[] $clientUserIds
     * @param string[] $listingIds  MLS numbers (not listing_key per project rule #4).
     * @return int Number of shares created/updated.
     */
    public function shareProperties(int $agentUserId, array $clientUserIds, array $listingIds, ?string $note = null): int
    {
        $count = 0;

        foreach ($clientUserIds as $clientUserId) {
            foreach ($listingIds as $listingId) {
                $existing = $this->sharedRepo->findByAgentClientListing($agentUserId, $clientUserId, $listingId);

                if ($existing !== null) {
                    // Update the note and un-dismiss if re-shared.
                    $this->sharedRepo->update((int) $existing->id, [
                        'agent_note'   => $note,
                        'is_dismissed' => 0,
                    ]);
                } else {
                    $this->sharedRepo->create([
                        'agent_user_id'  => $agentUserId,
                        'client_user_id' => $clientUserId,
                        'listing_id'     => $listingId,
                        'agent_note'     => $note,
                    ]);
                }

                $count++;
            }
        }

        return $count;
    }

    /**
     * Get properties shared with a client.
     *
     * @return array{items: object[], total: int}
     */
    public function getSharedForClient(int $clientUserId, bool $includeDismissed = false, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $items = $this->sharedRepo->findForClient($clientUserId, $includeDismissed, $perPage, $offset);
        $total = $this->sharedRepo->countForClient($clientUserId, $includeDismissed);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Get properties shared by an agent.
     *
     * @return object[]
     */
    public function getSharedByAgent(int $agentUserId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->sharedRepo->findByAgent($agentUserId, $perPage, $offset);
    }

    /**
     * Client responds to a shared property.
     *
     * @throws RuntimeException If share not found or unauthorized.
     */
    public function respondToShare(int $shareId, int $clientUserId, string $response, ?string $note = null): bool
    {
        $share = $this->sharedRepo->find($shareId);

        if ($share === null) {
            throw new RuntimeException('Shared property not found.');
        }

        if ((int) $share->client_user_id !== $clientUserId) {
            throw new RuntimeException('Not authorized to respond to this share.');
        }

        if (!in_array($response, ['interested', 'not_interested'], true)) {
            throw new RuntimeException('Invalid response. Must be interested or not_interested.');
        }

        return $this->sharedRepo->update($shareId, [
            'client_response' => $response,
            'client_note'     => $note,
        ]);
    }

    /**
     * Client dismisses a shared property.
     *
     * @throws RuntimeException If share not found or unauthorized.
     */
    public function dismissShare(int $shareId, int $clientUserId): bool
    {
        $share = $this->sharedRepo->find($shareId);

        if ($share === null) {
            throw new RuntimeException('Shared property not found.');
        }

        if ((int) $share->client_user_id !== $clientUserId) {
            throw new RuntimeException('Not authorized to dismiss this share.');
        }

        return $this->sharedRepo->update($shareId, [
            'is_dismissed' => 1,
        ]);
    }

    /**
     * Record a view of a shared property.
     */
    public function recordView(int $shareId, int $clientUserId): bool
    {
        $share = $this->sharedRepo->find($shareId);

        if ($share === null || (int) $share->client_user_id !== $clientUserId) {
            return false;
        }

        return $this->sharedRepo->recordView($shareId);
    }
}
