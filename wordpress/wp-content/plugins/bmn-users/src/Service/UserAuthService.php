<?php

declare(strict_types=1);

namespace BMN\Users\Service;

use BMN\Platform\Auth\AuthService;
use BMN\Platform\Auth\JwtAuthService;
use BMN\Platform\Email\EmailService;
use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Repository\PasswordResetRepository;
use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Repository\TokenRevocationRepository;
use InvalidArgumentException;
use RuntimeException;

/**
 * Orchestrates authentication flows using the platform's AuthService.
 */
class UserAuthService
{
    /** Rate limiting: max attempts per window. */
    private const MAX_LOGIN_ATTEMPTS = 20;

    /** Rate limiting: lockout duration in seconds (5 minutes). */
    private const LOCKOUT_DURATION = 300;

    /** Rate limiting: window duration in seconds (15 minutes). */
    private const WINDOW_DURATION = 900;

    /** Password reset: token validity in seconds (1 hour). */
    private const RESET_TOKEN_EXPIRY = 3600;

    private readonly AuthService $authService;
    private readonly EmailService $emailService;
    private readonly TokenRevocationRepository $tokenRepo;
    private readonly PasswordResetRepository $resetRepo;

    public function __construct(
        AuthService $authService,
        EmailService $emailService,
        TokenRevocationRepository $tokenRepo,
        PasswordResetRepository $resetRepo,
    ) {
        $this->authService = $authService;
        $this->emailService = $emailService;
        $this->tokenRepo = $tokenRepo;
        $this->resetRepo = $resetRepo;
    }

    /**
     * Authenticate a user with email and password.
     *
     * @return array{user: array, access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws RuntimeException On invalid credentials, rate limiting, or user not found.
     */
    public function login(string $email, string $password): array
    {
        $identifier = md5($email . $this->getClientIp());
        $this->checkLoginRateLimit($identifier);

        $user = wp_authenticate($email, $password);

        if (is_wp_error($user)) {
            $this->recordLoginAttempt($identifier);
            throw new RuntimeException('Invalid email or password.');
        }

        // Clear rate limit on success.
        delete_transient('bmn_auth_login_' . $identifier);

        return $this->buildAuthResponse($user);
    }

    /**
     * Register a new user account.
     *
     * @return array{user: array, access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws RuntimeException If registration fails.
     */
    public function register(
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        string $phone = '',
    ): array {
        $existingUser = get_user_by('email', $email);

        if ($existingUser !== false) {
            throw new RuntimeException('An account with this email already exists.');
        }

        $userId = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'display_name' => trim("{$firstName} {$lastName}"),
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException('Failed to create account.');
        }

        if ($phone !== '') {
            update_user_meta($userId, 'phone', sanitize_text_field($phone));
        }

        $user = get_userdata($userId);

        if ($user === false) {
            throw new RuntimeException('Failed to retrieve created account.');
        }

        return $this->buildAuthResponse($user);
    }

    /**
     * Issue new tokens using a refresh token.
     *
     * @return array{user: array, access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws RuntimeException If the token is revoked or invalid.
     */
    public function refreshToken(string $refreshToken): array
    {
        // Check if the refresh token has been revoked.
        $tokenHash = hash('sha256', $refreshToken);

        if ($this->tokenRepo->isRevoked($tokenHash)) {
            throw new RuntimeException('Token has been revoked.');
        }

        try {
            $tokens = $this->authService->refreshToken($refreshToken);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage());
        }

        $payload = $this->authService->validateToken($refreshToken, 'refresh');
        $user = get_userdata($payload['sub']);

        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        return [
            'user'          => UserProfileFormatter::format($user),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
        ];
    }

    /**
     * Revoke a specific token (logout).
     */
    public function logout(string $token, int $userId): void
    {
        $tokenHash = hash('sha256', $token);

        try {
            $payload = $this->authService->validateToken($token, 'access');
            $expiresAt = date('Y-m-d H:i:s', $payload['exp']);
        } catch (InvalidArgumentException) {
            $expiresAt = date('Y-m-d H:i:s', (int) current_time('timestamp') + JwtAuthService::ACCESS_TOKEN_EXPIRY);
        }

        $this->tokenRepo->revokeToken($tokenHash, $userId, $expiresAt);
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllTokens(int $userId): void
    {
        $this->tokenRepo->revokeAllForUser($userId);
    }

    /**
     * Initiate a password reset flow.
     *
     * Always returns true to prevent email enumeration.
     */
    public function forgotPassword(string $email): bool
    {
        $user = get_user_by('email', $email);

        if ($user === false) {
            return true; // No email enumeration.
        }

        $userId = (int) $user->ID;

        // Invalidate any existing resets.
        $this->resetRepo->invalidateForUser($userId);

        // Generate a random token and store its hash.
        $rawToken = wp_generate_password(64, false);
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', (int) current_time('timestamp') + self::RESET_TOKEN_EXPIRY);

        $this->resetRepo->createReset($userId, $tokenHash, $expiresAt);

        // Send reset email.
        $resetUrl = home_url("/reset-password?token={$rawToken}");
        $firstName = $user->first_name ?? 'there';

        $this->emailService->sendTemplate(
            $user->user_email,
            'Reset Your Password - BMN Boston',
            '<p>Hi {{first_name}},</p>'
            . '<p>We received a request to reset your password. Click the link below to set a new password:</p>'
            . '<p><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background-color:#2b6cb0;color:#ffffff;text-decoration:none;border-radius:4px;">Reset Password</a></p>'
            . '<p>This link expires in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>',
            [
                'first_name' => $firstName,
                'reset_url'  => $resetUrl,
            ],
            ['context' => 'general']
        );

        return true;
    }

    /**
     * Complete a password reset using a valid token.
     *
     * @throws RuntimeException If the token is invalid or expired.
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $tokenHash = hash('sha256', $token);
        $reset = $this->resetRepo->findValidReset($tokenHash);

        if ($reset === null) {
            throw new RuntimeException('Invalid or expired reset token.');
        }

        // Update the user's password.
        wp_set_password($newPassword, (int) $reset->user_id);

        // Mark the reset token as used.
        $this->resetRepo->markUsed((int) $reset->id);

        return true;
    }

    /**
     * Delete a user account and all associated data.
     *
     * @throws RuntimeException If the user is not found.
     */
    public function deleteAccount(int $userId, FavoriteRepository $favoriteRepo, SavedSearchRepository $savedSearchRepo): bool
    {
        $user = get_userdata($userId);

        if ($user === false) {
            throw new RuntimeException('User not found.');
        }

        // Delete all user data from custom tables.
        $favoriteRepo->removeAllForUser($userId);
        $savedSearchRepo->removeAllForUser($userId);
        $this->tokenRepo->revokeAllForUser($userId);
        $this->resetRepo->invalidateForUser($userId);

        // Delete the WordPress user.
        $result = wp_delete_user($userId);

        return $result !== false;
    }

    /**
     * Check rate limiting for login attempts.
     *
     * @throws RuntimeException If the user is rate-limited.
     */
    public function checkLoginRateLimit(string $identifier): void
    {
        $key = 'bmn_auth_login_' . $identifier;
        $data = get_transient($key);

        if ($data === false) {
            return;
        }

        $attempts = (int) ($data['attempts'] ?? 0);
        $lockedUntil = (int) ($data['locked_until'] ?? 0);

        if ($lockedUntil > 0 && $lockedUntil > (int) current_time('timestamp')) {
            throw new RuntimeException('Too many login attempts. Please try again later.');
        }

        if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
            // Set lockout.
            $data['locked_until'] = (int) current_time('timestamp') + self::LOCKOUT_DURATION;
            set_transient($key, $data, self::WINDOW_DURATION);

            throw new RuntimeException('Too many login attempts. Please try again later.');
        }
    }

    /**
     * Record a failed login attempt.
     */
    public function recordLoginAttempt(string $identifier): void
    {
        $key = 'bmn_auth_login_' . $identifier;
        $data = get_transient($key);

        if ($data === false) {
            $data = ['attempts' => 0, 'locked_until' => 0];
        }

        $data['attempts'] = ((int) ($data['attempts'] ?? 0)) + 1;

        set_transient($key, $data, self::WINDOW_DURATION);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Build the standard auth response with user profile and tokens.
     */
    private function buildAuthResponse(object $user): array
    {
        $userId = (int) $user->ID;

        return [
            'user'          => UserProfileFormatter::format($user),
            'access_token'  => $this->authService->generateAccessToken($userId),
            'refresh_token' => $this->authService->generateRefreshToken($userId),
            'expires_in'    => JwtAuthService::ACCESS_TOKEN_EXPIRY,
        ];
    }

    /**
     * Get the client IP address for rate limiting.
     */
    private function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
