<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Auth;

use BMN\Platform\Auth\JwtAuthService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for JwtAuthService.
 *
 * These tests verify JWT generation, validation, refresh flow, and
 * password hashing/verification using the firebase/php-jwt library.
 */
class JwtAuthServiceTest extends TestCase
{
    private const TEST_SECRET = 'test-secret-key-for-unit-testing';
    private const TEST_USER_ID = 42;

    private JwtAuthService $auth;

    protected function setUp(): void
    {
        $this->auth = new JwtAuthService(self::TEST_SECRET);

        // Clean up globals that might leak between tests.
        unset($GLOBALS['wp_options']['bmn_jwt_secret']);
    }

    // ------------------------------------------------------------------
    // 1. testGenerateAccessTokenReturnsString
    // ------------------------------------------------------------------

    public function testGenerateAccessTokenReturnsString(): void
    {
        $token = $this->auth->generateAccessToken(self::TEST_USER_ID);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // JWT tokens have three dot-separated segments.
        $segments = explode('.', $token);
        $this->assertCount(3, $segments, 'JWT must have exactly three segments (header.payload.signature).');
    }

    // ------------------------------------------------------------------
    // 2. testAccessTokenContainsCorrectClaims
    // ------------------------------------------------------------------

    public function testAccessTokenContainsCorrectClaims(): void
    {
        $before = time();
        $token = $this->auth->generateAccessToken(self::TEST_USER_ID, ['role' => 'admin']);
        $after = time();

        $decoded = (array) JWT::decode($token, new Key(self::TEST_SECRET, 'HS256'));

        // Standard claims.
        $this->assertSame(self::TEST_USER_ID, $decoded['sub']);
        $this->assertSame('access', $decoded['type']);
        $this->assertSame('https://bmnboston.com', $decoded['iss']);

        // Timing claims (iat should be approximately now, exp 30 days later).
        $this->assertGreaterThanOrEqual($before, $decoded['iat']);
        $this->assertLessThanOrEqual($after, $decoded['iat']);
        $this->assertSame($decoded['iat'] + JwtAuthService::ACCESS_TOKEN_EXPIRY, $decoded['exp']);

        // Extra claims are merged in.
        $this->assertSame('admin', $decoded['role']);
    }

    // ------------------------------------------------------------------
    // 3. testGenerateRefreshTokenReturnsString
    // ------------------------------------------------------------------

    public function testGenerateRefreshTokenReturnsString(): void
    {
        $token = $this->auth->generateRefreshToken(self::TEST_USER_ID);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $segments = explode('.', $token);
        $this->assertCount(3, $segments);
    }

    // ------------------------------------------------------------------
    // 4. testRefreshTokenHasCorrectType
    // ------------------------------------------------------------------

    public function testRefreshTokenHasCorrectType(): void
    {
        $token = $this->auth->generateRefreshToken(self::TEST_USER_ID);
        $decoded = (array) JWT::decode($token, new Key(self::TEST_SECRET, 'HS256'));

        $this->assertSame('refresh', $decoded['type']);
        $this->assertSame(self::TEST_USER_ID, $decoded['sub']);
        $this->assertSame('https://bmnboston.com', $decoded['iss']);
        $this->assertSame($decoded['iat'] + JwtAuthService::REFRESH_TOKEN_EXPIRY, $decoded['exp']);
    }

    // ------------------------------------------------------------------
    // 5. testValidateAccessTokenReturnsPayload
    // ------------------------------------------------------------------

    public function testValidateAccessTokenReturnsPayload(): void
    {
        $token = $this->auth->generateAccessToken(self::TEST_USER_ID);
        $payload = $this->auth->validateToken($token, 'access');

        $this->assertIsArray($payload);
        $this->assertSame(self::TEST_USER_ID, $payload['sub']);
        $this->assertSame('access', $payload['type']);
        $this->assertSame('https://bmnboston.com', $payload['iss']);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
    }

    // ------------------------------------------------------------------
    // 6. testValidateTokenRejectsExpiredToken
    // ------------------------------------------------------------------

    public function testValidateTokenRejectsExpiredToken(): void
    {
        // Build a token that expired 100 seconds ago.
        $now = time();
        $expiredPayload = [
            'sub'  => self::TEST_USER_ID,
            'iat'  => $now - 200,
            'exp'  => $now - 100,
            'iss'  => 'https://bmnboston.com',
            'type' => 'access',
        ];
        $expiredToken = JWT::encode($expiredPayload, self::TEST_SECRET, 'HS256');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired.');

        $this->auth->validateToken($expiredToken, 'access');
    }

    // ------------------------------------------------------------------
    // 7. testValidateTokenRejectsWrongType
    // ------------------------------------------------------------------

    public function testValidateTokenRejectsWrongType(): void
    {
        // Generate a refresh token, then try to validate it as access.
        $refreshToken = $this->auth->generateRefreshToken(self::TEST_USER_ID);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected access token, got refresh.');

        $this->auth->validateToken($refreshToken, 'access');
    }

    // ------------------------------------------------------------------
    // 8. testValidateTokenRejectsEmptyString
    // ------------------------------------------------------------------

    public function testValidateTokenRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token must not be empty.');

        $this->auth->validateToken('');
    }

    public function testValidateTokenRejectsWhitespaceOnlyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token must not be empty.');

        $this->auth->validateToken('   ');
    }

    // ------------------------------------------------------------------
    // 9. testValidateTokenRejectsInvalidSignature
    // ------------------------------------------------------------------

    public function testValidateTokenRejectsInvalidSignature(): void
    {
        // Sign with a completely different secret.
        $wrongSecret = 'wrong-secret-key-totally-different';
        $payload = [
            'sub'  => self::TEST_USER_ID,
            'iat'  => time(),
            'exp'  => time() + 3600,
            'iss'  => 'https://bmnboston.com',
            'type' => 'access',
        ];
        $badToken = JWT::encode($payload, $wrongSecret, 'HS256');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token signature is invalid.');

        $this->auth->validateToken($badToken, 'access');
    }

    // ------------------------------------------------------------------
    // 10. testValidateTokenRejectsInvalidIssuer
    // ------------------------------------------------------------------

    public function testValidateTokenRejectsInvalidIssuer(): void
    {
        // Manually build a token with a wrong issuer but signed with the
        // correct secret so the signature check passes.
        $payload = [
            'sub'  => self::TEST_USER_ID,
            'iat'  => time(),
            'exp'  => time() + 3600,
            'iss'  => 'https://evil-site.example.com',
            'type' => 'access',
        ];
        $badIssuerToken = JWT::encode($payload, self::TEST_SECRET, 'HS256');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token issuer mismatch.');

        $this->auth->validateToken($badIssuerToken, 'access');
    }

    // ------------------------------------------------------------------
    // 11. testRefreshTokenReturnsNewTokenPair
    // ------------------------------------------------------------------

    public function testRefreshTokenReturnsNewTokenPair(): void
    {
        $refreshToken = $this->auth->generateRefreshToken(self::TEST_USER_ID);
        $result = $this->auth->refreshToken($refreshToken);

        // Must return the expected keys.
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);

        // Types.
        $this->assertIsString($result['access_token']);
        $this->assertIsString($result['refresh_token']);
        $this->assertSame(JwtAuthService::ACCESS_TOKEN_EXPIRY, $result['expires_in']);

        // New access token should be valid and belong to the same user.
        $accessPayload = $this->auth->validateToken($result['access_token'], 'access');
        $this->assertSame(self::TEST_USER_ID, $accessPayload['sub']);

        // New refresh token should also be valid.
        $refreshPayload = $this->auth->validateToken($result['refresh_token'], 'refresh');
        $this->assertSame(self::TEST_USER_ID, $refreshPayload['sub']);
    }

    // ------------------------------------------------------------------
    // 12. testRefreshTokenRejectsAccessToken
    // ------------------------------------------------------------------

    public function testRefreshTokenRejectsAccessToken(): void
    {
        // Generate an access token and try to use it as a refresh token.
        $accessToken = $this->auth->generateAccessToken(self::TEST_USER_ID);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected refresh token, got access.');

        $this->auth->refreshToken($accessToken);
    }

    // ------------------------------------------------------------------
    // 13. testHashPasswordReturnsString
    // ------------------------------------------------------------------

    public function testHashPasswordReturnsString(): void
    {
        $hash = $this->auth->hashPassword('my-secure-password');

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
        // Should not be the same as the plaintext.
        $this->assertNotSame('my-secure-password', $hash);
    }

    // ------------------------------------------------------------------
    // 14. testVerifyPasswordReturnsTrueForCorrectPassword
    // ------------------------------------------------------------------

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $password = 'correct-horse-battery-staple';
        $hash = $this->auth->hashPassword($password);

        $this->assertTrue($this->auth->verifyPassword($password, $hash));
    }

    // ------------------------------------------------------------------
    // 15. testVerifyPasswordReturnsFalseForWrongPassword
    // ------------------------------------------------------------------

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $hash = $this->auth->hashPassword('real-password');

        $this->assertFalse($this->auth->verifyPassword('wrong-password', $hash));
    }

    // ------------------------------------------------------------------
    // 16. testSecretFromOptionFallback
    // ------------------------------------------------------------------

    public function testSecretFromOptionFallback(): void
    {
        // Set the secret in the wp_options global (used by the get_option stub).
        $optionSecret = 'secret-from-database-option';
        $GLOBALS['wp_options']['bmn_jwt_secret'] = $optionSecret;

        // Create a service without a constructor secret and without the constant.
        $auth = new JwtAuthService();

        // Token generation should work using the option-sourced secret.
        $token = $auth->generateAccessToken(self::TEST_USER_ID);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Validate the token with the same service instance.
        $payload = $auth->validateToken($token, 'access');
        $this->assertSame(self::TEST_USER_ID, $payload['sub']);

        // Also verify by manually decoding with the option secret.
        $decoded = (array) JWT::decode($token, new Key($optionSecret, 'HS256'));
        $this->assertSame(self::TEST_USER_ID, $decoded['sub']);
    }

    // ------------------------------------------------------------------
    // 17. testMissingSecretThrowsRuntimeException
    // ------------------------------------------------------------------

    public function testMissingSecretThrowsRuntimeException(): void
    {
        // Ensure no fallback sources exist.
        unset($GLOBALS['wp_options']['bmn_jwt_secret']);

        $auth = new JwtAuthService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JWT secret key is not configured.');

        $auth->generateAccessToken(self::TEST_USER_ID);
    }

    // ------------------------------------------------------------------
    // Additional edge-case tests
    // ------------------------------------------------------------------

    public function testAccessTokenDefaultExpectedType(): void
    {
        // validateToken defaults to 'access' when no second argument is given.
        $token = $this->auth->generateAccessToken(self::TEST_USER_ID);
        $payload = $this->auth->validateToken($token);

        $this->assertSame('access', $payload['type']);
    }

    public function testExtraClaimsDoNotOverrideStandardClaims(): void
    {
        // Pass extra claims that collide with standard claim keys.
        // The implementation merges extras first, then overwrites with
        // standard claims (array_merge with standard claims last).
        $token = $this->auth->generateAccessToken(self::TEST_USER_ID, [
            'type' => 'evil',
            'sub'  => 9999,
        ]);

        $decoded = (array) JWT::decode($token, new Key(self::TEST_SECRET, 'HS256'));

        // Standard claims must win.
        $this->assertSame('access', $decoded['type']);
        $this->assertSame(self::TEST_USER_ID, $decoded['sub']);
    }

    public function testValidateTokenRejectsMalformedToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Token is malformed or invalid');

        $this->auth->validateToken('not.a.valid-jwt');
    }

    public function testRefreshFlowPreservesUserId(): void
    {
        $userId = 99;
        $refreshToken = $this->auth->generateRefreshToken($userId);
        $result = $this->auth->refreshToken($refreshToken);

        $accessPayload = $this->auth->validateToken($result['access_token'], 'access');
        $refreshPayload = $this->auth->validateToken($result['refresh_token'], 'refresh');

        $this->assertSame($userId, $accessPayload['sub']);
        $this->assertSame($userId, $refreshPayload['sub']);
    }

    public function testTokenExpiryConstants(): void
    {
        // Both access and refresh tokens use 30-day lifetime (2,592,000 seconds).
        $this->assertSame(2_592_000, JwtAuthService::ACCESS_TOKEN_EXPIRY);
        $this->assertSame(2_592_000, JwtAuthService::REFRESH_TOKEN_EXPIRY);
    }
}
