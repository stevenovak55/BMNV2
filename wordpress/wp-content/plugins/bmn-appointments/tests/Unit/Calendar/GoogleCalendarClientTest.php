<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Calendar;

use BMN\Appointments\Calendar\GoogleCalendarClient;
use BMN\Appointments\Calendar\GoogleCalendarService;
use BMN\Appointments\Repository\StaffRepository;
use PHPUnit\Framework\TestCase;

final class GoogleCalendarClientTest extends TestCase
{
    private StaffRepository $staffRepo;
    private GoogleCalendarClient $client;

    protected function setUp(): void
    {
        $this->staffRepo = $this->createMock(StaffRepository::class);
        $this->client = new GoogleCalendarClient($this->staffRepo, 'test-client-id', 'test-client-secret');
    }

    public function testImplementsGoogleCalendarServiceInterface(): void
    {
        $this->assertInstanceOf(GoogleCalendarService::class, $this->client);
    }

    public function testIsStaffConnectedReturnsFalseWhenNoRefreshToken(): void
    {
        $this->staffRepo->method('find')->willReturn(
            (object) ['id' => 1, 'google_refresh_token' => null]
        );

        $this->assertFalse($this->client->isStaffConnected(1));
    }

    public function testIsStaffConnectedReturnsTrueWhenTokenExists(): void
    {
        $this->staffRepo->method('find')->willReturn(
            (object) ['id' => 1, 'google_refresh_token' => 'some-token']
        );

        $this->assertTrue($this->client->isStaffConnected(1));
    }

    public function testIsStaffConnectedReturnsFalseWhenStaffNotFound(): void
    {
        $this->staffRepo->method('find')->willReturn(null);

        $this->assertFalse($this->client->isStaffConnected(999));
    }

    public function testCreateEventReturnsFalseWhenNotConnected(): void
    {
        $this->staffRepo->method('find')->willReturn(
            (object) ['id' => 1, 'google_refresh_token' => null]
        );

        $result = $this->client->createEvent(1, ['summary' => 'Test']);

        $this->assertFalse($result);
    }
}
