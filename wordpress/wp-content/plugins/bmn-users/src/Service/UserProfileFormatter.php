<?php

declare(strict_types=1);

namespace BMN\Users\Service;

/**
 * Static helper to build v1-compatible user profile arrays.
 */
final class UserProfileFormatter
{
    /**
     * Format a WordPress user object into a v1-compatible profile array.
     *
     * @param object $user WordPress user object (WP_User or stdClass with ID, user_email, etc.).
     *
     * @return array{id: int, email: string, name: string, first_name: string, last_name: string, phone: string, avatar_url: string, user_type: string, assigned_agent: int|null, mls_agent_id: string}
     */
    public static function format(object $user): array
    {
        $userId = (int) $user->ID;
        $firstName = $user->first_name ?? '';
        $lastName = $user->last_name ?? '';
        $displayName = $user->display_name ?? trim("{$firstName} {$lastName}");

        return [
            'id'             => $userId,
            'email'          => $user->user_email ?? '',
            'name'           => $displayName,
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'phone'          => (string) get_user_meta($userId, 'phone', true),
            'avatar_url'     => (string) get_avatar_url($userId),
            'user_type'      => self::resolveUserType($user),
            'assigned_agent' => self::resolveAssignedAgent($userId),
            'mls_agent_id'   => (string) get_user_meta($userId, 'mls_agent_id', true),
        ];
    }

    /**
     * Map WordPress roles to user_type.
     */
    private static function resolveUserType(object $user): string
    {
        $roles = (array) ($user->roles ?? []);

        if (in_array('administrator', $roles, true)) {
            return 'admin';
        }

        if (in_array('bmn_agent', $roles, true)) {
            return 'agent';
        }

        return 'client';
    }

    /**
     * Get the assigned agent ID from user meta.
     */
    private static function resolveAssignedAgent(int $userId): ?int
    {
        $agentId = get_user_meta($userId, 'bmn_assigned_agent_id', true);

        return $agentId !== '' && $agentId !== false ? (int) $agentId : null;
    }
}
