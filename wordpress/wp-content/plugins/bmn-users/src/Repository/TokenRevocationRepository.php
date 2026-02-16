<?php

declare(strict_types=1);

namespace BMN\Users\Repository;

use wpdb;

/**
 * Repository for revoked JWT tokens (bmn_revoked_tokens table).
 *
 * Standalone class (no base Repository) because it uses revoked_at/expires_at
 * instead of created_at/updated_at timestamps.
 */
class TokenRevocationRepository
{
    private readonly wpdb $wpdb;
    private readonly string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bmn_revoked_tokens';
    }

    public function revokeToken(string $tokenHash, int $userId, string $expiresAt): bool
    {
        $result = $this->wpdb->insert($this->table, [
            'token_hash' => $tokenHash,
            'user_id'    => $userId,
            'revoked_at' => current_time('mysql'),
            'expires_at' => $expiresAt,
        ]);

        return $result !== false;
    }

    public function isRevoked(string $tokenHash): bool
    {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE token_hash = %s",
            $tokenHash
        );

        return (int) $this->wpdb->get_var($sql) > 0;
    }

    public function revokeAllForUser(int $userId): int
    {
        $result = $this->wpdb->delete($this->table, ['user_id' => $userId]);

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Remove expired revoked tokens (they're no longer needed after natural expiry).
     */
    public function cleanupExpired(): int
    {
        $now = current_time('mysql');

        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->table} WHERE expires_at < %s",
            $now
        );

        $result = $this->wpdb->query($sql);

        return $result !== false ? (int) $result : 0;
    }
}
