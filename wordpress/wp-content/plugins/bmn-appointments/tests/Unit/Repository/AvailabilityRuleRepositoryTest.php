<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\AvailabilityRuleRepository;
use PHPUnit\Framework\TestCase;

final class AvailabilityRuleRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private AvailabilityRuleRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new AvailabilityRuleRepository($this->wpdb);
    }

    public function testFindByStaffReturnsRules(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'staff_id' => 1, 'rule_type' => 'recurring', 'day_of_week' => 1],
            (object) ['id' => 2, 'staff_id' => 1, 'rule_type' => 'recurring', 'day_of_week' => 2],
        ];

        $result = $this->repo->findByStaff(1);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('staff_id', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
    }

    public function testFindByStaffReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByStaff(999);

        $this->assertSame([], $result);
    }

    public function testFindByStaffAndTypeWithNullTypeId(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByStaffAndType(1, null);

        $this->assertSame([], $result);
        // Should call findByStaff internally.
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('staff_id', $sql);
    }

    public function testFindByStaffAndTypeWithTypeId(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'staff_id' => 1, 'appointment_type_id' => 5],
        ];

        $result = $this->repo->findByStaffAndType(1, 5);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('appointment_type_id IS NULL OR appointment_type_id', $sql);
    }

    public function testFindBlockedDatesReturnsBlockedRules(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 3, 'rule_type' => 'blocked', 'specific_date' => '2026-03-01'],
        ];

        $result = $this->repo->findBlockedDates(1, '2026-03-01', '2026-03-31');

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString("rule_type = 'blocked'", $sql);
        $this->assertStringContainsString('specific_date', $sql);
    }

    public function testFindBlockedDatesReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findBlockedDates(1, '2026-03-01', '2026-03-31');

        $this->assertSame([], $result);
    }

    public function testCreateDelegatesToBaseCreate(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->create([
            'staff_id'   => 1,
            'rule_type'  => 'recurring',
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time'   => '17:00:00',
        ]);

        $this->assertIsInt($result);
    }
}
