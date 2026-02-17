<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for appointments (bmn_appointments table).
 *
 * Uses START TRANSACTION + UNIQUE constraint for double-booking prevention.
 */
class AppointmentRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_appointments';
    }

    /**
     * Create an appointment within a transaction.
     *
     * Uses the database UNIQUE KEY on (staff_id, appointment_date, start_time)
     * to prevent double-booking race conditions.
     *
     * @param array<string, mixed> $data Appointment data.
     * @return int|false Inserted row ID, or false on failure (including duplicate slot).
     */
    public function createWithTransaction(array $data): int|false
    {
        $now = current_time('mysql');
        $data['created_at'] = $data['created_at'] ?? $now;
        $data['updated_at'] = $data['updated_at'] ?? $now;

        $this->wpdb->query('START TRANSACTION');

        $result = $this->wpdb->insert($this->table, $data);

        if ($result === false) {
            $this->wpdb->query('ROLLBACK');
            return false;
        }

        $insertId = (int) $this->wpdb->insert_id;
        $this->wpdb->query('COMMIT');

        return $insertId;
    }

    /**
     * Find appointments for a user (as client).
     *
     * @param array<string, mixed> $filters Optional: status, from_date, to_date.
     * @return object[]
     */
    public function findByUser(int $userId, array $filters = []): array
    {
        $where = ['a.user_id = %d'];
        $values = [$userId];

        $this->applyFilters($where, $values, $filters);

        $whereClause = implode(' AND ', $where);

        $sql = $this->wpdb->prepare(
            "SELECT a.*, t.name AS type_name, t.duration_minutes, t.color,
                    s.name AS staff_name, s.email AS staff_email
             FROM {$this->table} a
             LEFT JOIN {$this->wpdb->prefix}bmn_appointment_types t ON a.appointment_type_id = t.id
             LEFT JOIN {$this->wpdb->prefix}bmn_staff s ON a.staff_id = s.id
             WHERE {$whereClause}
             ORDER BY a.appointment_date DESC, a.start_time DESC",
            ...$values
        );

        return $this->wpdb->get_results($sql) ?? [];
    }

    /**
     * Find appointments for a staff member.
     *
     * @param array<string, mixed> $filters Optional: status, from_date, to_date.
     * @return object[]
     */
    public function findByStaff(int $staffId, array $filters = []): array
    {
        $where = ['a.staff_id = %d'];
        $values = [$staffId];

        $this->applyFilters($where, $values, $filters);

        $whereClause = implode(' AND ', $where);

        $sql = $this->wpdb->prepare(
            "SELECT a.*, t.name AS type_name, t.duration_minutes, t.color
             FROM {$this->table} a
             LEFT JOIN {$this->wpdb->prefix}bmn_appointment_types t ON a.appointment_type_id = t.id
             WHERE {$whereClause}
             ORDER BY a.appointment_date ASC, a.start_time ASC",
            ...$values
        );

        return $this->wpdb->get_results($sql) ?? [];
    }

    /**
     * Find booked (non-cancelled) slots for a staff member within a date range.
     *
     * @return object[] Each row has appointment_date, start_time, end_time, status.
     */
    public function findBookedSlots(int $staffId, string $startDate, string $endDate): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT appointment_date, start_time, end_time, status, appointment_type_id
                 FROM {$this->table}
                 WHERE staff_id = %d
                   AND appointment_date >= %s
                   AND appointment_date <= %s
                   AND status NOT IN ('cancelled')
                 ORDER BY appointment_date ASC, start_time ASC",
                $staffId,
                $startDate,
                $endDate
            )
        ) ?? [];
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(int $id, string $reason, string $cancelledBy): bool
    {
        return $this->update($id, [
            'status'              => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_by'        => $cancelledBy,
        ]);
    }

    /**
     * Reschedule an appointment to a new date/time.
     */
    public function reschedule(int $id, string $newDate, string $newTime, string $newEndTime): bool
    {
        $appointment = $this->find($id);

        if ($appointment === null) {
            return false;
        }

        $originalDatetime = $appointment->original_datetime
            ?? ($appointment->appointment_date . ' ' . $appointment->start_time);

        return $this->update($id, [
            'appointment_date'  => $newDate,
            'start_time'        => $newTime,
            'end_time'          => $newEndTime,
            'reschedule_count'  => (int) $appointment->reschedule_count + 1,
            'original_datetime' => $originalDatetime,
        ]);
    }

    /**
     * Find appointments that need 24-hour reminders.
     *
     * @return object[]
     */
    public function findDueReminders24h(): array
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day', (int) current_time('timestamp')));

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT a.*, t.name AS type_name, s.name AS staff_name, s.email AS staff_email
                 FROM {$this->table} a
                 LEFT JOIN {$this->wpdb->prefix}bmn_appointment_types t ON a.appointment_type_id = t.id
                 LEFT JOIN {$this->wpdb->prefix}bmn_staff s ON a.staff_id = s.id
                 WHERE a.appointment_date = %s
                   AND a.status IN ('pending', 'confirmed')
                 ORDER BY a.start_time ASC",
                $tomorrow
            )
        ) ?? [];
    }

    /**
     * Find appointments that need 1-hour reminders.
     *
     * @return object[]
     */
    public function findDueReminders1h(): array
    {
        $now = current_time('mysql');
        $oneHourLater = date('Y-m-d H:i:s', strtotime('+1 hour', (int) current_time('timestamp')));
        $today = date('Y-m-d', (int) current_time('timestamp'));
        $currentTime = date('H:i:s', (int) current_time('timestamp'));
        $oneHourTime = date('H:i:s', strtotime('+1 hour', (int) current_time('timestamp')));

        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT a.*, t.name AS type_name, s.name AS staff_name, s.email AS staff_email
                 FROM {$this->table} a
                 LEFT JOIN {$this->wpdb->prefix}bmn_appointment_types t ON a.appointment_type_id = t.id
                 LEFT JOIN {$this->wpdb->prefix}bmn_staff s ON a.staff_id = s.id
                 WHERE a.appointment_date = %s
                   AND a.start_time BETWEEN %s AND %s
                   AND a.status IN ('pending', 'confirmed')
                 ORDER BY a.start_time ASC",
                $today,
                $currentTime,
                $oneHourTime
            )
        ) ?? [];
    }

    /**
     * Apply common filters to query builder.
     *
     * @param string[] $where  WHERE clauses (modified by reference).
     * @param mixed[]  $values Prepared statement values (modified by reference).
     * @param array<string, mixed> $filters Filter criteria.
     */
    private function applyFilters(array &$where, array &$values, array $filters): void
    {
        if (!empty($filters['status'])) {
            $where[] = 'a.status = %s';
            $values[] = $filters['status'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = 'a.appointment_date >= %s';
            $values[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'a.appointment_date <= %s';
            $values[] = $filters['to_date'];
        }
    }
}
