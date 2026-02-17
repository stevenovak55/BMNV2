<?php

declare(strict_types=1);

namespace BMN\Appointments\Tests\Unit\Repository;

use BMN\Appointments\Repository\AppointmentTypeRepository;
use PHPUnit\Framework\TestCase;

final class AppointmentTypeRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private AppointmentTypeRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new AppointmentTypeRepository($this->wpdb);
    }

    public function testFindActiveReturnsActiveTypes(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'name' => 'Showing', 'is_active' => 1],
            (object) ['id' => 2, 'name' => 'Consultation', 'is_active' => 1],
        ];

        $result = $this->repo->findActive();

        $this->assertCount(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_active = 1', $sql);
        $this->assertStringContainsString('ORDER BY sort_order', $sql);
    }

    public function testFindActiveReturnsEmptyArray(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findActive();

        $this->assertSame([], $result);
    }

    public function testFindBySlugReturnsType(): void
    {
        $expected = (object) ['id' => 1, 'slug' => 'showing', 'name' => 'Property Showing'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findBySlug('showing');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('slug', $sql);
    }

    public function testFindBySlugReturnsNull(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findBySlug('nonexistent');

        $this->assertNull($result);
    }

    public function testFindByStaffJoinsStaffServices(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['id' => 1, 'name' => 'Showing'],
        ];

        $result = $this->repo->findByStaff(3);

        $this->assertCount(1, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('INNER JOIN', $sql);
        $this->assertStringContainsString('bmn_staff_services', $sql);
        $this->assertStringContainsString('staff_id', $sql);
    }

    public function testFindByStaffReturnsEmpty(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->findByStaff(999);

        $this->assertSame([], $result);
    }

    public function testFindByIdDelegatesToBaseFind(): void
    {
        $expected = (object) ['id' => 1, 'name' => 'Showing'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->find(1);

        $this->assertSame($expected, $result);
    }

    public function testCreateDelegatesToBaseCreate(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->create([
            'name'             => 'New Type',
            'slug'             => 'new-type',
            'duration_minutes' => 30,
        ]);

        $this->assertIsInt($result);
    }
}
