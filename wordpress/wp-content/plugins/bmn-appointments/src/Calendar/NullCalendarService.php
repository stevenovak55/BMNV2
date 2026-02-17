<?php

declare(strict_types=1);

namespace BMN\Appointments\Calendar;

/**
 * Null implementation of GoogleCalendarService.
 *
 * Returns safe defaults for all operations. Used as the default
 * implementation until Google Calendar is connected. Swappable to
 * GoogleCalendarClient later via the service container.
 */
final class NullCalendarService implements GoogleCalendarService
{
    public function isStaffConnected(int $staffId): bool
    {
        return false;
    }

    public function createEvent(int $staffId, array $eventData): array|false
    {
        return false;
    }

    public function updateEvent(int $staffId, string $eventId, array $eventData): array|false
    {
        return false;
    }

    public function deleteEvent(int $staffId, string $eventId): bool
    {
        return true;
    }

    public function getFreeBusy(int $staffId, string $startDatetime, string $endDatetime): array
    {
        return [];
    }
}
