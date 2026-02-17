<?php

declare(strict_types=1);

namespace BMN\Appointments\Calendar;

/**
 * Interface for Google Calendar integration.
 *
 * Abstracts calendar operations so implementations can be swapped
 * between the real GoogleCalendarClient and the NullCalendarService.
 */
interface GoogleCalendarService
{
    /**
     * Check if a staff member has a connected Google Calendar.
     */
    public function isStaffConnected(int $staffId): bool;

    /**
     * Create a calendar event for a staff member.
     *
     * @param int   $staffId   Staff member ID.
     * @param array $eventData Event data: summary, description, start (RFC3339), end (RFC3339), attendees.
     * @return array|false Created event data with 'id' key, or false on failure.
     */
    public function createEvent(int $staffId, array $eventData): array|false;

    /**
     * Update an existing calendar event.
     *
     * @param int    $staffId   Staff member ID.
     * @param string $eventId   Google Calendar event ID.
     * @param array  $eventData Updated event data.
     * @return array|false Updated event data, or false on failure.
     */
    public function updateEvent(int $staffId, string $eventId, array $eventData): array|false;

    /**
     * Delete a calendar event.
     *
     * @param int    $staffId Staff member ID.
     * @param string $eventId Google Calendar event ID.
     * @return bool True if deleted or not found.
     */
    public function deleteEvent(int $staffId, string $eventId): bool;

    /**
     * Get free/busy information for a staff member.
     *
     * @param int    $staffId       Staff member ID.
     * @param string $startDatetime RFC3339 start datetime.
     * @param string $endDatetime   RFC3339 end datetime.
     * @return array Array of busy periods: [['start' => string, 'end' => string], ...].
     */
    public function getFreeBusy(int $staffId, string $startDatetime, string $endDatetime): array;
}
