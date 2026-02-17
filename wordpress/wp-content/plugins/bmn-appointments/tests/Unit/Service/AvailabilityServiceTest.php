<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Service;

use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Repository\AvailabilityRuleRepository;
use BMN\Appointments\Repository\StaffRepository;
use BMN\Appointments\Service\AvailabilityService;
use PHPUnit\Framework\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    private StaffRepository $staffRepo;
    private AppointmentTypeRepository $typeRepo;
    private AvailabilityRuleRepository $ruleRepo;
    private AppointmentRepository $appointmentRepo;
    private GoogleCalendarService $calendar;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->staffRepo = $this->createMock(StaffRepository::class);
        $this->typeRepo = $this->createMock(AppointmentTypeRepository::class);
        $this->ruleRepo = $this->createMock(AvailabilityRuleRepository::class);
        $this->appointmentRepo = $this->createMock(AppointmentRepository::class);
        $this->calendar = $this->createMock(GoogleCalendarService::class);

        $this->service = new AvailabilityService(
            $this->staffRepo,
            $this->typeRepo,
            $this->ruleRepo,
            $this->appointmentRepo,
            $this->calendar,
        );
    }

    public function testGetAvailableSlotsReturnsEmptyWhenNoStaff(): void
    {
        $this->staffRepo->method('findActive')->willReturn([]);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02');

        $this->assertSame([], $result);
    }

    public function testGetAvailableSlotsForSpecificStaff(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        // Recurring rule: Monday 09:00-12:00.
        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        // 2026-03-02 is a Monday.
        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', null, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        $this->assertContains('09:00', $result['2026-03-02']);
        $this->assertContains('09:15', $result['2026-03-02']);
        $this->assertContains('11:30', $result['2026-03-02']);
        // 11:45 + 30min = 12:15 > 12:00, so 11:45 should not be included (with default 30min duration).
    }

    public function testGetAvailableSlotsUsesTypeDuration(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        $type = (object) ['id' => 1, 'duration_minutes' => 60, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->with(1)->willReturn($type);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', 1, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        // With 60min duration, last slot should be 11:00 (11:00 + 60 = 12:00).
        $this->assertContains('11:00', $result['2026-03-02']);
        $this->assertNotContains('11:15', $result['2026-03-02']);
    }

    public function testGetAvailableSlotsSubtractsBookedSlots(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([
            (object) ['appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00', 'status' => 'confirmed', 'appointment_type_id' => 1],
        ]);

        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', null, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        // 10:00 should be removed (booked).
        $this->assertNotContains('10:00', $result['2026-03-02']);
        // 09:45 should also be removed (30min slot starting at 09:45 ends at 10:15, overlaps).
        $this->assertNotContains('09:45', $result['2026-03-02']);
        // 09:00 should still be available.
        $this->assertContains('09:00', $result['2026-03-02']);
    }

    public function testGetAvailableSlotsHandlesSpecificDateOverride(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        // Recurring is Mon 09:00-17:00, but specific date override is 13:00-15:00.
        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'specific_date' => null],
            (object) ['rule_type' => 'specific_date', 'day_of_week' => null, 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'specific_date' => '2026-03-02'],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', null, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        // Should only have slots from 13:00-15:00, not 09:00-17:00.
        $this->assertContains('13:00', $result['2026-03-02']);
        $this->assertContains('14:00', $result['2026-03-02']);
        $this->assertNotContains('09:00', $result['2026-03-02']);
        $this->assertNotContains('16:00', $result['2026-03-02']);
    }

    public function testGetAvailableSlotsHandlesBlockedDates(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'specific_date' => null],
            (object) ['rule_type' => 'blocked', 'day_of_week' => null, 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'specific_date' => '2026-03-02'],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', null, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        $this->assertContains('09:00', $result['2026-03-02']);
        $this->assertNotContains('12:00', $result['2026-03-02']);
        $this->assertNotContains('12:15', $result['2026-03-02']);
        $this->assertContains('13:00', $result['2026-03-02']);
    }

    public function testGetAvailableSlotsReturnsNothingOnOffDay(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        // Only Monday rules.
        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        // 2026-03-03 is Tuesday.
        $result = $this->service->getAvailableSlots('2026-03-03', '2026-03-03', null, 1);

        $this->assertArrayNotHasKey('2026-03-03', $result);
    }

    public function testGetAvailableSlotsMultipleDays(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'specific_date' => null],
            (object) ['rule_type' => 'recurring', 'day_of_week' => 2, 'start_time' => '14:00:00', 'end_time' => '15:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        // Mon 2026-03-02 and Tue 2026-03-03.
        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-03', null, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        $this->assertArrayHasKey('2026-03-03', $result);
    }

    public function testIsSlotAvailableReturnsTrue(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);
        $this->staffRepo->method('findByAppointmentType')->willReturn([$staff]);

        $type = (object) ['id' => 1, 'duration_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->with(1)->willReturn($type);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->isSlotAvailable('2026-03-02', '10:00', 1, 1);

        $this->assertTrue($result);
    }

    public function testIsSlotAvailableReturnsFalseWhenBooked(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);
        $this->staffRepo->method('findByAppointmentType')->willReturn([$staff]);

        $type = (object) ['id' => 1, 'duration_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->with(1)->willReturn($type);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([
            (object) ['appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00', 'status' => 'confirmed', 'appointment_type_id' => 1],
        ]);

        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->isSlotAvailable('2026-03-02', '10:00', 1, 1);

        $this->assertFalse($result);
    }

    public function testIsSlotAvailableReturnsFalseOnOffDay(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);
        $this->staffRepo->method('findByAppointmentType')->willReturn([$staff]);

        $type = (object) ['id' => 1, 'duration_minutes' => 30, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->with(1)->willReturn($type);

        // Only Monday.
        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'specific_date' => null],
        ]);

        $this->appointmentRepo->method('findBookedSlots')->willReturn([]);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        // 2026-03-04 is Wednesday.
        $result = $this->service->isSlotAvailable('2026-03-04', '10:00', 1, 1);

        $this->assertFalse($result);
    }

    public function testGetAvailableSlotsWithBuffers(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        $type = (object) ['id' => 1, 'duration_minutes' => 30, 'buffer_before' => 15, 'buffer_after' => 15];
        $this->typeRepo->method('find')->with(1)->willReturn($type);

        $this->ruleRepo->method('findByStaffAndType')->willReturn([
            (object) ['rule_type' => 'recurring', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'specific_date' => null],
        ]);

        // Booking at 10:00-10:30 with 15min buffers means 09:45-10:45 blocked.
        $this->appointmentRepo->method('findBookedSlots')->willReturn([
            (object) ['appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00', 'status' => 'confirmed', 'appointment_type_id' => 1],
        ]);

        $this->calendar->method('isStaffConnected')->willReturn(false);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', 1, 1);

        $this->assertArrayHasKey('2026-03-02', $result);
        // 09:30 slot would end at 10:00 which overlaps with 09:45 buffer_before, so it should be removed.
        $this->assertNotContains('09:30', $result['2026-03-02']);
        $this->assertNotContains('10:00', $result['2026-03-02']);
        $this->assertNotContains('10:15', $result['2026-03-02']);
        // 09:00 should be fine (09:00-09:30, well before 09:45).
        $this->assertContains('09:00', $result['2026-03-02']);
    }

    public function testGetAvailableSlotsWithInactiveStaff(): void
    {
        $staff = (object) ['id' => 1, 'is_active' => false];
        $this->staffRepo->method('find')->with(1)->willReturn($staff);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', null, 1);

        $this->assertSame([], $result);
    }

    public function testGetAvailableSlotsWithNullStaff(): void
    {
        $this->staffRepo->method('find')->with(999)->willReturn(null);

        $result = $this->service->getAvailableSlots('2026-03-02', '2026-03-02', null, 999);

        $this->assertSame([], $result);
    }
}
