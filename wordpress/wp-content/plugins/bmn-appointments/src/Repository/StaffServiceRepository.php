<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for staff-service links (bmn_staff_services junction table).
 */
class StaffServiceRepository extends Repository
{
    protected bool $timestamps = false;

    protected function getTableName(): string
    {
        return 'bmn_staff_services';
    }

    /**
     * Find all service links for a staff member.
     *
     * @return object[]
     */
    public function findByStaff(int $staffId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE staff_id = %d",
                $staffId
            )
        ) ?? [];
    }

    /**
     * Find all staff links for an appointment type.
     *
     * @return object[]
     */
    public function findByType(int $typeId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE appointment_type_id = %d",
                $typeId
            )
        ) ?? [];
    }

    /**
     * Link a staff member to an appointment type.
     *
     * @return int|false Inserted row ID, or false on failure (e.g. duplicate).
     */
    public function linkStaffToType(int $staffId, int $typeId): int|false
    {
        $result = $this->wpdb->insert($this->table, [
            'staff_id'            => $staffId,
            'appointment_type_id' => $typeId,
            'created_at'          => current_time('mysql'),
        ]);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Unlink a staff member from an appointment type.
     */
    public function unlinkStaffFromType(int $staffId, int $typeId): bool
    {
        $result = $this->wpdb->delete($this->table, [
            'staff_id'            => $staffId,
            'appointment_type_id' => $typeId,
        ]);

        return $result !== false;
    }
}
