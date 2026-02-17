<?php

declare(strict_types=1);

namespace BMN\Appointments\Service;

use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Repository\AppointmentRepository;
use BMN\Appointments\Repository\AppointmentTypeRepository;
use BMN\Appointments\Repository\AvailabilityRuleRepository;
use BMN\Appointments\Repository\StaffRepository;

/**
 * Slot calculation engine.
 *
 * Computes available appointment slots by merging recurring rules
 * with specific-date overrides, then subtracting blocked dates,
 * booked appointments (with buffers), and Google Calendar busy times.
 */
class AvailabilityService
{
    private readonly StaffRepository $staffRepo;
    private readonly AppointmentTypeRepository $typeRepo;
    private readonly AvailabilityRuleRepository $ruleRepo;
    private readonly AppointmentRepository $appointmentRepo;
    private readonly GoogleCalendarService $calendar;

    public function __construct(
        StaffRepository $staffRepo,
        AppointmentTypeRepository $typeRepo,
        AvailabilityRuleRepository $ruleRepo,
        AppointmentRepository $appointmentRepo,
        GoogleCalendarService $calendar,
    ) {
        $this->staffRepo = $staffRepo;
        $this->typeRepo = $typeRepo;
        $this->ruleRepo = $ruleRepo;
        $this->appointmentRepo = $appointmentRepo;
        $this->calendar = $calendar;
    }

    /**
     * Get available time slots for a date range.
     *
     * @param array $options Optional: slot_interval (minutes, default 15).
     * @return array<string, string[]> Date => array of available times (H:i format).
     */
    public function getAvailableSlots(
        string $startDate,
        string $endDate,
        ?int $typeId = null,
        ?int $staffId = null,
        array $options = [],
    ): array {
        $type = $typeId !== null ? $this->typeRepo->find($typeId) : null;
        $duration = $type !== null ? (int) $type->duration_minutes : 30;
        $bufferBefore = $type !== null ? (int) $type->buffer_before : 0;
        $bufferAfter = $type !== null ? (int) $type->buffer_after : 0;
        $slotInterval = (int) ($options['slot_interval'] ?? 15);

        // Determine staff members to check.
        $staffMembers = $this->resolveStaff($staffId, $typeId);

        if ($staffMembers === []) {
            return [];
        }

        $allSlots = [];

        foreach ($staffMembers as $staff) {
            $sId = (int) $staff->id;
            $rules = $this->ruleRepo->findByStaffAndType($sId, $typeId);
            $bookedSlots = $this->appointmentRepo->findBookedSlots($sId, $startDate, $endDate);
            $googleBusy = $this->getGoogleBusy($sId, $startDate, $endDate);

            $current = new \DateTimeImmutable($startDate);
            $end = new \DateTimeImmutable($endDate);

            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                $dayOfWeek = (int) $current->format('w');

                // Get available windows for this day.
                $windows = $this->getWindowsForDay($rules, $dayOfWeek, $dateStr);

                // Generate slots within windows.
                $daySlots = $this->generateSlots($windows, $duration, $slotInterval);

                // Subtract booked appointments (with buffers).
                $daySlots = $this->subtractBooked($daySlots, $bookedSlots, $dateStr, $duration, $bufferBefore, $bufferAfter);

                // Subtract Google Calendar busy times.
                $daySlots = $this->subtractGoogleBusy($daySlots, $googleBusy, $dateStr, $duration);

                // Remove past slots.
                $daySlots = $this->removePastSlots($daySlots, $dateStr);

                if ($daySlots !== []) {
                    if (!isset($allSlots[$dateStr])) {
                        $allSlots[$dateStr] = [];
                    }
                    $allSlots[$dateStr] = array_unique(array_merge($allSlots[$dateStr], $daySlots));
                    sort($allSlots[$dateStr]);
                }

                $current = $current->modify('+1 day');
            }
        }

        // Sort by date.
        ksort($allSlots);

        return $allSlots;
    }

    /**
     * Check if a specific slot is available.
     */
    public function isSlotAvailable(string $date, string $time, int $typeId, ?int $staffId = null): bool
    {
        $slots = $this->getAvailableSlots($date, $date, $typeId, $staffId);

        return isset($slots[$date]) && in_array($time, $slots[$date], true);
    }

    /**
     * Resolve which staff members to check.
     *
     * @return object[]
     */
    private function resolveStaff(?int $staffId, ?int $typeId): array
    {
        if ($staffId !== null) {
            $staff = $this->staffRepo->find($staffId);
            return $staff !== null && $staff->is_active ? [$staff] : [];
        }

        if ($typeId !== null) {
            return $this->staffRepo->findByAppointmentType($typeId);
        }

        return $this->staffRepo->findActive();
    }

    /**
     * Get available time windows for a specific day.
     *
     * Merges recurring rules with specific_date overrides, then removes blocked periods.
     *
     * @return array<int, array{start: string, end: string}> Time windows.
     */
    private function getWindowsForDay(array $rules, int $dayOfWeek, string $dateStr): array
    {
        $windows = [];
        $blocked = [];
        $hasSpecificDate = false;

        foreach ($rules as $rule) {
            if ($rule->rule_type === 'specific_date' && $rule->specific_date === $dateStr) {
                // Specific date rules override recurring rules for this day.
                $hasSpecificDate = true;
                $windows[] = ['start' => $rule->start_time, 'end' => $rule->end_time];
            } elseif ($rule->rule_type === 'blocked') {
                if ($rule->specific_date === $dateStr || $rule->specific_date === null) {
                    $blocked[] = ['start' => $rule->start_time, 'end' => $rule->end_time];
                }
            }
        }

        // If no specific date rules, use recurring rules.
        if (!$hasSpecificDate) {
            foreach ($rules as $rule) {
                if ($rule->rule_type === 'recurring' && (int) $rule->day_of_week === $dayOfWeek) {
                    $windows[] = ['start' => $rule->start_time, 'end' => $rule->end_time];
                }
            }
        }

        // Subtract blocked periods from windows.
        return $this->subtractBlockedFromWindows($windows, $blocked);
    }

    /**
     * Generate time slots within available windows.
     *
     * @param array<int, array{start: string, end: string}> $windows
     * @return string[] Time slots in H:i format.
     */
    private function generateSlots(array $windows, int $duration, int $interval): array
    {
        $slots = [];

        foreach ($windows as $window) {
            $start = strtotime($window['start']);
            $end = strtotime($window['end']);
            $durationSecs = $duration * 60;
            $intervalSecs = $interval * 60;

            $current = $start;

            while (($current + $durationSecs) <= $end) {
                $slots[] = date('H:i', $current);
                $current += $intervalSecs;
            }
        }

        return array_unique($slots);
    }

    /**
     * Remove slots that overlap with booked appointments (including buffers).
     *
     * @param string[] $slots Available slots.
     * @param object[] $bookedSlots Booked appointments.
     * @return string[] Filtered slots.
     */
    private function subtractBooked(array $slots, array $bookedSlots, string $dateStr, int $duration, int $bufferBefore, int $bufferAfter): array
    {
        $dayBookings = array_filter(
            $bookedSlots,
            static fn (object $b): bool => $b->appointment_date === $dateStr
        );

        if ($dayBookings === []) {
            return $slots;
        }

        return array_values(array_filter($slots, static function (string $slot) use ($dayBookings, $duration, $bufferBefore, $bufferAfter): bool {
            $slotStart = strtotime($slot);
            $slotEnd = $slotStart + ($duration * 60);

            foreach ($dayBookings as $booking) {
                $bookedStart = strtotime($booking->start_time) - ($bufferBefore * 60);
                $bookedEnd = strtotime($booking->end_time) + ($bufferAfter * 60);

                // Check for overlap.
                if ($slotStart < $bookedEnd && $slotEnd > $bookedStart) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Remove slots that overlap with Google Calendar busy times.
     *
     * @param string[] $slots Available slots.
     * @param array    $busyPeriods Google busy periods.
     * @return string[] Filtered slots.
     */
    private function subtractGoogleBusy(array $slots, array $busyPeriods, string $dateStr, int $duration): array
    {
        if ($busyPeriods === []) {
            return $slots;
        }

        return array_values(array_filter($slots, static function (string $slot) use ($busyPeriods, $dateStr, $duration): bool {
            $slotStart = strtotime($dateStr . ' ' . $slot);
            $slotEnd = $slotStart + ($duration * 60);

            foreach ($busyPeriods as $busy) {
                $busyStart = strtotime($busy['start']);
                $busyEnd = strtotime($busy['end']);

                if ($slotStart < $busyEnd && $slotEnd > $busyStart) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Remove slots that are in the past.
     *
     * @param string[] $slots Available slots.
     * @return string[] Future slots only.
     */
    private function removePastSlots(array $slots, string $dateStr): array
    {
        $now = (int) current_time('timestamp');
        $today = date('Y-m-d', $now);

        if ($dateStr > $today) {
            return $slots;
        }

        if ($dateStr < $today) {
            return [];
        }

        // Same day: filter out past slots.
        $currentTime = date('H:i', $now);

        return array_values(array_filter(
            $slots,
            static fn (string $slot): bool => $slot > $currentTime
        ));
    }

    /**
     * Get Google Calendar busy times for a staff member.
     *
     * @return array Array of busy periods.
     */
    private function getGoogleBusy(int $staffId, string $startDate, string $endDate): array
    {
        if (!$this->calendar->isStaffConnected($staffId)) {
            return [];
        }

        $tz = function_exists('wp_timezone') ? wp_timezone()->getName() : 'America/New_York';

        return $this->calendar->getFreeBusy(
            $staffId,
            $startDate . 'T00:00:00' . $this->getTimezoneOffset($tz),
            $endDate . 'T23:59:59' . $this->getTimezoneOffset($tz),
        );
    }

    /**
     * Subtract blocked periods from available windows.
     *
     * @param array<int, array{start: string, end: string}> $windows
     * @param array<int, array{start: string, end: string}> $blocked
     * @return array<int, array{start: string, end: string}>
     */
    private function subtractBlockedFromWindows(array $windows, array $blocked): array
    {
        if ($blocked === []) {
            return $windows;
        }

        $result = [];

        foreach ($windows as $window) {
            $wStart = strtotime($window['start']);
            $wEnd = strtotime($window['end']);
            $segments = [['start' => $wStart, 'end' => $wEnd]];

            foreach ($blocked as $block) {
                $bStart = strtotime($block['start']);
                $bEnd = strtotime($block['end']);
                $newSegments = [];

                foreach ($segments as $seg) {
                    if ($bStart >= $seg['end'] || $bEnd <= $seg['start']) {
                        $newSegments[] = $seg;
                    } else {
                        if ($seg['start'] < $bStart) {
                            $newSegments[] = ['start' => $seg['start'], 'end' => $bStart];
                        }
                        if ($seg['end'] > $bEnd) {
                            $newSegments[] = ['start' => $bEnd, 'end' => $seg['end']];
                        }
                    }
                }

                $segments = $newSegments;
            }

            foreach ($segments as $seg) {
                $result[] = ['start' => date('H:i:s', $seg['start']), 'end' => date('H:i:s', $seg['end'])];
            }
        }

        return $result;
    }

    /**
     * Get timezone offset string for RFC3339.
     */
    private function getTimezoneOffset(string $timezone): string
    {
        try {
            $tz = new \DateTimeZone($timezone);
            $now = new \DateTime('now', $tz);
            return $now->format('P');
        } catch (\Exception) {
            return '-05:00'; // Default to Eastern.
        }
    }
}
