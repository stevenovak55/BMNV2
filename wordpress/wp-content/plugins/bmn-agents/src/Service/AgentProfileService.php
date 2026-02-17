<?php

declare(strict_types=1);

namespace BMN\Agents\Service;

use BMN\Agents\Repository\AgentProfileRepository;
use BMN\Agents\Repository\AgentReadRepository;
use BMN\Agents\Repository\OfficeReadRepository;
use RuntimeException;

/**
 * Merges MLS agent data with extended profile data and office info.
 */
class AgentProfileService
{
    private readonly AgentReadRepository $agentReadRepo;
    private readonly OfficeReadRepository $officeReadRepo;
    private readonly AgentProfileRepository $profileRepo;

    public function __construct(
        AgentReadRepository $agentReadRepo,
        OfficeReadRepository $officeReadRepo,
        AgentProfileRepository $profileRepo,
    ) {
        $this->agentReadRepo = $agentReadRepo;
        $this->officeReadRepo = $officeReadRepo;
        $this->profileRepo = $profileRepo;
    }

    /**
     * Get a single agent with merged MLS + profile + office data.
     */
    public function getAgent(string $agentMlsId): ?array
    {
        $agent = $this->agentReadRepo->findByMlsId($agentMlsId);

        if ($agent === null) {
            return null;
        }

        return $this->mergeAgentData($agent);
    }

    /**
     * List agents with pagination.
     *
     * @return array{items: array[], total: int}
     */
    public function listAgents(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $agents = $this->agentReadRepo->searchByName($search, $perPage);
            $total = count($agents);
        } else {
            $agents = $this->agentReadRepo->findAll($perPage, $offset);
            $total = $this->agentReadRepo->count();
        }

        $items = array_map(fn (object $agent): array => $this->mergeAgentData($agent), $agents);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Get featured agents for homepage display.
     *
     * @return array[]
     */
    public function getFeaturedAgents(): array
    {
        $profiles = $this->profileRepo->findFeatured();

        return array_map(function (object $profile): array {
            $agent = $this->agentReadRepo->findByMlsId($profile->agent_mls_id);

            if ($agent === null) {
                return $this->formatProfileOnly($profile);
            }

            return $this->mergeAgentData($agent, $profile);
        }, $profiles);
    }

    /**
     * Search agents by name.
     *
     * @return array[]
     */
    public function searchAgents(string $term): array
    {
        $agents = $this->agentReadRepo->searchByName($term);

        return array_map(fn (object $agent): array => $this->mergeAgentData($agent), $agents);
    }

    /**
     * Save (upsert) an extended profile.
     *
     * @return int Profile ID.
     * @throws RuntimeException If agent not found.
     */
    public function saveProfile(string $agentMlsId, array $data): int
    {
        $agent = $this->agentReadRepo->findByMlsId($agentMlsId);

        if ($agent === null) {
            throw new RuntimeException('Agent not found.');
        }

        $profileData = array_intersect_key($data, array_flip([
            'bio', 'photo_url', 'specialties', 'is_featured',
            'is_active', 'snab_staff_id', 'display_order', 'user_id',
        ]));

        if (isset($profileData['specialties']) && is_array($profileData['specialties'])) {
            $profileData['specialties'] = wp_json_encode($profileData['specialties']);
        }

        $result = $this->profileRepo->upsert($agentMlsId, $profileData);

        if ($result === false) {
            throw new RuntimeException('Failed to save profile.');
        }

        return $result;
    }

    /**
     * Link an MLS agent to a WordPress user account.
     *
     * @throws RuntimeException If agent not found.
     */
    public function linkToUser(string $agentMlsId, int $userId): int
    {
        return $this->saveProfile($agentMlsId, ['user_id' => $userId]);
    }

    /**
     * Merge bmn_agents row + bmn_agent_profiles row + office data.
     */
    private function mergeAgentData(object $agent, ?object $profile = null): array
    {
        if ($profile === null) {
            $profile = $this->profileRepo->findByMlsId($agent->agent_mls_id);
        }

        $office = null;
        if (!empty($agent->office_mls_id)) {
            $office = $this->officeReadRepo->findByMlsId($agent->office_mls_id);
        }

        return [
            'agent_mls_id'  => $agent->agent_mls_id,
            'full_name'     => $agent->full_name ?? null,
            'first_name'    => $agent->first_name ?? null,
            'last_name'     => $agent->last_name ?? null,
            'email'         => $agent->email ?? null,
            'phone'         => $agent->phone ?? null,
            'designation'   => $agent->designation ?? null,
            // Extended profile fields.
            'user_id'       => $profile !== null ? ($profile->user_id ?? null) : null,
            'bio'           => $profile->bio ?? null,
            'photo_url'     => $profile->photo_url ?? null,
            'specialties'   => $profile !== null && !empty($profile->specialties)
                ? json_decode($profile->specialties, true) : [],
            'is_featured'   => $profile !== null ? (bool) ($profile->is_featured ?? false) : false,
            'is_active'     => $profile !== null ? (bool) ($profile->is_active ?? true) : true,
            'snab_staff_id' => $profile->snab_staff_id ?? null,
            'display_order' => $profile !== null ? (int) ($profile->display_order ?? 0) : 0,
            // Office data.
            'office'        => $office !== null ? [
                'office_mls_id' => $office->office_mls_id,
                'office_name'   => $office->office_name ?? null,
                'phone'         => $office->phone ?? null,
                'address'       => $office->address ?? null,
                'city'          => $office->city ?? null,
                'state'         => $office->state_or_province ?? null,
                'postal_code'   => $office->postal_code ?? null,
            ] : null,
        ];
    }

    /**
     * Format a profile-only result (when MLS agent not found).
     */
    private function formatProfileOnly(object $profile): array
    {
        return [
            'agent_mls_id'  => $profile->agent_mls_id,
            'full_name'     => null,
            'first_name'    => null,
            'last_name'     => null,
            'email'         => null,
            'phone'         => null,
            'designation'   => null,
            'user_id'       => $profile->user_id ?? null,
            'bio'           => $profile->bio ?? null,
            'photo_url'     => $profile->photo_url ?? null,
            'specialties'   => !empty($profile->specialties) ? json_decode($profile->specialties, true) : [],
            'is_featured'   => (bool) ($profile->is_featured ?? false),
            'is_active'     => (bool) ($profile->is_active ?? true),
            'snab_staff_id' => $profile->snab_staff_id ?? null,
            'display_order' => (int) ($profile->display_order ?? 0),
            'office'        => null,
        ];
    }
}
