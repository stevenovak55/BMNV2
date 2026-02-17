<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for appointment types (bmn_appointment_types table).
 */
class AppointmentTypeRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_appointment_types';
    }

    /**
     * Find all active appointment types.
     *
     * @return object[]
     */
    public function findActive(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        ) ?? [];
    }

    /**
     * Find an appointment type by its slug.
     */
    public function findBySlug(string $slug): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE slug = %s LIMIT 1",
                $slug
            )
        );

        return $result ?: null;
    }

    /**
     * Find active appointment types offered by a specific staff member.
     *
     * @return object[]
     */
    public function findByStaff(int $staffId): array
    {
        $staffServicesTable = $this->wpdb->prefix . 'bmn_staff_services';

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT t.* FROM {$this->table} t
                INNER JOIN {$staffServicesTable} ss ON t.id = ss.appointment_type_id
                WHERE ss.staff_id = %d AND t.is_active = 1
                ORDER BY t.sort_order ASC, t.name ASC",
                $staffId
            )
        ) ?? [];
    }
}
