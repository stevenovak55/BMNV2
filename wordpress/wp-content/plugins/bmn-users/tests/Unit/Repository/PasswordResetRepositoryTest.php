<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Repository;

use BMN\Users\Repository\PasswordResetRepository;
use PHPUnit\Framework\TestCase;

final class PasswordResetRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private PasswordResetRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new PasswordResetRepository($this->wpdb);
    }

    public function testCreateResetInsertsRow(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->createReset(1, 'hash123', '2026-02-16 12:00:00');

        $this->assertIsInt($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['user_id']);
        $this->assertSame('hash123', $args['token_hash']);
        $this->assertArrayHasKey('created_at', $args);
        $this->assertSame('2026-02-16 12:00:00', $args['expires_at']);
    }

    public function testCreateResetReturnsFalseOnFailure(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->createReset(1, 'hash123', '2026-02-16 12:00:00');

        $this->assertFalse($result);
    }

    public function testFindValidResetReturnsActiveToken(): void
    {
        $expected = (object) ['id' => 1, 'user_id' => 1, 'token_hash' => 'hash123'];
        $this->wpdb->get_row_result = $expected;

        $result = $this->repo->findValidReset('hash123');

        $this->assertSame($expected, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('token_hash', $sql);
        $this->assertStringContainsString('expires_at', $sql);
        $this->assertStringContainsString('used_at IS NULL', $sql);
    }

    public function testFindValidResetReturnsNullWhenExpired(): void
    {
        $this->wpdb->get_row_result = null;

        $result = $this->repo->findValidReset('expired_hash');

        $this->assertNull($result);
    }

    public function testMarkUsedUpdatesRow(): void
    {
        $result = $this->repo->markUsed(1);

        $this->assertTrue($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertArrayHasKey('used_at', $args);
    }

    public function testInvalidateForUserMarksAllAsUsed(): void
    {
        $this->wpdb->query_result = 3;

        $result = $this->repo->invalidateForUser(1);

        $this->assertSame(3, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('UPDATE', $sql);
        $this->assertStringContainsString('used_at', $sql);
        $this->assertStringContainsString('used_at IS NULL', $sql);
    }

    public function testCleanupExpiredDeletesExpiredResets(): void
    {
        $this->wpdb->query_result = 2;

        $result = $this->repo->cleanupExpired();

        $this->assertSame(2, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('DELETE FROM', $sql);
        $this->assertStringContainsString('expires_at', $sql);
    }

    public function testTableNameIsCorrectlyPrefixed(): void
    {
        $this->wpdb->get_row_result = null;
        $this->repo->findValidReset('test');

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('wp_bmn_password_resets', $sql);
    }
}
