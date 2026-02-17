<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Migration;

use BMN\Appointments\Migration\CreateAppointmentsTable;
use BMN\Appointments\Migration\CreateAppointmentTypesTable;
use BMN\Appointments\Migration\CreateAttendeeTable;
use BMN\Appointments\Migration\CreateAvailabilityRulesTable;
use BMN\Appointments\Migration\CreateNotificationsLogTable;
use BMN\Appointments\Migration\CreateStaffServicesTable;
use BMN\Appointments\Migration\CreateStaffTable;
use PHPUnit\Framework\TestCase;

final class MigrationsTest extends TestCase
{
    public function testCreateStaffTableExtendsBaseMigration(): void
    {
        $migration = new CreateStaffTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateStaffTableHasVersionString(): void
    {
        $migration = new CreateStaffTable();
        $this->assertSame('CreateStaffTable', $migration->getVersion());
    }

    public function testCreateAppointmentTypesTableExtendsBaseMigration(): void
    {
        $migration = new CreateAppointmentTypesTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateAppointmentTypesTableHasVersionString(): void
    {
        $migration = new CreateAppointmentTypesTable();
        $this->assertSame('CreateAppointmentTypesTable', $migration->getVersion());
    }

    public function testCreateAvailabilityRulesTableExtendsBaseMigration(): void
    {
        $migration = new CreateAvailabilityRulesTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateAvailabilityRulesTableHasVersionString(): void
    {
        $migration = new CreateAvailabilityRulesTable();
        $this->assertSame('CreateAvailabilityRulesTable', $migration->getVersion());
    }

    public function testCreateAppointmentsTableExtendsBaseMigration(): void
    {
        $migration = new CreateAppointmentsTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateAppointmentsTableHasVersionString(): void
    {
        $migration = new CreateAppointmentsTable();
        $this->assertSame('CreateAppointmentsTable', $migration->getVersion());
    }

    public function testCreateAttendeeTableExtendsBaseMigration(): void
    {
        $migration = new CreateAttendeeTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateAttendeeTableHasVersionString(): void
    {
        $migration = new CreateAttendeeTable();
        $this->assertSame('CreateAttendeeTable', $migration->getVersion());
    }

    public function testCreateStaffServicesTableExtendsBaseMigration(): void
    {
        $migration = new CreateStaffServicesTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateStaffServicesTableHasVersionString(): void
    {
        $migration = new CreateStaffServicesTable();
        $this->assertSame('CreateStaffServicesTable', $migration->getVersion());
    }

    public function testCreateNotificationsLogTableExtendsBaseMigration(): void
    {
        $migration = new CreateNotificationsLogTable();
        $this->assertInstanceOf(\BMN\Platform\Database\Migration::class, $migration);
    }

    public function testCreateNotificationsLogTableHasVersionString(): void
    {
        $migration = new CreateNotificationsLogTable();
        $this->assertSame('CreateNotificationsLogTable', $migration->getVersion());
    }
}
