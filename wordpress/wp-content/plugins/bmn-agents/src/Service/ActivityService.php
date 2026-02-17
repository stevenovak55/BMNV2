<?php

declare(strict_types=1);

namespace BMN\Agents\Service;

use BMN\Agents\Repository\ActivityLogRepository;
use BMN\Agents\Repository\RelationshipRepository;

/**
 * Activity tracking and agent dashboard metrics.
 */
class ActivityService
{
    private readonly ActivityLogRepository $activityRepo;
    private readonly RelationshipRepository $relationshipRepo;

    public function __construct(
        ActivityLogRepository $activityRepo,
        RelationshipRepository $relationshipRepo,
    ) {
        $this->activityRepo = $activityRepo;
        $this->relationshipRepo = $relationshipRepo;
    }

    /**
     * Log a client activity. Auto-resolves the assigned agent.
     *
     * @return int|false Activity log ID.
     */
    public function logActivity(
        int $clientUserId,
        string $activityType,
        ?string $entityId = null,
        ?string $entityType = null,
        ?array $metadata = null,
    ): int|false {
        // Auto-resolve the agent for this client.
        $relationship = $this->relationshipRepo->findActiveForClient($clientUserId);

        if ($relationship === null) {
            // No assigned agent — skip logging.
            return false;
        }

        return $this->activityRepo->create([
            'agent_user_id'  => (int) $relationship->agent_user_id,
            'client_user_id' => $clientUserId,
            'activity_type'  => $activityType,
            'entity_id'      => $entityId,
            'entity_type'    => $entityType,
            'metadata'       => $metadata,
        ]);
    }

    /**
     * Get agent's activity feed (all clients).
     *
     * @return array[]
     */
    public function getAgentActivityFeed(int $agentUserId, int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $activities = $this->activityRepo->findByAgent($agentUserId, $perPage, $offset);

        return array_map(static function (object $a): array {
            return [
                'id'              => (int) $a->id,
                'client_user_id'  => (int) $a->client_user_id,
                'activity_type'   => $a->activity_type,
                'entity_id'       => $a->entity_id ?? null,
                'entity_type'     => $a->entity_type ?? null,
                'metadata'        => !empty($a->metadata) ? json_decode($a->metadata, true) : null,
                'created_at'      => $a->created_at,
            ];
        }, $activities);
    }

    /**
     * Get activity for a specific client.
     *
     * @return array[]
     */
    public function getClientActivity(int $agentUserId, int $clientUserId, int $limit = 50): array
    {
        $activities = $this->activityRepo->findByAgentAndClient($agentUserId, $clientUserId, $limit);

        return array_map(static function (object $a): array {
            return [
                'id'            => (int) $a->id,
                'activity_type' => $a->activity_type,
                'entity_id'     => $a->entity_id ?? null,
                'entity_type'   => $a->entity_type ?? null,
                'metadata'      => !empty($a->metadata) ? json_decode($a->metadata, true) : null,
                'created_at'    => $a->created_at,
            ];
        }, $activities);
    }

    /**
     * Get agent dashboard metrics.
     */
    public function getAgentMetrics(int $agentUserId, int $days = 30): array
    {
        $totalClients = $this->relationshipRepo->countClientsByAgent($agentUserId, 'active');
        $activeClients = $this->activityRepo->countActiveClients($agentUserId, $days);
        $recentActivities = $this->activityRepo->countRecent($agentUserId, $days);
        $activityByType = $this->activityRepo->countByType($agentUserId, $days);

        return [
            'total_clients'     => $totalClients,
            'active_clients'    => $activeClients,
            'recent_activities' => $recentActivities,
            'period_days'       => $days,
            'activity_by_type'  => $activityByType,
        ];
    }
}
