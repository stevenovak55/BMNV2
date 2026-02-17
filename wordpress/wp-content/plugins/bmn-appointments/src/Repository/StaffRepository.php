<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for staff members (bmn_staff table).
 */
class StaffRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_staff';
    }

    /**
     * Find the primary staff member.
     */
    public function findPrimary(): ?object
    {
        $result = $this->wpdb->get_row(
            "SELECT * FROM {$this->table} WHERE is_primary = 1 AND is_active = 1 LIMIT 1"
        );

        return $result ?: null;
    }

    /**
     * Find all active staff members.
     *
     * @return object[]
     */
    public function findActive(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY is_primary DESC, name ASC"
        ) ?? [];
    }

    /**
     * Find a staff member by WordPress user ID.
     */
    public function findByUserId(int $userId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d LIMIT 1",
                $userId
            )
        );

        return $result ?: null;
    }

    /**
     * Find active staff members who offer a specific appointment type.
     *
     * @return object[]
     */
    public function findByAppointmentType(int $typeId): array
    {
        $staffServicesTable = $this->wpdb->prefix . 'bmn_staff_services';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT s.* FROM {$this->table} s
                INNER JOIN {$staffServicesTable} ss ON s.id = ss.staff_id
                WHERE ss.appointment_type_id = %d AND s.is_active = 1
                ORDER BY s.is_primary DESC, s.name ASC",
                $typeId
            )
        ) ?? [];
    }

    /**
     * Update Google OAuth tokens for a staff member.
     */
    public function updateGoogleTokens(int $staffId, string $accessToken, string $refreshToken, string $expiresAt): bool
    {
        return $this->update($staffId, [
            'google_access_token'  => $accessToken,
            'google_refresh_token' => $refreshToken,
            'google_token_expires' => $expiresAt,
        ]);
    }
}
