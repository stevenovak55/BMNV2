<?php

declare(strict_types=1);

namespace BMN\Platform\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;

/**
 * JWT-based authentication service using firebase/php-jwt.
 *
 * Token lifetimes and secret key sourcing mirror the v1 system so that
 * behaviour is familiar during the migration period.
 */
final class JwtAuthService implements AuthService
{
    /** Access token lifetime: 30 days in seconds. */
    public const ACCESS_TOKEN_EXPIRY = 2_592_000;

    /** Refresh token lifetime: 30 days in seconds. */
    public const REFRESH_TOKEN_EXPIRY = 2_592_000;

    /** JWT signing algorithm. */
    private const ALGORITHM = 'HS256';

    /** Cached secret key (resolved once per request). */
    private ?string $secret = null;

    /**
     * @param string|null $secret Override the secret key (useful for testing).
     */
    public function __construct(?string $secret = null)
    {
        if ($secret !== null) {
            $this->secret = $secret;
        }
    }

    // ------------------------------------------------------------------
    // Token generation
    // ------------------------------------------------------------------

    /** @inheritDoc */
    public function generateAccessToken(int $userId, array $extraClaims = []): string
    {
        $now = (int) current_time('timestamp');

        $payload = array_merge($extraClaims, [
            'sub'  => $userId,
            'iat'  => $now,
            'exp'  => $now + self::ACCESS_TOKEN_EXPIRY,
            'iss'  => $this->getIssuer(),
            'type' => 'access',
        ]);

        return JWT::encode($payload, $this->getSecret(), self::ALGORITHM);
    }

    /** @inheritDoc */
    public function generateRefreshToken(int $userId): string
    {
        $now = (int) current_time('timestamp');

        $payload = [
            'sub'  => $userId,
            'iat'  => $now,
            'exp'  => $now + self::REFRESH_TOKEN_EXPIRY,
            'iss'  => $this->getIssuer(),
            'type' => 'refresh',
        ];

        return JWT::encode($payload, $this->getSecret(), self::ALGORITHM);
    }

    // ------------------------------------------------------------------
    // Token validation
    // ------------------------------------------------------------------

    /** @inheritDoc */
    public function validateToken(string $token, string $expected = 'access'): array
    {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException('Token must not be empty.');
        }

        try {
            $decoded = JWT::decode($token, new Key($this->getSecret(), self::ALGORITHM));
        } catch (ExpiredException $e) {
            throw new InvalidArgumentException('Token has expired.', 0, $e);
        } catch (SignatureInvalidException $e) {
            throw new InvalidArgumentException('Token signature is invalid.', 0, $e);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Token is malformed or invalid: ' . $e->getMessage(), 0, $e);
        }

        $payload = (array) $decoded;

        // Verify issuer.
        if (($payload['iss'] ?? '') !== $this->getIssuer()) {
            throw new InvalidArgumentException('Token issuer mismatch.');
        }

        // Verify token type.
        if (($payload['type'] ?? '') !== $expected) {
            throw new InvalidArgumentException(
                sprintf('Expected %s token, got %s.', $expected, $payload['type'] ?? 'unknown')
            );
        }

        // Ensure required claims exist.
        if (! isset($payload['sub']) || (int) $payload['sub'] <= 0) {
            throw new InvalidArgumentException('Token missing valid subject (user ID).');
        }

        // Normalise types for the return.
        return [
            'sub'  => (int) $payload['sub'],
            'exp'  => (int) ($payload['exp'] ?? 0),
            'iss'  => (string) ($payload['iss'] ?? ''),
            'type' => (string) ($payload['type'] ?? ''),
            'iat'  => (int) ($payload['iat'] ?? 0),
        ];
    }

    // ------------------------------------------------------------------
    // Refresh flow
    // ------------------------------------------------------------------

    /** @inheritDoc */
    public function refreshToken(string $refreshToken): array
    {
        $payload = $this->validateToken($refreshToken, 'refresh');

        $userId = $payload['sub'];

        return [
            'access_token'  => $this->generateAccessToken($userId),
            'refresh_token' => $this->generateRefreshToken($userId),
            'expires_in'    => self::ACCESS_TOKEN_EXPIRY,
        ];
    }

    // ------------------------------------------------------------------
    // Password helpers
    // ------------------------------------------------------------------

    /** @inheritDoc */
    public function hashPassword(string $password): string
    {
        return wp_hash_password($password);
    }

    /** @inheritDoc */
    public function verifyPassword(string $password, string $hash): bool
    {
        return wp_check_password($password, $hash);
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /**
     * Resolve the JWT secret key.
     *
     * Priority:
     *   1. Constructor-injected value (for testing)
     *   2. BMN_JWT_SECRET constant (from wp-config.php)
     *   3. bmn_jwt_secret WordPress option (database)
     *
     * @throws \RuntimeException If no secret can be resolved.
     */
    private function getSecret(): string
    {
        if ($this->secret !== null) {
            return $this->secret;
        }

        // Priority 1: PHP constant (most secure — set in wp-config.php).
        if (defined('BMN_JWT_SECRET') && BMN_JWT_SECRET !== '') {
            $this->secret = (string) BMN_JWT_SECRET;
            return $this->secret;
        }

        // Priority 2: Database option (fallback).
        $option = get_option('bmn_jwt_secret', '');
        if (is_string($option) && $option !== '') {
            $this->secret = $option;
            return $this->secret;
        }

        throw new \RuntimeException(
            'JWT secret key is not configured. Define BMN_JWT_SECRET in wp-config.php or set the bmn_jwt_secret option.'
        );
    }

    /**
     * Return the token issuer (site URL).
     */
    private function getIssuer(): string
    {
        return get_bloginfo('url');
    }
}
