<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Service;

use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Notification\AppointmentNotificationService;
use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Repository\AttendeeRepository;
use BMN\Appointments\Repository\StaffRepository;
use BMN\Appointments\Service\AppointmentService;
use BMN\Appointments\Service\AvailabilityService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AppointmentServiceTest extends TestCase
{
    private AppointmentRepository $appointmentRepo;
    private AppointmentTypeRepository $typeRepo;
    private AttendeeRepository $attendeeRepo;
    private StaffRepository $staffRepo;
    private AvailabilityService $availabilityService;
    private GoogleCalendarService $calendar;
    private AppointmentNotificationService $notificationService;
    private AppointmentService $service;

    protected function setUp(): void
    {
        // Reset transients for rate limiting.
        $GLOBALS['wp_transients'] = [];

        $this->appointmentRepo = $this->createMock(AppointmentRepository::class);
        $this->typeRepo = $this->createMock(AppointmentTypeRepository::class);
        $this->attendeeRepo = $this->createMock(AttendeeRepository::class);
        $this->staffRepo = $this->createMock(StaffRepository::class);
        $this->availabilityService = $this->createMock(AvailabilityService::class);
        $this->calendar = $this->createMock(GoogleCalendarService::class);
        $this->notificationService = $this->createMock(AppointmentNotificationService::class);

        $this->service = new AppointmentService(
            $this->appointmentRepo,
            $this->typeRepo,
            $this->attendeeRepo,
            $this->staffRepo,
            $this->availabilityService,
            $this->calendar,
            $this->notificationService,
        );
    }

    // ------------------------------------------------------------------
    // createAppointment
    // ------------------------------------------------------------------

    public function testCreateAppointmentSuccess(): void
    {
        $type = (object) ['id' => 1, 'name' => 'Showing', 'is_active' => true, 'duration_minutes' => 30, 'requires_approval' => false, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->willReturn($type);

        $staff = (object) ['id' => 1, 'name' => 'Steve', 'is_active' => true];
        $this->staffRepo->method('find')->willReturn($staff);
        $this->staffRepo->method('findPrimary')->willReturn($staff);

        $this->availabilityService->method('isSlotAvailable')->willReturn(true);
        $this->appointmentRepo->method('createWithTransaction')->willReturn(1);
        $this->attendeeRepo->method('create')->willReturn(1);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        $appointment = (object) [
            'id' => 1, 'staff_id' => 1, 'appointment_type_id' => 1, 'status' => 'confirmed',
            'appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
            'client_name' => 'John', 'client_email' => 'john@test.com', 'client_phone' => null,
            'listing_id' => null, 'notes' => null, 'google_event_id' => null,
            'cancellation_reason' => null, 'cancelled_by' => null, 'reschedule_count' => 0,
            'original_datetime' => null, 'type_name' => 'Showing', 'staff_name' => 'Steve',
            'duration_minutes' => 30, 'color' => '#3B82F6',
            'created_at' => '2026-03-01 12:00:00', 'updated_at' => '2026-03-01 12:00:00',
        ];
        $this->appointmentRepo->method('find')->willReturn($appointment);

        $result = $this->service->createAppointment([
            'appointment_type_id' => 1,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);

        $this->assertSame(1, $result['id']);
        $this->assertSame('confirmed', $result['status']);
    }

    public function testCreateAppointmentThrowsOnInvalidType(): void
    {
        $this->typeRepo->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid appointment type.');

        $this->service->createAppointment([
            'appointment_type_id' => 999,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);
    }

    public function testCreateAppointmentThrowsOnNoStaff(): void
    {
        $type = (object) ['id' => 1, 'is_active' => true, 'duration_minutes' => 30, 'requires_approval' => false, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->willReturn($type);
        $this->staffRepo->method('findPrimary')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No staff available.');

        $this->service->createAppointment([
            'appointment_type_id' => 1,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);
    }

    public function testCreateAppointmentThrowsOnUnavailableSlot(): void
    {
        $type = (object) ['id' => 1, 'is_active' => true, 'duration_minutes' => 30, 'requires_approval' => false, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->willReturn($type);

        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->willReturn($staff);

        $this->availabilityService->method('isSlotAvailable')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Selected time slot is no longer available.');

        $this->service->createAppointment([
            'appointment_type_id' => 1,
            'staff_id'            => 1,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);
    }

    public function testCreateAppointmentThrowsOnDoubleBook(): void
    {
        $type = (object) ['id' => 1, 'is_active' => true, 'duration_minutes' => 30, 'requires_approval' => false, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->willReturn($type);

        $staff = (object) ['id' => 1, 'is_active' => true];
        $this->staffRepo->method('find')->willReturn($staff);

        $this->availabilityService->method('isSlotAvailable')->willReturn(true);
        $this->appointmentRepo->method('createWithTransaction')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('just booked by someone else');

        $this->service->createAppointment([
            'appointment_type_id' => 1,
            'staff_id'            => 1,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);
    }

    public function testCreateAppointmentWithRequiresApproval(): void
    {
        $type = (object) ['id' => 1, 'name' => 'Consultation', 'is_active' => true, 'duration_minutes' => 30, 'requires_approval' => true, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->willReturn($type);

        $staff = (object) ['id' => 1, 'name' => 'Steve', 'is_active' => true];
        $this->staffRepo->method('find')->willReturn($staff);

        $this->availabilityService->method('isSlotAvailable')->willReturn(true);

        // Verify createWithTransaction receives status=pending.
        $this->appointmentRepo->expects($this->once())
            ->method('createWithTransaction')
            ->with($this->callback(function (array $data): bool {
                return $data['status'] === 'pending';
            }))
            ->willReturn(1);

        $this->attendeeRepo->method('create')->willReturn(1);
        $this->calendar->method('isStaffConnected')->willReturn(false);

        $appointment = (object) [
            'id' => 1, 'staff_id' => 1, 'appointment_type_id' => 1, 'status' => 'pending',
            'appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
            'client_name' => 'John', 'client_email' => 'john@test.com', 'client_phone' => null,
            'listing_id' => null, 'notes' => null, 'google_event_id' => null,
            'cancellation_reason' => null, 'cancelled_by' => null, 'reschedule_count' => 0,
            'original_datetime' => null, 'type_name' => null, 'staff_name' => null,
            'duration_minutes' => null, 'color' => null,
            'created_at' => '2026-03-01 12:00:00', 'updated_at' => '2026-03-01 12:00:00',
        ];
        $this->appointmentRepo->method('find')->willReturn($appointment);

        $result = $this->service->createAppointment([
            'appointment_type_id' => 1,
            'staff_id'            => 1,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);

        $this->assertSame('pending', $result['status']);
    }

    public function testCreateAppointmentThrowsOnRateLimit(): void
    {
        // Set up transient to indicate rate limit exceeded.
        set_transient('bmn_appt_rate_' . md5('john@test.com' . ($_SERVER['REMOTE_ADDR'] ?? '')), 5, 900);

        $type = (object) ['id' => 1, 'is_active' => true, 'duration_minutes' => 30, 'requires_approval' => false, 'buffer_before' => 0, 'buffer_after' => 0];
        $this->typeRepo->method('find')->willReturn($type);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many booking attempts');

        $this->service->createAppointment([
            'appointment_type_id' => 1,
            'date'                => '2026-03-02',
            'time'                => '10:00',
            'client_name'         => 'John',
            'client_email'        => 'john@test.com',
        ]);
    }

    // ------------------------------------------------------------------
    // cancelAppointment
    // ------------------------------------------------------------------

    public function testCancelAppointmentSuccess(): void
    {
        $appointment = (object) [
            'id' => 1, 'user_id' => 42, 'staff_id' => 1, 'status' => 'confirmed',
            'appointment_date' => '2030-12-31', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
            'google_event_id' => null, 'client_name' => 'John', 'client_email' => 'john@test.com',
            'client_phone' => null, 'listing_id' => null, 'notes' => null,
            'cancellation_reason' => null, 'cancelled_by' => null, 'reschedule_count' => 0,
            'original_datetime' => null, 'appointment_type_id' => 1,
            'type_name' => null, 'staff_name' => null, 'duration_minutes' => null, 'color' => null,
            'created_at' => '2026-01-01', 'updated_at' => '2026-01-01',
        ];

        $cancelledAppointment = clone $appointment;
        $cancelledAppointment->status = 'cancelled';
        $cancelledAppointment->cancellation_reason = 'Changed plans';
        $cancelledAppointment->cancelled_by = 'client';

        // find() called twice: once for check, once after cancel.
        $this->appointmentRepo->method('find')
            ->willReturnOnConsecutiveCalls($appointment, $cancelledAppointment);
        $this->appointmentRepo->method('cancel')->willReturn(true);

        $result = $this->service->cancelAppointment(1, 42, 'Changed plans');

        $this->assertSame('cancelled', $result['status']);
    }

    public function testCancelAppointmentThrowsOnNotFound(): void
    {
        $this->appointmentRepo->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Appointment not found.');

        $this->service->cancelAppointment(999, 42, 'test');
    }

    public function testCancelAppointmentThrowsOnNotAuthorized(): void
    {
        $appointment = (object) [
            'id' => 1, 'user_id' => 42, 'staff_id' => 1, 'status' => 'confirmed',
            'appointment_date' => '2030-12-31', 'start_time' => '10:00:00',
        ];

        $this->appointmentRepo->method('find')->willReturn($appointment);
        $this->staffRepo->method('findByUserId')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not authorized');

        $this->service->cancelAppointment(1, 999, 'test');
    }

    public function testCancelAppointmentThrowsOnAlreadyCancelled(): void
    {
        $appointment = (object) [
            'id' => 1, 'user_id' => 42, 'staff_id' => 1, 'status' => 'cancelled',
            'appointment_date' => '2030-12-31', 'start_time' => '10:00:00',
        ];

        $this->appointmentRepo->method('find')->willReturn($appointment);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already cancelled');

        $this->service->cancelAppointment(1, 42, 'test');
    }

    // ------------------------------------------------------------------
    // rescheduleAppointment
    // ------------------------------------------------------------------

    public function testRescheduleAppointmentThrowsOnMaxReschedules(): void
    {
        $appointment = (object) [
            'id' => 1, 'user_id' => 42, 'staff_id' => 1, 'reschedule_count' => 3,
            'appointment_date' => '2030-12-31', 'start_time' => '10:00:00',
            'appointment_type_id' => 1,
        ];

        $this->appointmentRepo->method('find')->willReturn($appointment);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maximum reschedule limit');

        $this->service->rescheduleAppointment(1, 42, '2026-04-01', '11:00');
    }

    public function testRescheduleAppointmentThrowsOnNotFound(): void
    {
        $this->appointmentRepo->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Appointment not found.');

        $this->service->rescheduleAppointment(999, 42, '2026-04-01', '11:00');
    }

    // ------------------------------------------------------------------
    // getAppointment
    // ------------------------------------------------------------------

    public function testGetAppointmentThrowsOnNotFound(): void
    {
        $this->appointmentRepo->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Appointment not found.');

        $this->service->getAppointment(999, 42);
    }

    public function testGetAppointmentThrowsOnNotAuthorized(): void
    {
        $appointment = (object) [
            'id' => 1, 'user_id' => 42, 'staff_id' => 1,
        ];
        $this->appointmentRepo->method('find')->willReturn($appointment);
        $this->staffRepo->method('findByUserId')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not authorized');

        $this->service->getAppointment(1, 999);
    }

    public function testGetAppointmentSuccessForOwner(): void
    {
        $appointment = (object) [
            'id' => 1, 'user_id' => 42, 'staff_id' => 1, 'status' => 'confirmed',
            'appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
            'appointment_type_id' => 1, 'client_name' => 'John', 'client_email' => 'john@test.com',
            'client_phone' => null, 'listing_id' => null, 'notes' => null, 'google_event_id' => null,
            'cancellation_reason' => null, 'cancelled_by' => null, 'reschedule_count' => 0,
            'original_datetime' => null, 'type_name' => 'Showing', 'staff_name' => 'Steve',
            'duration_minutes' => 30, 'color' => '#3B82F6',
            'created_at' => '2026-03-01 12:00:00', 'updated_at' => '2026-03-01 12:00:00',
        ];

        $this->appointmentRepo->method('find')->willReturn($appointment);
        $this->attendeeRepo->method('findByAppointment')->willReturn([]);

        $result = $this->service->getAppointment(1, 42);

        $this->assertSame(1, $result['id']);
        $this->assertArrayHasKey('attendees', $result);
    }

    // ------------------------------------------------------------------
    // getUserAppointments
    // ------------------------------------------------------------------

    public function testGetUserAppointmentsReturnsFormattedList(): void
    {
        $this->appointmentRepo->method('findByUser')->willReturn([
            (object) [
                'id' => 1, 'staff_id' => 1, 'appointment_type_id' => 1, 'status' => 'confirmed',
                'appointment_date' => '2026-03-02', 'start_time' => '10:00:00', 'end_time' => '10:30:00',
                'client_name' => 'John', 'client_email' => 'john@test.com', 'client_phone' => null,
                'listing_id' => null, 'notes' => null, 'google_event_id' => null,
                'cancellation_reason' => null, 'cancelled_by' => null, 'reschedule_count' => 0,
                'original_datetime' => null, 'type_name' => 'Showing', 'staff_name' => 'Steve',
                'duration_minutes' => 30, 'color' => '#3B82F6',
                'created_at' => '2026-03-01', 'updated_at' => '2026-03-01',
            ],
        ]);

        $this->staffRepo->method('findByUserId')->willReturn(null);

        $result = $this->service->getUserAppointments(42);

        $this->assertCount(1, $result);
        $this->assertSame('confirmed', $result[0]['status']);
    }

    // ------------------------------------------------------------------
    // getPolicy
    // ------------------------------------------------------------------

    public function testGetPolicyReturnsExpectedStructure(): void
    {
        $policy = $this->service->getPolicy();

        $this->assertArrayHasKey('cancel_hours_before', $policy);
        $this->assertArrayHasKey('reschedule_hours_before', $policy);
        $this->assertArrayHasKey('max_reschedules', $policy);
        $this->assertArrayHasKey('rate_limit_max', $policy);
        $this->assertArrayHasKey('rate_limit_window_minutes', $policy);
        $this->assertSame(2, $policy['cancel_hours_before']);
        $this->assertSame(4, $policy['reschedule_hours_before']);
        $this->assertSame(3, $policy['max_reschedules']);
        $this->assertSame(5, $policy['rate_limit_max']);
        $this->assertSame(15, $policy['rate_limit_window_minutes']);
    }
}
