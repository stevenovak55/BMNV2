<?php

declare(strict_types=1);

namespace BMN\Appointments\Service;

use BMN\Appointments\Repository\StaffRepository;
use BMN\Appointments\Repository\StaffServiceRepository;

/**
 * Service for staff-related operations.
 */
class StaffService
{
    private readonly StaffRepository $staffRepo;
    private readonly StaffServiceRepository $staffServiceRepo;

    public function __construct(StaffRepository $staffRepo, StaffServiceRepository $staffServiceRepo)
    {
        $this->staffRepo = $staffRepo;
        $this->staffServiceRepo = $staffServiceRepo;
    }

    /**
     * Get all active staff, optionally filtered by appointment type.
     *
     * @return array[]
     */
    public function getActiveStaff(?int $typeId = null): array
    {
        $staff = ($typeId !== null)
            ? $this->staffRepo->findByAppointmentType($typeId)
            : $this->staffRepo->findActive();

        return array_map([$this, 'formatStaff'], $staff);
    }

    /**
     * Get the primary staff member.
     */
    public function getPrimaryStaff(): ?array
    {
        $staff = $this->staffRepo->findPrimary();

        return $staff !== null ? $this->formatStaff($staff) : null;
    }

    /**
     * Get staff members who offer a specific appointment type.
     *
     * @return array[]
     */
    public function getStaffForType(int $typeId): array
    {
        $staff = $this->staffRepo->findByAppointmentType($typeId);

        return array_map([$this, 'formatStaff'], $staff);
    }

    /**
     * Format a staff record for API output.
     */
    private function formatStaff(object $staff): array
    {
        return [
            'id'         => (int) $staff->id,
            'name'       => $staff->name,
            'email'      => $staff->email,
            'phone'      => $staff->phone ?? null,
            'is_primary' => (bool) ($staff->is_primary ?? false),
        ];
    }
}
