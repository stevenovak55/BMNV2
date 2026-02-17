<?php

declare(strict_types=1);

namespace BMN\Appointments\Service;

use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Notification\AppointmentNotificationService;
use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Repository\AttendeeRepository;
use BMN\Appointments\Repository\StaffRepository;
use RuntimeException;

/**
 * Core booking lifecycle service.
 *
 * Single source of truth for appointment operations (One Service, Two Interfaces rule).
 * Used by both the REST controller (iOS) and any future web interface.
 */
class AppointmentService
{
    /** Maximum booking attempts per 15 minutes per IP/user. */
    private const RATE_LIMIT_MAX = 5;
    private const RATE_LIMIT_WINDOW = 900; // 15 minutes

    /** Maximum reschedules per appointment. */
    private const MAX_RESCHEDULES = 3;

    /** Hours before appointment that cancellation is allowed. */
    private const CANCEL_HOURS_BEFORE = 2;

    /** Hours before appointment that reschedule is allowed. */
    private const RESCHEDULE_HOURS_BEFORE = 4;

    private readonly AppointmentRepository $appointmentRepo;
    private readonly AppointmentTypeRepository $typeRepo;
    private readonly AttendeeRepository $attendeeRepo;
    private readonly StaffRepository $staffRepo;
    private readonly AvailabilityService $availabilityService;
    private readonly GoogleCalendarService $calendar;
    private readonly ?AppointmentNotificationService $notificationService;

    public function __construct(
        AppointmentRepository $appointmentRepo,
        AppointmentTypeRepository $typeRepo,
        AttendeeRepository $attendeeRepo,
        StaffRepository $staffRepo,
        AvailabilityService $availabilityService,
        GoogleCalendarService $calendar,
        ?AppointmentNotificationService $notificationService = null,
    ) {
        $this->appointmentRepo = $appointmentRepo;
        $this->typeRepo = $typeRepo;
        $this->attendeeRepo = $attendeeRepo;
        $this->staffRepo = $staffRepo;
        $this->availabilityService = $availabilityService;
        $this->calendar = $calendar;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new appointment.
     *
     * @param array $data Keys: appointment_type_id, staff_id, date, time, client_name, client_email,
     *                     client_phone, user_id, listing_id, notes, attendees.
     * @return array Created appointment data.
     *
     * @throws RuntimeException On validation failure, rate limit, or slot conflict.
     */
    public function createAppointment(array $data): array
    {
        // Rate limit check.
        $this->checkRateLimit($data['client_email'] ?? '');

        // Validate appointment type.
        $type = $this->typeRepo->find((int) $data['appointment_type_id']);
        if ($type === null || !$type->is_active) {
            throw new RuntimeException('Invalid appointment type.');
        }

        // Resolve staff.
        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId === 0) {
            $primary = $this->staffRepo->findPrimary();
            if ($primary === null) {
                throw new RuntimeException('No staff available.');
            }
            $staffId = (int) $primary->id;
        }

        $staff = $this->staffRepo->find($staffId);
        if ($staff === null || !$staff->is_active) {
            throw new RuntimeException('Selected staff is not available.');
        }

        // Validate slot availability.
        $date = $data['date'];
        $time = $data['time'];

        if (!$this->availabilityService->isSlotAvailable($date, $time, (int) $type->id, $staffId)) {
            throw new RuntimeException('Selected time slot is no longer available.');
        }

        // Calculate end time.
        $endTime = date('H:i:s', strtotime($time) + ((int) $type->duration_minutes * 60));

        // Determine status.
        $status = $type->requires_approval ? 'pending' : 'confirmed';

        // Insert with transaction (UNIQUE constraint prevents double-booking).
        $appointmentId = $this->appointmentRepo->createWithTransaction([
            'staff_id'            => $staffId,
            'appointment_type_id' => (int) $type->id,
            'status'              => $status,
            'appointment_date'    => $date,
            'start_time'          => $time,
            'end_time'            => $endTime,
            'user_id'             => $data['user_id'] ?? null,
            'client_name'         => sanitize_text_field($data['client_name']),
            'client_email'        => sanitize_email($data['client_email']),
            'client_phone'        => !empty($data['client_phone']) ? sanitize_text_field($data['client_phone']) : null,
            'listing_id'          => $data['listing_id'] ?? null,
            'notes'               => !empty($data['notes']) ? sanitize_text_field($data['notes']) : null,
        ]);

        if ($appointmentId === false) {
            throw new RuntimeException('This time slot was just booked by someone else. Please choose another time.');
        }

        // Create primary attendee.
        $this->attendeeRepo->create([
            'appointment_id' => $appointmentId,
            'attendee_type'  => 'primary',
            'user_id'        => $data['user_id'] ?? null,
            'name'           => sanitize_text_field($data['client_name']),
            'email'          => sanitize_email($data['client_email']),
            'phone'          => !empty($data['client_phone']) ? sanitize_text_field($data['client_phone']) : null,
        ]);

        // Create additional attendees.
        if (!empty($data['attendees']) && is_array($data['attendees'])) {
            foreach ($data['attendees'] as $attendee) {
                $this->attendeeRepo->create([
                    'appointment_id' => $appointmentId,
                    'attendee_type'  => $attendee['type'] ?? 'additional',
                    'name'           => sanitize_text_field($attendee['name']),
                    'email'          => sanitize_email($attendee['email']),
                    'phone'          => !empty($attendee['phone']) ? sanitize_text_field($attendee['phone']) : null,
                ]);
            }
        }

        // Sync to Google Calendar.
        $this->syncToCalendar($appointmentId, $staffId, $type, $data, $date, $time, $endTime);

        // Send confirmation.
        $this->notificationService?->sendConfirmation($appointmentId);

        return $this->formatAppointment($this->appointmentRepo->find($appointmentId));
    }

    /**
     * Cancel an appointment.
     *
     * @throws RuntimeException On policy violation or not found.
     */
    public function cancelAppointment(int $id, int $userId, string $reason): array
    {
        $appointment = $this->appointmentRepo->find($id);

        if ($appointment === null) {
            throw new RuntimeException('Appointment not found.');
        }

        // Check ownership (client or staff).
        $cancelledBy = $this->resolveCancelledBy($appointment, $userId);

        // Staff can always cancel. Clients must respect policy.
        if ($cancelledBy === 'client') {
            $this->checkCancelPolicy($appointment);
        }

        if ($appointment->status === 'cancelled') {
            throw new RuntimeException('Appointment is already cancelled.');
        }

        // Cancel in database.
        $this->appointmentRepo->cancel($id, $reason, $cancelledBy);

        // Delete Google Calendar event.
        if (!empty($appointment->google_event_id)) {
            $this->calendar->deleteEvent((int) $appointment->staff_id, $appointment->google_event_id);
        }

        // Send cancellation notification.
        $this->notificationService?->sendCancellation($id, $reason);

        return $this->formatAppointment($this->appointmentRepo->find($id));
    }

    /**
     * Reschedule an appointment.
     *
     * @throws RuntimeException On policy violation, max reschedules, or slot unavailable.
     */
    public function rescheduleAppointment(int $id, int $userId, string $newDate, string $newTime): array
    {
        $appointment = $this->appointmentRepo->find($id);

        if ($appointment === null) {
            throw new RuntimeException('Appointment not found.');
        }

        // Check ownership.
        if ((int) ($appointment->user_id ?? 0) !== $userId) {
            $staff = $this->staffRepo->findByUserId($userId);
            if ($staff === null || (int) $staff->id !== (int) $appointment->staff_id) {
                throw new RuntimeException('Not authorized to reschedule this appointment.');
            }
        }

        // Check reschedule limit.
        if ((int) $appointment->reschedule_count >= self::MAX_RESCHEDULES) {
            throw new RuntimeException('Maximum reschedule limit reached.');
        }

        // Check reschedule policy (time before).
        $this->checkReschedulePolicy($appointment);

        // Validate new slot.
        if (!$this->availabilityService->isSlotAvailable($newDate, $newTime, (int) $appointment->appointment_type_id, (int) $appointment->staff_id)) {
            throw new RuntimeException('Selected time slot is not available.');
        }

        $type = $this->typeRepo->find((int) $appointment->appointment_type_id);
        $duration = $type !== null ? (int) $type->duration_minutes : 30;
        $newEndTime = date('H:i:s', strtotime($newTime) + ($duration * 60));

        $oldDate = $appointment->appointment_date;
        $oldTime = $appointment->start_time;

        // Update appointment.
        $this->appointmentRepo->reschedule($id, $newDate, $newTime, $newEndTime);

        // Update Google Calendar.
        if (!empty($appointment->google_event_id)) {
            $tz = function_exists('wp_timezone') ? wp_timezone()->getName() : 'America/New_York';
            $this->calendar->updateEvent((int) $appointment->staff_id, $appointment->google_event_id, [
                'summary' => ($type->name ?? 'Appointment') . ' - ' . $appointment->client_name,
                'start'   => $newDate . 'T' . $newTime . ':00',
                'end'     => $newDate . 'T' . $newEndTime,
            ]);
        }

        // Send reschedule notification.
        $this->notificationService?->sendReschedule($id, $oldDate, $oldTime);

        return $this->formatAppointment($this->appointmentRepo->find($id));
    }

    /**
     * Get a single appointment (with ownership check).
     *
     * @throws RuntimeException If not found or not authorized.
     */
    public function getAppointment(int $id, int $userId): array
    {
        $appointment = $this->appointmentRepo->find($id);

        if ($appointment === null) {
            throw new RuntimeException('Appointment not found.');
        }

        // Check ownership: user is the client or the assigned staff.
        if ((int) ($appointment->user_id ?? 0) !== $userId) {
            $staff = $this->staffRepo->findByUserId($userId);
            if ($staff === null || (int) $staff->id !== (int) $appointment->staff_id) {
                throw new RuntimeException('Not authorized to view this appointment.');
            }
        }

        $formatted = $this->formatAppointment($appointment);
        $formatted['attendees'] = array_map(
            static fn (object $a): array => [
                'name'  => $a->name,
                'email' => $a->email,
                'phone' => $a->phone,
                'type'  => $a->attendee_type,
            ],
            $this->attendeeRepo->findByAppointment($id)
        );

        return $formatted;
    }

    /**
     * Get all appointments for a user.
     *
     * @param array<string, mixed> $filters Optional: status, from_date, to_date.
     * @return array[]
     */
    public function getUserAppointments(int $userId, array $filters = []): array
    {
        $appointments = $this->appointmentRepo->findByUser($userId, $filters);

        // Also include appointments where user is staff.
        $staff = $this->staffRepo->findByUserId($userId);
        if ($staff !== null) {
            $staffAppointments = $this->appointmentRepo->findByStaff((int) $staff->id, $filters);
            $existingIds = array_map(static fn (object $a): int => (int) $a->id, $appointments);
            foreach ($staffAppointments as $sa) {
                if (!in_array((int) $sa->id, $existingIds, true)) {
                    $appointments[] = $sa;
                }
            }
        }

        return array_map([$this, 'formatAppointment'], $appointments);
    }

    /**
     * Get available reschedule slots for an appointment.
     *
     * @return array<string, string[]>
     */
    public function getRescheduleSlots(int $id, int $userId, string $startDate, string $endDate): array
    {
        $appointment = $this->appointmentRepo->find($id);

        if ($appointment === null) {
            throw new RuntimeException('Appointment not found.');
        }

        return $this->availabilityService->getAvailableSlots(
            $startDate,
            $endDate,
            (int) $appointment->appointment_type_id,
            (int) $appointment->staff_id,
        );
    }

    /**
     * Get the cancellation/reschedule policy.
     */
    public function getPolicy(): array
    {
        return [
            'cancel_hours_before'    => self::CANCEL_HOURS_BEFORE,
            'reschedule_hours_before' => self::RESCHEDULE_HOURS_BEFORE,
            'max_reschedules'         => self::MAX_RESCHEDULES,
            'rate_limit_max'          => self::RATE_LIMIT_MAX,
            'rate_limit_window_minutes' => (int) (self::RATE_LIMIT_WINDOW / 60),
        ];
    }

    /**
     * Check rate limit for booking.
     *
     * @throws RuntimeException If rate limit exceeded.
     */
    private function checkRateLimit(string $email): void
    {
        $key = 'bmn_appt_rate_' . md5($email . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX) {
            throw new RuntimeException('Too many booking attempts. Please wait before trying again.');
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);
    }

    /**
     * Check cancellation policy.
     *
     * @throws RuntimeException If too close to appointment time.
     */
    private function checkCancelPolicy(object $appointment): void
    {
        $appointmentTimestamp = strtotime($appointment->appointment_date . ' ' . $appointment->start_time);
        $now = (int) current_time('timestamp');
        $hoursUntil = ($appointmentTimestamp - $now) / 3600;

        if ($hoursUntil < self::CANCEL_HOURS_BEFORE) {
            throw new RuntimeException(
                sprintf('Appointments must be cancelled at least %d hours in advance.', self::CANCEL_HOURS_BEFORE)
            );
        }
    }

    /**
     * Check reschedule policy.
     *
     * @throws RuntimeException If too close to appointment time.
     */
    private function checkReschedulePolicy(object $appointment): void
    {
        $appointmentTimestamp = strtotime($appointment->appointment_date . ' ' . $appointment->start_time);
        $now = (int) current_time('timestamp');
        $hoursUntil = ($appointmentTimestamp - $now) / 3600;

        if ($hoursUntil < self::RESCHEDULE_HOURS_BEFORE) {
            throw new RuntimeException(
                sprintf('Appointments must be rescheduled at least %d hours in advance.', self::RESCHEDULE_HOURS_BEFORE)
            );
        }
    }

    /**
     * Determine who is cancelling (client or staff).
     */
    private function resolveCancelledBy(object $appointment, int $userId): string
    {
        if ((int) ($appointment->user_id ?? 0) === $userId) {
            return 'client';
        }

        $staff = $this->staffRepo->findByUserId($userId);
        if ($staff !== null && (int) $staff->id === (int) $appointment->staff_id) {
            return 'staff';
        }

        throw new RuntimeException('Not authorized to cancel this appointment.');
    }

    /**
     * Sync appointment to Google Calendar.
     */
    private function syncToCalendar(int $appointmentId, int $staffId, object $type, array $data, string $date, string $time, string $endTime): void
    {
        if (!$this->calendar->isStaffConnected($staffId)) {
            return;
        }

        $result = $this->calendar->createEvent($staffId, [
            'summary'     => $type->name . ' - ' . ($data['client_name'] ?? ''),
            'description' => 'Client: ' . ($data['client_name'] ?? '') . "\nEmail: " . ($data['client_email'] ?? '') . "\nPhone: " . ($data['client_phone'] ?? 'N/A'),
            'start'       => $date . 'T' . $time . ':00',
            'end'         => $date . 'T' . $endTime,
            'attendees'   => [$data['client_email']],
        ]);

        if ($result !== false && isset($result['id'])) {
            $this->appointmentRepo->update($appointmentId, [
                'google_event_id' => $result['id'],
            ]);
        }
    }

    /**
     * Format an appointment record for API output.
     */
    private function formatAppointment(?object $appointment): array
    {
        if ($appointment === null) {
            return [];
        }

        return [
            'id'                  => (int) $appointment->id,
            'staff_id'            => (int) $appointment->staff_id,
            'appointment_type_id' => (int) $appointment->appointment_type_id,
            'status'              => $appointment->status,
            'appointment_date'    => $appointment->appointment_date,
            'start_time'          => $appointment->start_time,
            'end_time'            => $appointment->end_time,
            'client_name'         => $appointment->client_name,
            'client_email'        => $appointment->client_email,
            'client_phone'        => $appointment->client_phone ?? null,
            'listing_id'          => $appointment->listing_id ?? null,
            'notes'               => $appointment->notes ?? null,
            'google_event_id'     => $appointment->google_event_id ?? null,
            'cancellation_reason' => $appointment->cancellation_reason ?? null,
            'cancelled_by'        => $appointment->cancelled_by ?? null,
            'reschedule_count'    => (int) ($appointment->reschedule_count ?? 0),
            'original_datetime'   => $appointment->original_datetime ?? null,
            'type_name'           => $appointment->type_name ?? null,
            'staff_name'          => $appointment->staff_name ?? null,
            'duration_minutes'    => isset($appointment->duration_minutes) ? (int) $appointment->duration_minutes : null,
            'color'               => $appointment->color ?? null,
            'created_at'          => $appointment->created_at,
            'updated_at'          => $appointment->updated_at,
        ];
    }
}
