<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\StaffServiceRepository;
use PHPUnit\Framework\TestCase;

final class StaffServiceRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private StaffServiceRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new StaffServiceRepository($this->wpdb);
    }

    public function testTimestampsAreDisabled(): void
    {
        $this->wpdb->insert_result = true;

        $this->repo->linkStaffToType(1, 5);

        $args = $this->wpdb->queries[0]['args'];
        $this->assertArrayHasKey('created_at', $args);
        $this->assertArrayNotHasKey('updated_at', $args);
    }

    public function testFindByStaffReturnsLinks(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'staff_id' => 1, 'appointment_type_id' => 5],
        ];

        $result = $this->repo->findByStaff(1);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('staff_id', $sql);
    }

    public function testFindByTypeReturnsLinks(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'staff_id' => 1, 'appointment_type_id' => 5],
            (object) ['id' => 2, 'staff_id' => 2, 'appointment_type_id' => 5],
        ];

        $result = $this->repo->findByType(5);

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('appointment_type_id', $sql);
    }

    public function testLinkStaffToTypeInsertsRow(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->linkStaffToType(1, 5);

        $this->assertIsInt($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['staff_id']);
        $this->assertSame(5, $args['appointment_type_id']);
    }

    public function testLinkStaffToTypeReturnsFalseOnDuplicate(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->linkStaffToType(1, 5);

        $this->assertFalse($result);
    }

    public function testUnlinkStaffFromTypeDeletesRow(): void
    {
        $result = $this->repo->unlinkStaffFromType(1, 5);

        $this->assertTrue($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['staff_id']);
        $this->assertSame(5, $args['appointment_type_id']);
    }

    public function testFindByStaffReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByStaff(999);

        $this->assertSame([], $result);
    }

    public function testFindByTypeReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByType(999);

        $this->assertSame([], $result);
    }
}
