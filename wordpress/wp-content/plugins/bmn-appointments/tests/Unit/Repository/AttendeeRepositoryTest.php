<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\AttendeeRepository;
use PHPUnit\Framework\TestCase;

final class AttendeeRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private AttendeeRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new AttendeeRepository($this->wpdb);
    }

    public function testFindByAppointmentReturnsAttendees(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'appointment_id' => 10, 'attendee_type' => 'primary', 'name' => 'John'],
            (object) ['id' => 2, 'appointment_id' => 10, 'attendee_type' => 'additional', 'name' => 'Jane'],
        ];

        $result = $this->repo->findByAppointment(10);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('appointment_id', $sql);
        $this->assertStringContainsString('ORDER BY attendee_type', $sql);
    }

    public function testFindByAppointmentReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByAppointment(999);

        $this->assertSame([], $result);
    }

    public function testFindPrimaryReturnsPrimaryAttendee(): void
    {
        $expected = (object) ['id' => 1, 'attendee_type' => 'primary', 'name' => 'John'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findPrimary(10);

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString("attendee_type = 'primary'", $sql);
    }

    public function testFindPrimaryReturnsNull(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findPrimary(999);

        $this->assertNull($result);
    }

    public function testDeleteByAppointmentDeletesRows(): void
    {
        $result = $this->repo->deleteByAppointment(10);

        $this->assertSame(1, $result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(10, $args['appointment_id']);
    }

    public function testFindUnsentReminders24h(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'reminder_24h_sent' => 0, 'email' => 'test@test.com'],
        ];

        $result = $this->repo->findUnsentReminders24h(10);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('reminder_24h_sent = 0', $sql);
    }

    public function testFindUnsentReminders1h(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findUnsentReminders1h(10);

        $this->assertSame([], $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('reminder_1h_sent = 0', $sql);
    }

    public function testMarkReminderSent24h(): void
    {
        $result = $this->repo->markReminderSent(1, '24h');

        $this->assertTrue($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['reminder_24h_sent']);
    }

    public function testMarkReminderSent1h(): void
    {
        $result = $this->repo->markReminderSent(1, '1h');

        $this->assertTrue($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['reminder_1h_sent']);
    }
}
