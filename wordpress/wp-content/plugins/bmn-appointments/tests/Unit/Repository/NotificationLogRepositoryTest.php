<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\NotificationLogRepository;
use PHPUnit\Framework\TestCase;

final class NotificationLogRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private NotificationLogRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new NotificationLogRepository($this->wpdb);
    }

    public function testTimestampsAreDisabled(): void
    {
        $this->wpdb->insert_result = true;

        $this->repo->logNotification(1, 'confirmation', 'client', 'test@test.com', 'Subject', 'sent');

        $args = $this->wpdb->queries[0]['args'];
        $this->assertArrayHasKey('created_at', $args);
        // Should not have updated_at since timestamps are disabled.
    }

    public function testLogNotificationInsertsRow(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->logNotification(
            10,
            'confirmation',
            'client',
            'test@example.com',
            'Appointment Confirmed',
            'sent',
        );

        $this->assertIsInt($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(10, $args['appointment_id']);
        $this->assertSame('confirmation', $args['notification_type']);
        $this->assertSame('client', $args['recipient_type']);
        $this->assertSame('test@example.com', $args['recipient_email']);
        $this->assertSame('sent', $args['status']);
        $this->assertNull($args['error_message']);
    }

    public function testLogNotificationWithError(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->logNotification(
            10,
            'confirmation',
            'client',
            'test@example.com',
            'Appointment Confirmed',
            'failed',
            'SMTP connection refused',
        );

        $this->assertIsInt($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame('failed', $args['status']);
        $this->assertSame('SMTP connection refused', $args['error_message']);
    }

    public function testLogNotificationReturnsFalseOnFailure(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->logNotification(10, 'confirmation', 'client', 'test@test.com', 'Subject', 'sent');

        $this->assertFalse($result);
    }

    public function testFindByAppointmentReturnsLogs(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'appointment_id' => 10, 'notification_type' => 'confirmation'],
            (object) ['id' => 2, 'appointment_id' => 10, 'notification_type' => 'reminder_24h'],
        ];

        $result = $this->repo->findByAppointment(10);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('appointment_id', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testFindByAppointmentReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByAppointment(999);

        $this->assertSame([], $result);
    }
}
