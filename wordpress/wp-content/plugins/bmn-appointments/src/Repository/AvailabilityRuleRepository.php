<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for availability rules (bmn_availability_rules table).
 */
class AvailabilityRuleRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_availability_rules';
    }

    /**
     * Find all rules for a staff member.
     *
     * @return object[]
     */
    public function findByStaff(int $staffId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE staff_id = %d ORDER BY rule_type ASC, day_of_week ASC, start_time ASC",
                $staffId
            )
        ) ?? [];
    }

    /**
     * Find rules for a staff member filtered by appointment type.
     *
     * Returns rules that are either global (no type restriction) or specific to the type.
     *
     * @return object[]
     */
    public function findByStaffAndType(int $staffId, ?int $typeId = null): array
    {
        if ($typeId === null) {
            return $this->findByStaff($staffId);
        }

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE staff_id = %d AND (appointment_type_id IS NULL OR appointment_type_id = %d)
                ORDER BY rule_type ASC, day_of_week ASC, start_time ASC",
                $staffId,
                $typeId
            )
        ) ?? [];
    }

    /**
     * Find blocked dates for a staff member within a date range.
     *
     * @return object[]
     */
    public function findBlockedDates(int $staffId, string $startDate, string $endDate): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                WHERE staff_id = %d AND rule_type = 'blocked'
                AND (specific_date IS NULL OR (specific_date >= %s AND specific_date <= %s))
                ORDER BY specific_date ASC",
                $staffId,
                $startDate,
                $endDate
            )
        ) ?? [];
    }
}
