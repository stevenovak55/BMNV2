<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\AppointmentRepository;
use PHPUnit\Framework\TestCase;

final class AppointmentRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private AppointmentRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new AppointmentRepository($this->wpdb);
    }

    public function testCreateWithTransactionInsertsAndCommits(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->createWithTransaction([
            'staff_id'            => 1,
            'appointment_type_id' => 1,
            'appointment_date'    => '2026-03-01',
            'start_time'          => '09:00:00',
            'end_time'            => '09:30:00',
            'client_name'         => 'Test',
            'client_email'        => 'test@example.com',
            'status'              => 'confirmed',
        ]);

        $this->assertIsInt($result);

        // Check for START TRANSACTION and COMMIT.
        $sqlLog = array_column($this->wpdb->queries, 'sql');
        $this->assertContains('START TRANSACTION', $sqlLog);
        $this->assertContains('COMMIT', $sqlLog);
        $this->assertNotContains('ROLLBACK', $sqlLog);
    }

    public function testCreateWithTransactionRollsBackOnFailure(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->createWithTransaction([
            'staff_id'            => 1,
            'appointment_type_id' => 1,
            'appointment_date'    => '2026-03-01',
            'start_time'          => '09:00:00',
            'end_time'            => '09:30:00',
            'client_name'         => 'Test',
            'client_email'        => 'test@example.com',
            'status'              => 'confirmed',
        ]);

        $this->assertFalse($result);

        $sqlLog = array_column($this->wpdb->queries, 'sql');
        $this->assertContains('START TRANSACTION', $sqlLog);
        $this->assertContains('ROLLBACK', $sqlLog);
        $this->assertNotContains('COMMIT', $sqlLog);
    }

    public function testFindByUserExecutesCorrectQuery(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByUser(42);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('a.user_id', $sql);
        $this->assertStringContainsString('LEFT JOIN', $sql);
        $this->assertStringContainsString('bmn_appointment_types', $sql);
        $this->assertStringContainsString('bmn_staff', $sql);
        $this->assertSame([], $result);
    }

    public function testFindByUserWithStatusFilter(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findByUser(42, ['status' => 'confirmed']);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('a.status', $sql);
    }

    public function testFindByStaffExecutesCorrectQuery(): void
    {
        $this->wpdb->get_results_result = [];

        $this->repo->findByStaff(1);

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('a.staff_id', $sql);
        $this->assertStringContainsString('ORDER BY a.appointment_date ASC', $sql);
    }

    public function testFindBookedSlotsExcludesCancelled(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['appointment_date' => '2026-03-01', 'start_time' => '09:00:00', 'end_time' => '09:30:00', 'status' => 'confirmed'],
        ];

        $result = $this->repo->findBookedSlots(1, '2026-03-01', '2026-03-31');

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString("NOT IN ('cancelled')", $sql);
    }

    public function testCancelUpdatesStatus(): void
    {
        $this->repo->cancel(1, 'No longer needed', 'client');

        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame('cancelled', $args['status']);
        $this->assertSame('No longer needed', $args['cancellation_reason']);
        $this->assertSame('client', $args['cancelled_by']);
    }

    public function testRescheduleUpdatesDateTimeAndCount(): void
    {
        // Set up find to return existing appointment.
        $this->wpdb->get_row_result = (object) [
            'id' => 1,
            'appointment_date' => '2026-03-01',
            'start_time' => '09:00:00',
            'reschedule_count' => 1,
            'original_datetime' => null,
        ];

        $result = $this->repo->reschedule(1, '2026-03-02', '10:00:00', '10:30:00');

        $this->assertTrue($result);

        // The update query should contain the new date/time.
        $updateQuery = end($this->wpdb->queries);
        $this->assertSame('UPDATE', substr($updateQuery['sql'], 0, 6));
        $this->assertSame('2026-03-02', $updateQuery['args']['appointment_date']);
        $this->assertSame('10:00:00', $updateQuery['args']['start_time']);
        $this->assertSame(2, $updateQuery['args']['reschedule_count']);
    }

    public function testRescheduleReturnsFalseForMissingAppointment(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->reschedule(999, '2026-03-02', '10:00:00', '10:30:00');

        $this->assertFalse($result);
    }
}
