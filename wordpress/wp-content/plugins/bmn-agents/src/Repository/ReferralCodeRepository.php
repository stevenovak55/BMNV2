<?php

declare(strict_types=1);

namespace BMN\Agents\Repository;

use BMN\Platform\Database\Repository;

/**
 * Repository for bmn_agent_referral_codes.
 */
class ReferralCodeRepository extends Repository
{
    protected function getTableName(): string
    {
        return 'bmn_agent_referral_codes';
    }

    /**
     * Find the active referral code for an agent.
     */
    public function findActiveForAgent(int $agentUserId): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE agent_user_id = %d AND is_active = 1
                 ORDER BY created_at DESC LIMIT 1",
                $agentUserId
            )
        );

        return $result ?: null;
    }

    /**
     * Find by referral code string.
     */
    public function findByCode(string $code): ?object
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE referral_code = %s AND is_active = 1
                 LIMIT 1",
                $code
            )
        );

        return $result ?: null;
    }

    /**
     * Check if a code already exists.
     */
    public function codeExists(string $code): bool
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE referral_code = %s",
                $code
            )
        ) > 0;
    }

    /**
     * Deactivate all codes for an agent.
     */
    public function deactivateForAgent(int $agentUserId): bool
    {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table} SET is_active = 0, updated_at = %s WHERE agent_user_id = %d AND is_active = 1",
                current_time('mysql'),
                $agentUserId
            )
        );

        return $result !== false;
    }
}
