<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Service;

use BMN\Platform\Auth\AuthService;
use BMN\Platform\Auth\JwtAuthService;
use BMN\Platform\Email\EmailService;
use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Repository\PasswordResetRepository;
use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Repository\TokenRevocationRepository;
use BMN\Users\Service\UserAuthService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserAuthServiceTest extends TestCase
{
    private AuthService $authService;
    private EmailService $emailService;
    private TokenRevocationRepository $tokenRepo;
    private PasswordResetRepository $resetRepo;
    private UserAuthService $service;

    protected function setUp(): void
    {
        $this->authService = $this->createMock(AuthService::class);
        $this->emailService = $this->createMock(EmailService::class);
        $this->tokenRepo = $this->createMock(TokenRevocationRepository::class);
        $this->resetRepo = $this->createMock(PasswordResetRepository::class);

        $this->service = new UserAuthService(
            $this->authService,
            $this->emailService,
            $this->tokenRepo,
            $this->resetRepo,
        );

        // Clear global state.
        unset($GLOBALS['wp_authenticate_result']);
        $GLOBALS['wp_transients'] = [];
        $GLOBALS['wp_next_user_id'] = $GLOBALS['wp_next_user_id'] ?? 100;

        foreach (array_keys($GLOBALS) as $key) {
            if (str_starts_with($key, 'wp_user_')) {
                unset($GLOBALS[$key]);
            }
        }
    }

    private function registerGlobalUser(int $id, string $email = 'test@example.com'): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = $email;
        $user->user_pass = wp_hash_password('Test1234!');
        $user->display_name = 'Test User';
        $user->first_name = 'Test';
        $user->last_name = 'User';
        $user->roles = ['subscriber'];

        $GLOBALS["wp_user_id_{$id}"] = $user;
        $GLOBALS["wp_user_email_{$email}"] = $user;

        return $user;
    }

    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------

    public function testLoginReturnsAuthResponse(): void
    {
        $user = $this->registerGlobalUser(1);
        $GLOBALS['wp_authenticate_result'] = $user;

        $this->authService->method('generateAccessToken')->willReturn('access_token_xxx');
        $this->authService->method('generateRefreshToken')->willReturn('refresh_token_xxx');

        $result = $this->service->login('test@example.com', 'Test1234!');

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertSame('access_token_xxx', $result['access_token']);
    }

    public function testLoginThrowsOnInvalidCredentials(): void
    {
        $GLOBALS['wp_authenticate_result'] = new \WP_Error('invalid', 'Invalid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid email or password.');

        $this->service->login('bad@example.com', 'wrong');
    }

    public function testLoginRecordsFailedAttempt(): void
    {
        $GLOBALS['wp_authenticate_result'] = new \WP_Error('invalid', 'Invalid');

        try {
            $this->service->login('bad@example.com', 'wrong');
        } catch (RuntimeException) {
            // expected
        }

        // Check that a transient was set.
        $found = false;
        foreach (array_keys($GLOBALS['wp_transients']) as $key) {
            if (str_starts_with($key, 'bmn_auth_login_')) {
                $found = true;
                $data = $GLOBALS['wp_transients'][$key]['value'];
                $this->assertSame(1, $data['attempts']);
            }
        }
        $this->assertTrue($found, 'Rate limit transient should be set after failed login');
    }

    public function testLoginThrowsWhenRateLimited(): void
    {
        // Pre-populate rate limit data.
        $identifier = md5('bad@example.com' . '127.0.0.1');
        set_transient('bmn_auth_login_' . $identifier, [
            'attempts'     => 20,
            'locked_until' => (int) current_time('timestamp') + 300,
        ], 900);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many login attempts.');

        $this->service->login('bad@example.com', 'wrong');
    }

    // ------------------------------------------------------------------
    // Register
    // ------------------------------------------------------------------

    public function testRegisterCreatesUserAndReturnsTokens(): void
    {
        $this->authService->method('generateAccessToken')->willReturn('access_xxx');
        $this->authService->method('generateRefreshToken')->willReturn('refresh_xxx');

        $result = $this->service->register('new@example.com', 'Pass1234!', 'New', 'User');

        $this->assertArrayHasKey('user', $result);
        $this->assertSame('access_xxx', $result['access_token']);
        $this->assertSame('new@example.com', $result['user']['email']);
    }

    public function testRegisterThrowsOnDuplicateEmail(): void
    {
        $this->registerGlobalUser(1, 'existing@example.com');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $this->service->register('existing@example.com', 'Pass1234!', 'Dup', 'User');
    }

    public function testRegisterThrowsOnInsertFailure(): void
    {
        $GLOBALS['wp_insert_user_error'] = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create account.');

        try {
            $this->service->register('fail@example.com', 'Pass1234!', 'Fail', 'User');
        } finally {
            unset($GLOBALS['wp_insert_user_error']);
        }
    }

    public function testRegisterSetsPhoneWhenProvided(): void
    {
        $this->authService->method('generateAccessToken')->willReturn('tok');
        $this->authService->method('generateRefreshToken')->willReturn('ref');

        $result = $this->service->register('phone@example.com', 'Pass1234!', 'Phone', 'User', '617-555-1234');

        $this->assertArrayHasKey('user', $result);
    }

    // ------------------------------------------------------------------
    // Refresh Token
    // ------------------------------------------------------------------

    public function testRefreshTokenReturnsNewTokens(): void
    {
        $this->tokenRepo->method('isRevoked')->willReturn(false);
        $this->authService->method('refreshToken')->willReturn([
            'access_token'  => 'new_access',
            'refresh_token' => 'new_refresh',
            'expires_in'    => 2592000,
        ]);
        $this->authService->method('validateToken')->willReturn([
            'sub' => 1, 'exp' => time() + 3600, 'iss' => '', 'type' => 'refresh', 'iat' => time(),
        ]);

        $user = $this->registerGlobalUser(1);

        $result = $this->service->refreshToken('old_refresh_token');

        $this->assertSame('new_access', $result['access_token']);
        $this->assertSame('new_refresh', $result['refresh_token']);
        $this->assertArrayHasKey('user', $result);
    }

    public function testRefreshTokenThrowsWhenRevoked(): void
    {
        $this->tokenRepo->method('isRevoked')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token has been revoked.');

        $this->service->refreshToken('revoked_token');
    }

    // ------------------------------------------------------------------
    // Logout
    // ------------------------------------------------------------------

    public function testLogoutRevokesToken(): void
    {
        $this->authService->method('validateToken')->willReturn([
            'sub' => 1, 'exp' => time() + 3600, 'iss' => '', 'type' => 'access', 'iat' => time(),
        ]);

        $this->tokenRepo->expects($this->once())->method('revokeToken');

        $this->service->logout('some_jwt_token', 1);
    }

    public function testLogoutHandlesInvalidTokenGracefully(): void
    {
        $this->authService->method('validateToken')
            ->willThrowException(new \InvalidArgumentException('Bad token'));

        $this->tokenRepo->expects($this->once())->method('revokeToken');

        $this->service->logout('bad_token', 1);
    }

    // ------------------------------------------------------------------
    // Forgot Password
    // ------------------------------------------------------------------

    public function testForgotPasswordSendsEmail(): void
    {
        $user = $this->registerGlobalUser(1, 'reset@example.com');

        $this->resetRepo->expects($this->once())->method('invalidateForUser')->with(1);
        $this->resetRepo->expects($this->once())->method('createReset');
        $this->emailService->expects($this->once())->method('sendTemplate');

        $result = $this->service->forgotPassword('reset@example.com');

        $this->assertTrue($result);
    }

    public function testForgotPasswordReturnsTrueForUnknownEmail(): void
    {
        $result = $this->service->forgotPassword('unknown@example.com');

        $this->assertTrue($result);
    }

    public function testForgotPasswordDoesNotSendEmailForUnknownUser(): void
    {
        $this->emailService->expects($this->never())->method('sendTemplate');

        $this->service->forgotPassword('nobody@example.com');
    }

    // ------------------------------------------------------------------
    // Reset Password
    // ------------------------------------------------------------------

    public function testResetPasswordSucceeds(): void
    {
        $reset = (object) ['id' => 1, 'user_id' => 1, 'token_hash' => 'hash'];
        $this->resetRepo->method('findValidReset')->willReturn($reset);
        $this->resetRepo->expects($this->once())->method('markUsed')->with(1);

        $this->registerGlobalUser(1);

        $result = $this->service->resetPassword('raw_token', 'NewPass1!');

        $this->assertTrue($result);
    }

    public function testResetPasswordThrowsOnInvalidToken(): void
    {
        $this->resetRepo->method('findValidReset')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired reset token.');

        $this->service->resetPassword('bad_token', 'NewPass1!');
    }

    // ------------------------------------------------------------------
    // Delete Account
    // ------------------------------------------------------------------

    public function testDeleteAccountRemovesAllData(): void
    {
        $this->registerGlobalUser(1);

        $favoriteRepo = $this->createMock(FavoriteRepository::class);
        $savedSearchRepo = $this->createMock(SavedSearchRepository::class);

        $favoriteRepo->expects($this->once())->method('removeAllForUser')->with(1);
        $savedSearchRepo->expects($this->once())->method('removeAllForUser')->with(1);
        $this->tokenRepo->expects($this->once())->method('revokeAllForUser')->with(1);
        $this->resetRepo->expects($this->once())->method('invalidateForUser')->with(1);

        $result = $this->service->deleteAccount(1, $favoriteRepo, $savedSearchRepo);

        $this->assertTrue($result);
    }

    public function testDeleteAccountThrowsForMissingUser(): void
    {
        $favoriteRepo = $this->createMock(FavoriteRepository::class);
        $savedSearchRepo = $this->createMock(SavedSearchRepository::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found.');

        $this->service->deleteAccount(999, $favoriteRepo, $savedSearchRepo);
    }

    // ------------------------------------------------------------------
    // Rate Limiting
    // ------------------------------------------------------------------

    public function testCheckLoginRateLimitAllowsWhenNoData(): void
    {
        // Should not throw.
        $this->service->checkLoginRateLimit('test_id');
        $this->assertTrue(true);
    }

    public function testRecordLoginAttemptIncrementsCounter(): void
    {
        $this->service->recordLoginAttempt('test_id');

        $data = get_transient('bmn_auth_login_test_id');
        $this->assertSame(1, $data['attempts']);

        $this->service->recordLoginAttempt('test_id');

        $data = get_transient('bmn_auth_login_test_id');
        $this->assertSame(2, $data['attempts']);
    }

    public function testRevokeAllTokensDelegates(): void
    {
        $this->tokenRepo->expects($this->once())->method('revokeAllForUser')->with(1);

        $this->service->revokeAllTokens(1);
    }
}
