<?php

declare(strict_types=1);

namespace BMN\Users\Service;

use RuntimeException;

/**
 * Service for user profile operations.
 */
class UserProfileService
{
    /**
     * Get a user's profile.
     */
    public function getProfile(int $userId): ?array
    {
        $user = get_userdata($userId);

        if ($user === false) {
            return null;
        }

        return UserProfileFormatter::format($user);
    }

    /**
     * Update a user's profile.
     *
     * @param array $data Supported keys: first_name, last_name, phone, email.
     *
     * @throws RuntimeException If the update fails.
     */
    public function updateProfile(int $userId, array $data): array
    {
        $user = get_userdata($userId);

        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        $wpData = ['ID' => $userId];

        if (isset($data['first_name'])) {
            $wpData['first_name'] = sanitize_text_field($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $wpData['last_name'] = sanitize_text_field($data['last_name']);
        }

        if (isset($data['email'])) {
            $wpData['user_email'] = sanitize_email($data['email']);
        }

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $wpData['first_name'] ?? $user->first_name ?? '';
            $last = $wpData['last_name'] ?? $user->last_name ?? '';
            $wpData['display_name'] = trim("{$first} {$last}");
        }

        $result = wp_update_user($wpData);

        if (is_wp_error($result)) {
            throw new RuntimeException('Failed to update user profile.');
        }

        if (isset($data['phone'])) {
            update_user_meta($userId, 'phone', sanitize_text_field($data['phone']));
        }

        // Re-fetch updated user.
        $updated = get_userdata($userId);

        return UserProfileFormatter::format($updated);
    }

    /**
     * Change a user's password.
     *
     * @throws RuntimeException If the current password is incorrect.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = get_userdata($userId);

        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        if (! wp_check_password($currentPassword, $user->user_pass, $userId)) {
            throw new RuntimeException('Current password is incorrect.');
        }

        wp_set_password($newPassword, $userId);

        return true;
    }
}
