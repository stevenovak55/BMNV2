<?php

declare(strict_types=1);

namespace BMN\Agents\Service;

use BMN\Agents\Repository\RelationshipRepository;
use RuntimeException;

/**
 * Manages agent-client assignment relationships.
 */
class RelationshipService
{
    private readonly RelationshipRepository $relationshipRepo;

    public function __construct(RelationshipRepository $relationshipRepo)
    {
        $this->relationshipRepo = $relationshipRepo;
    }

    /**
     * Assign an agent to a client (create or reactivate).
     *
     * @throws RuntimeException If client already has an active agent.
     */
    public function assignAgent(int $agentUserId, int $clientUserId, string $source = 'manual', ?string $notes = null): int
    {
        // Check if client already has an active agent.
        $existing = $this->relationshipRepo->findActiveForClient($clientUserId);

        if ($existing !== null && (int) $existing->agent_user_id !== $agentUserId) {
            throw new RuntimeException('Client already has an active agent.');
        }

        // Check if this exact relationship exists.
        $relationship = $this->relationshipRepo->findByAgentAndClient($agentUserId, $clientUserId);

        if ($relationship !== null) {
            // Reactivate if inactive.
            $this->relationshipRepo->update((int) $relationship->id, [
                'status' => 'active',
                'source' => $source,
                'notes'  => $notes,
            ]);
            return (int) $relationship->id;
        }

        $result = $this->relationshipRepo->create([
            'agent_user_id'  => $agentUserId,
            'client_user_id' => $clientUserId,
            'status'         => 'active',
            'source'         => $source,
            'notes'          => $notes,
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to assign agent.');
        }

        return $result;
    }

    /**
     * Unassign (deactivate) a client from an agent.
     */
    public function unassignAgent(int $agentUserId, int $clientUserId): bool
    {
        $relationship = $this->relationshipRepo->findByAgentAndClient($agentUserId, $clientUserId);

        if ($relationship === null) {
            throw new RuntimeException('Relationship not found.');
        }

        return $this->relationshipRepo->update((int) $relationship->id, [
            'status' => 'inactive',
        ]);
    }

    /**
     * Get the active agent for a client.
     */
    public function getClientAgent(int $clientUserId): ?object
    {
        return $this->relationshipRepo->findActiveForClient($clientUserId);
    }

    /**
     * Get paginated list of an agent's clients.
     *
     * @return array{items: object[], total: int}
     */
    public function getAgentClients(int $agentUserId, ?string $status = null, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $items = $this->relationshipRepo->findClientsByAgent($agentUserId, $status, $perPage, $offset);
        $total = $this->relationshipRepo->countClientsByAgent($agentUserId, $status);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Create a new WP user as a client and auto-assign to the agent.
     *
     * @return array{user_id: int, relationship_id: int}
     * @throws RuntimeException On failure.
     */
    public function createClient(array $data, int $agentUserId): array
    {
        $email = sanitize_email($data['email'] ?? '');
        $firstName = sanitize_text_field($data['first_name'] ?? '');
        $lastName = sanitize_text_field($data['last_name'] ?? '');

        if (empty($email)) {
            throw new RuntimeException('Email is required.');
        }

        $password = wp_generate_password(12, true);

        $userId = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'display_name' => trim("{$firstName} {$lastName}") ?: $email,
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Failed to create user: ' . $userId->get_error_message());
        }

        $relationshipId = $this->assignAgent($agentUserId, $userId, 'manual', $data['notes'] ?? null);

        return [
            'user_id'         => $userId,
            'relationship_id' => $relationshipId,
        ];
    }

    /**
     * Check if a user is the assigned agent for a client.
     */
    public function isAgentForClient(int $agentUserId, int $clientUserId): bool
    {
        $relationship = $this->relationshipRepo->findActiveForClient($clientUserId);

        return $relationship !== null && (int) $relationship->agent_user_id === $agentUserId;
    }

    /**
     * Update the status of a relationship.
     */
    public function updateStatus(int $agentUserId, int $clientUserId, string $status): bool
    {
        $relationship = $this->relationshipRepo->findByAgentAndClient($agentUserId, $clientUserId);

        if ($relationship === null) {
            throw new RuntimeException('Relationship not found.');
        }

        return $this->relationshipRepo->update((int) $relationship->id, [
            'status' => $status,
        ]);
    }
}
