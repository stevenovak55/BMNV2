<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\StaffRepository;
use PHPUnit\Framework\TestCase;

final class StaffRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private StaffRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new StaffRepository($this->wpdb);
    }

    public function testFindPrimaryReturnsStaff(): void
    {
        $expected = (object) ['id' => 1, 'name' => 'Steve', 'is_primary' => 1, 'is_active' => 1];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findPrimary();

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_primary = 1', $sql);
        $this->assertStringContainsString('is_active = 1', $sql);
    }

    public function testFindPrimaryReturnsNullWhenNone(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findPrimary();

        $this->assertNull($result);
    }

    public function testFindActiveReturnsStaffList(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'name' => 'Steve', 'is_active' => 1],
            (object) ['id' => 2, 'name' => 'Jane', 'is_active' => 1],
        ];

        $result = $this->repo->findActive();

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_active = 1', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
    }

    public function testFindByUserIdReturnsStaff(): void
    {
        $expected = (object) ['id' => 1, 'user_id' => 42, 'name' => 'Steve'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findByUserId(42);

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('user_id', $sql);
    }

    public function testFindByUserIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByUserId(999);

        $this->assertNull($result);
    }

    public function testFindByAppointmentTypeJoinsStaffServices(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'name' => 'Steve'],
        ];

        $result = $this->repo->findByAppointmentType(5);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('bmn_staff_services', $sql);
        $this->assertStringContainsString('appointment_type_id', $sql);
    }

    public function testUpdateGoogleTokensDelegatesToUpdate(): void
    {
        $this->repo->updateGoogleTokens(1, 'access-tok', 'refresh-tok', '2026-12-31 23:59:59');

        $this->assertCount(1, $this->wpdb->queries);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame('access-tok', $args['google_access_token']);
        $this->assertSame('refresh-tok', $args['google_refresh_token']);
    }

    public function testFindByIdDelegatesToBaseFind(): void
    {
        $expected = (object) ['id' => 1, 'name' => 'Steve'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->find(1);

        $this->assertSame($expected, $result);
    }
}
