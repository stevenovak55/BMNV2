<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Repository;

use BMN\Agents\Repository\ReferralCodeRepository;
use PHPUnit\Framework\TestCase;

final class ReferralCodeRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private ReferralCodeRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new ReferralCodeRepository($this->wpdb);
    }

    public function testFindActiveForAgentQueriesCorrectly(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'agent_user_id' => 10, 'referral_code' => 'ABC123', 'is_active' => 1,
        ];

        $result = $this->repo->findActiveForAgent(10);

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('agent_user_id', $sql);
        $this->assertStringContainsString('is_active = 1', $sql);
    }

    public function testFindActiveForAgentReturnsNull(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findActiveForAgent(999);

        $this->assertNull($result);
    }

    public function testFindByCodeQueriesCorrectly(): void
    {
        $this->wpdb->get_row_result = (object) [
            'id' => 1, 'referral_code' => 'ABC123', 'agent_user_id' => 10,
        ];

        $result = $this->repo->findByCode('ABC123');

        $this->assertNotNull($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('referral_code', $sql);
        $this->assertStringContainsString('is_active = 1', $sql);
    }

    public function testCodeExistsReturnsTrueWhenExists(): void
    {
        $this->wpdb->get_var_result = '1';

        $result = $this->repo->codeExists('ABC123');

        $this->assertTrue($result);
    }

    public function testCodeExistsReturnsFalseWhenNotExists(): void
    {
        $this->wpdb->get_var_result = '0';

        $result = $this->repo->codeExists('NONEXISTENT');

        $this->assertFalse($result);
    }

    public function testDeactivateForAgentUpdatesRecords(): void
    {
        $this->wpdb->query_result = 1;

        $result = $this->repo->deactivateForAgent(10);

        $this->assertTrue($result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('is_active = 0', $sql);
        $this->assertStringContainsString('agent_user_id', $sql);
    }

    public function testCreateInsertsWithTimestamps(): void
    {
        $result = $this->repo->create([
            'agent_user_id' => 10,
            'referral_code' => 'TEST123',
            'is_active'     => 1,
        ]);

        $this->assertIsInt($result);
        $query = $this->wpdb->queries[0];
        $this->assertArrayHasKey('created_at', $query['args']);
    }

    public function testFindByCodeReturnsNull(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findByCode('NONEXISTENT');

        $this->assertNull($result);
    }
}
