<?php

declare(strict_types=1);

namespace BMN\Agents\Service;

use BMN\Agents\Repository\ReferralCodeRepository;
use BMN\Agents\Repository\ReferralSignupRepository;
use RuntimeException;

/**
 * Referral code management and tracking.
 */
class ReferralService
{
    private readonly ReferralCodeRepository $codeRepo;
    private readonly ReferralSignupRepository $signupRepo;

    public function __construct(
        ReferralCodeRepository $codeRepo,
        ReferralSignupRepository $signupRepo,
    ) {
        $this->codeRepo = $codeRepo;
        $this->signupRepo = $signupRepo;
    }

    /**
     * Generate a referral code for an agent.
     *
     * @throws RuntimeException If code already exists.
     */
    public function generateCode(int $agentUserId, ?string $customCode = null): string
    {
        $code = $customCode ?? $this->generateUniqueCode();

        if ($this->codeRepo->codeExists($code)) {
            throw new RuntimeException('Referral code already exists.');
        }

        // Deactivate existing codes.
        $this->codeRepo->deactivateForAgent($agentUserId);

        $result = $this->codeRepo->create([
            'agent_user_id' => $agentUserId,
            'referral_code' => $code,
            'is_active'     => 1,
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to create referral code.');
        }

        return $code;
    }

    /**
     * Get an agent's referral info (code + URL + stats).
     */
    public function getAgentReferral(int $agentUserId): ?array
    {
        $codeRecord = $this->codeRepo->findActiveForAgent($agentUserId);

        if ($codeRecord === null) {
            return null;
        }

        $totalSignups = $this->signupRepo->countByAgent($agentUserId);

        return [
            'referral_code' => $codeRecord->referral_code,
            'referral_url'  => home_url('/register?ref=' . $codeRecord->referral_code),
            'is_active'     => (bool) $codeRecord->is_active,
            'total_signups'  => $totalSignups,
            'created_at'    => $codeRecord->created_at,
        ];
    }

    /**
     * Update an agent's referral code.
     *
     * @throws RuntimeException If code exists or not found.
     */
    public function updateCode(int $agentUserId, string $newCode): string
    {
        if ($this->codeRepo->codeExists($newCode)) {
            throw new RuntimeException('Referral code already in use.');
        }

        // Deactivate old code, create new one.
        $this->codeRepo->deactivateForAgent($agentUserId);

        $result = $this->codeRepo->create([
            'agent_user_id' => $agentUserId,
            'referral_code' => $newCode,
            'is_active'     => 1,
        ]);

        if ($result === false) {
            throw new RuntimeException('Failed to update referral code.');
        }

        return $newCode;
    }

    /**
     * Track a client signup with a referral code.
     */
    public function trackSignup(int $clientUserId, ?string $referralCode = null, string $source = 'organic', string $platform = 'web'): int|false
    {
        $agentUserId = 0;

        if ($referralCode !== null) {
            $agentUserId = $this->resolveAgentForSignup($referralCode);
            if ($agentUserId > 0) {
                $source = 'referral_link';
            }
        }

        return $this->signupRepo->create([
            'client_user_id' => $clientUserId,
            'agent_user_id'  => $agentUserId,
            'referral_code'  => $referralCode,
            'signup_source'  => $source,
            'platform'       => $platform,
        ]);
    }

    /**
     * Resolve agent user ID from a referral code.
     */
    public function resolveAgentForSignup(string $referralCode): int
    {
        $codeRecord = $this->codeRepo->findByCode($referralCode);

        return $codeRecord !== null ? (int) $codeRecord->agent_user_id : 0;
    }

    /**
     * Get detailed referral statistics for an agent.
     */
    public function getReferralStats(int $agentUserId): array
    {
        return [
            'total_signups'    => $this->signupRepo->countByAgent($agentUserId),
            'this_month'       => $this->signupRepo->countByAgentThisMonth($agentUserId),
            'by_source'        => $this->signupRepo->countBySource($agentUserId),
        ];
    }

    /**
     * Generate a unique alphanumeric code.
     */
    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while ($this->codeRepo->codeExists($code));

        return $code;
    }
}
