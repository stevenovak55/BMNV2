<?php

declare(strict_types=1);

namespace BMN\Users\Repository;

use wpdb;

/**
 * Repository for password reset tokens (bmn_password_resets table).
 *
 * Standalone class (no base Repository) because it uses custom timestamp columns.
 */
class PasswordResetRepository
{
    private readonly wpdb $wpdb;
    private readonly string $table;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'bmn_password_resets';
    }

    /**
     * @return int|false Inserted row ID, or false on failure.
     */
    public function createReset(int $userId, string $tokenHash, string $expiresAt): int|false
    {
        $result = $this->wpdb->insert($this->table, [
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'created_at' => current_time('mysql'),
            'expires_at' => $expiresAt,
        ]);

        return $result !== false ? (int) $this->wpdb->insert_id : false;
    }

    /**
     * Find a valid (not expired, not used) reset token.
     */
    public function findValidReset(string $tokenHash): ?object
    {
        $now = current_time('mysql');

        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE token_hash = %s AND expires_at > %s AND used_at IS NULL LIMIT 1",
            $tokenHash,
            $now
        );

        $result = $this->wpdb->get_row($sql);

        return $result ?: null;
    }

    public function markUsed(int $id): bool
    {
        $result = $this->wpdb->update(
            $this->table,
            ['used_at' => current_time('mysql')],
            ['id' => $id]
        );

        return $result !== false;
    }

    /**
     * Invalidate all pending resets for a user (e.g. when a new reset is requested).
     */
    public function invalidateForUser(int $userId): int
    {
        $now = current_time('mysql');

        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table} SET used_at = %s WHERE user_id = %d AND used_at IS NULL",
            $now,
            $userId
        );

        $result = $this->wpdb->query($sql);

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Remove expired reset tokens.
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
