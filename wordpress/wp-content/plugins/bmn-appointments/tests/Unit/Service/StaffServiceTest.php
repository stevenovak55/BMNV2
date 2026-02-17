<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Service;

use BMN\Appointments\Repository\StaffRepository;
use BMN\Appointments\Repository\StaffServiceRepository;
use BMN\Appointments\Service\StaffService;
use PHPUnit\Framework\TestCase;

final class StaffServiceTest extends TestCase
{
    private StaffRepository $staffRepo;
    private StaffServiceRepository $staffServiceRepo;
    private StaffService $service;

    protected function setUp(): void
    {
        $this->staffRepo = $this->createMock(StaffRepository::class);
        $this->staffServiceRepo = $this->createMock(StaffServiceRepository::class);
        $this->service = new StaffService($this->staffRepo, $this->staffServiceRepo);
    }

    public function testGetActiveStaffReturnsFormattedList(): void
    {
        $this->staffRepo->method('findActive')->willReturn([
            (object) ['id' => 1, 'name' => 'Steve', 'email' => 'steve@test.com', 'phone' => '555-1234', 'is_primary' => 1],
            (object) ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com', 'phone' => null, 'is_primary' => 0],
        ]);

        $result = $this->service->getActiveStaff();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Steve', $result[0]['name']);
        $this->assertTrue($result[0]['is_primary']);
        $this->assertNull($result[1]['phone']);
        $this->assertFalse($result[1]['is_primary']);
    }

    public function testGetActiveStaffFiltersByType(): void
    {
        $this->staffRepo->method('findByAppointmentType')->with(5)->willReturn([
            (object) ['id' => 1, 'name' => 'Steve', 'email' => 'steve@test.com', 'phone' => null, 'is_primary' => 1],
        ]);

        $result = $this->service->getActiveStaff(5);

        $this->assertCount(1, $result);
    }

    public function testGetPrimaryStaffReturnsFormatted(): void
    {
        $this->staffRepo->method('findPrimary')->willReturn(
            (object) ['id' => 1, 'name' => 'Steve', 'email' => 'steve@test.com', 'phone' => '555-1234', 'is_primary' => 1]
        );

        $result = $this->service->getPrimaryStaff();

        $this->assertNotNull($result);
        $this->assertSame(1, $result['id']);
        $this->assertSame('Steve', $result['name']);
    }

    public function testGetPrimaryStaffReturnsNull(): void
    {
        $this->staffRepo->method('findPrimary')->willReturn(null);

        $result = $this->service->getPrimaryStaff();

        $this->assertNull($result);
    }

    public function testGetStaffForTypeReturnsFormattedList(): void
    {
        $this->staffRepo->method('findByAppointmentType')->with(3)->willReturn([
            (object) ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com', 'phone' => null, 'is_primary' => 0],
        ]);

        $result = $this->service->getStaffForType(3);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['id']);
    }
}
