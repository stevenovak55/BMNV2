<?php

declare(strict_types=1);

namespace BMN\Appointments\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for notification logs (bmn_notifications_log table).
 */
class NotificationLogRepository extends Repository
{
    protected bool $timestamps = false;

    protected function getTableName(): string
    {
        return 'bmn_notifications_log';
    }

    /**
     * Log a notification send attempt.
     *
     * @return int|false Inserted row ID, or false on failure.
     */
    public function logNotification(
        int $appointmentId,
        string $notificationType,
        string $recipientType,
        string $recipientEmail,
        string $subject,
        string $status,
        ?string $errorMessage = null,
    ): int|false {
        $result = $this->wpdb->insert($this->table, [
            'appointment_id'    => $appointmentId,
            'notification_type' => $notificationType,
            'recipient_type'    => $recipientType,
            'recipient_email'   => $recipientEmail,
            'subject'           => $subject,
            'status'            => $status,
            'error_message'     => $errorMessage,
            'created_at'        => current_time('mysql'),
        ]);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Find all notification logs for an appointment.
     *
     * @return object[]
     */
    public function findByAppointment(int $appointmentId): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE appointment_id = %d ORDER BY created_at DESC",
                $appointmentId
            )
        ) ?? [];
    }
}
