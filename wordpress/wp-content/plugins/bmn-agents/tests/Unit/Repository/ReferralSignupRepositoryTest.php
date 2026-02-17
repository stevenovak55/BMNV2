<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\ReferralSignupRepository;
use PHPUnit\Framework\TestCase;

final class ReferralSignupRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ReferralSignupRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new ReferralSignupRepository($this->wpdb);
    }

    public function testCreateInsertsWithCreatedAt(): void
    {
        $result = $this->repo->create([
            'client_user_id' => 100,
            'agent_user_id'  => 10,
            'referral_code'  => 'ABC123',
            'signup_source'  => 'referral_link',
            'platform'       => 'web',
        ]);

        $this->assertIsInt($result);
        $query = $this->wpdb->queries[0];
        $this->assertArrayHasKey('created_at', $query['args']);
    }

    public function testFindByClientQueriesCorrectly(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'client_user_id' => 100, 'agent_user_id' => 10,
        ];

        $result = $this->repo->findByClient(100);

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('client_user_id', $sql);
    }

    public function testCountByAgentQueriesCorrectly(): void
    {
        $this->wpdb->get_var_result = '15';

        $result = $this->repo->countByAgent(10);

        $this->assertSame(15, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
    }

    public function testCountByAgentThisMonthFiltersDate(): void
    {
        $this->wpdb->get_var_result = '3';

        $result = $this->repo->countByAgentThisMonth(10);

        $this->assertSame(3, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('created_at >=', $sql);
    }

    public function testCountBySourceReturnsGrouped(): void
    {
        $this->wpdb->get_results_result = [
            (object) ['signup_source' => 'referral_link', 'cnt' => '10'],
            (object) ['signup_source' => 'organic', 'cnt' => '5'],
        ];

        $result = $this->repo->countBySource(10);

        $this->assertSame(10, $result['referral_link']);
        $this->assertSame(5, $result['organic']);
    }

    public function testCountBySourceReturnsEmptyArray(): void
    {
        $this->wpdb->get_results_result = [];

        $result = $this->repo->countBySource(10);

        $this->assertSame([], $result);
    }

    public function testFindByClientReturnsNull(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByClient(999);

        $this->assertNull($result);
    }
}
