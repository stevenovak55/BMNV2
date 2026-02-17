<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for appointment attendees (bmn_appointment_attendees table).
 */
class AttendeeRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_appointment_attendees';
    }

    /**
     * Find all attendees for an appointment.
     *
     * @return object[]
     */
    public function findByAppointment(int $appointmentId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE appointment_id = %d ORDER BY attendee_type ASC, name ASC",
                $appointmentId
            )
        ) ?? [];
    }

    /**
     * Find the primary attendee for an appointment.
     */
    public function findPrimary(int $appointmentId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE appointment_id = %d AND attendee_type = 'primary' LIMIT 1",
                $appointmentId
            )
        );

        return $result ?: null;
    }

    /**
     * Delete all attendees for an appointment.
     */
    public function deleteByAppointment(int $appointmentId): int
    {
        $result = $this->wpdb->delete($this->table, ['appointment_id' => $appointmentId]);

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Find attendees that haven't received a 24h reminder.
     *
     * @return object[]
     */
    public function findUnsentReminders24h(int $appointmentId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE appointment_id = %d AND reminder_24h_sent = 0",
                $appointmentId
            )
        ) ?? [];
    }

    /**
     * Find attendees that haven't received a 1h reminder.
     *
     * @return object[]
     */
    public function findUnsentReminders1h(int $appointmentId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE appointment_id = %d AND reminder_1h_sent = 0",
                $appointmentId
            )
        ) ?? [];
    }

    /**
     * Mark a reminder as sent for an attendee.
     */
    public function markReminderSent(int $attendeeId, string $reminderType): bool
    {
        $column = $reminderType === '24h' ? 'reminder_24h_sent' : 'reminder_1h_sent';

        return $this->update($attendeeId, [$column => 1]);
    }
}
