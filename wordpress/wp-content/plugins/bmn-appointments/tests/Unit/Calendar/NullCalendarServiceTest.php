<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Calendar;

use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Calendar\NullCalendarService;
use PHPUnit\Framework\TestCase;

final class NullCalendarServiceTest extends TestCase
{
    private NullCalendarService $service;

    protected function setUp(): void
    {
        $this->service = new NullCalendarService();
    }

    public function testImplementsGoogleCalendarServiceInterface(): void
    {
        $this->assertInstanceOf(GoogleCalendarService::class, $this->service);
    }

    public function testIsStaffConnectedReturnsFalse(): void
    {
        $this->assertFalse($this->service->isStaffConnected(1));
    }

    public function testCreateEventReturnsFalse(): void
    {
        $result = $this->service->createEvent(1, ['summary' => 'Test']);
        $this->assertFalse($result);
    }

    public function testUpdateEventReturnsFalse(): void
    {
        $result = $this->service->updateEvent(1, 'event-id', ['summary' => 'Updated']);
        $this->assertFalse($result);
    }

    public function testDeleteEventReturnsTrue(): void
    {
        $result = $this->service->deleteEvent(1, 'event-id');
        $this->assertTrue($result);
    }

    public function testGetFreeBusyReturnsEmptyArray(): void
    {
        $result = $this->service->getFreeBusy(1, '2026-03-01T00:00:00-05:00', '2026-03-02T00:00:00-05:00');
        $this->assertSame([], $result);
    }
}
