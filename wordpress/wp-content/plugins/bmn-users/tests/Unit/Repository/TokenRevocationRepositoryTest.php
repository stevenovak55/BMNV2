<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Repository;

use BMN\Users\Repository\TokenRevocationRepository;
use PHPUnit\Framework\TestCase;

final class TokenRevocationRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private TokenRevocationRepository $repo;

    protected function setUp(): void
    {
        $this->wpdb = new \wpdb();
        $this->repo = new TokenRevocationRepository($this->wpdb);
    }

    public function testRevokeTokenInsertsRow(): void
    {
        $this->wpdb->insert_result = true;

        $result = $this->repo->revokeToken('abc123hash', 1, '2026-03-16 00:00:00');

        $this->assertTrue($result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame('abc123hash', $args['token_hash']);
        $this->assertSame(1, $args['user_id']);
        $this->assertArrayHasKey('revoked_at', $args);
        $this->assertSame('2026-03-16 00:00:00', $args['expires_at']);
    }

    public function testRevokeTokenReturnsFalseOnFailure(): void
    {
        $this->wpdb->insert_result = false;

        $result = $this->repo->revokeToken('abc123hash', 1, '2026-03-16 00:00:00');

        $this->assertFalse($result);
    }

    public function testIsRevokedReturnsTrueWhenFound(): void
    {
        $this->wpdb->get_var_result = '1';

        $this->assertTrue($this->repo->isRevoked('abc123hash'));
    }

    public function testIsRevokedReturnsFalseWhenNotFound(): void
    {
        $this->wpdb->get_var_result = '0';

        $this->assertFalse($this->repo->isRevoked('nonexistent'));
    }

    public function testRevokeAllForUserDeletesByUserId(): void
    {
        $result = $this->repo->revokeAllForUser(1);

        $this->assertSame(1, $result);
        $args = $this->wpdb->queries[0]['args'];
        $this->assertSame(1, $args['user_id']);
    }

    public function testCleanupExpiredDeletesExpiredTokens(): void
    {
        $this->wpdb->query_result = 5;

        $result = $this->repo->cleanupExpired();

        $this->assertSame(5, $result);
        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('DELETE FROM', $sql);
        $this->assertStringContainsString('expires_at', $sql);
    }

    public function testCleanupExpiredReturnsZeroWhenNone(): void
    {
        $this->wpdb->query_result = 0;

        $result = $this->repo->cleanupExpired();

        $this->assertSame(0, $result);
    }

    public function testTableNameIsCorrectlyPrefixed(): void
    {
        $this->wpdb->get_var_result = '0';
        $this->repo->isRevoked('test');

        $sql = $this->wpdb->queries[0]['sql'];
        $this->assertStringContainsString('wp_bmn_revoked_tokens', $sql);
    }
}
