<?php

declare(strict_types=1);

namespace BMN\Platform\Auth;

/**
 * Authentication service contract.
 *
 * Handles JWT token generation, validation, and refresh for both
 * web and mobile clients.
 */
interface AuthService
{
    /**
     * Generate an access token for the given user.
     *
     * @param int   $userId      WordPress user ID.
     * @param array $extraClaims Additional claims to include in the payload.
     *
     * @return string Encoded JWT access token.
     */
    public function generateAccessToken(int $userId, array $extraClaims = []): string;

    /**
     * Generate a refresh token for the given user.
     *
     * @param int $userId WordPress user ID.
     *
     * @return string Encoded JWT refresh token.
     */
    public function generateRefreshToken(int $userId): string;

    /**
     * Validate a JWT token and return its payload.
     *
     * @param string $token    The encoded JWT.
     * @param string $expected Expected token type ('access' or 'refresh').
     *
     * @return array{sub: int, exp: int, iss: string, type: string, iat: int} Decoded payload.
     *
     * @throws \InvalidArgumentException If the token is invalid, expired, or wrong type.
     */
    public function validateToken(string $token, string $expected = 'access'): array;

    /**
     * Issue a new access token using a valid refresh token.
     *
     * @param string $refreshToken The encoded refresh JWT.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     *
     * @throws \InvalidArgumentException If the refresh token is invalid or expired.
     */
    public function refreshToken(string $refreshToken): array;

    /**
     * Hash a password using WordPress's hashing mechanism.
     */
    public function hashPassword(string $password): string;

    /**
     * Verify a password against a stored hash.
     */
    public function verifyPassword(string $password, string $hash): bool;
}
