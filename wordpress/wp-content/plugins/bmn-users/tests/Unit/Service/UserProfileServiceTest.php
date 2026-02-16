<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Service;

use BMN\Users\Service\UserProfileService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class UserProfileServiceTest extends TestCase
{
    private UserProfileService $service;

    protected function setUp(): void
    {
        $this->service = new UserProfileService();

        // Clear any global user stubs.
        foreach (array_keys($GLOBALS) as $key) {
            if (str_starts_with($key, 'wp_user_')) {
                unset($GLOBALS[$key]);
            }
        }
    }

    private function registerUser(int $id, array $overrides = []): object
    {
        $user = new \stdClass();
        $user->ID = $id;
        $user->user_email = $overrides['user_email'] ?? "user{$id}@example.com";
        $user->user_pass = wp_hash_password($overrides['password'] ?? 'Test1234!');
        $user->display_name = $overrides['display_name'] ?? 'Test User';
        $user->first_name = $overrides['first_name'] ?? 'Test';
        $user->last_name = $overrides['last_name'] ?? 'User';
        $user->roles = $overrides['roles'] ?? ['subscriber'];

        $GLOBALS["wp_user_id_{$id}"] = $user;

        return $user;
    }

    public function testGetProfileReturnsFormattedProfile(): void
    {
        $this->registerUser(1);

        $profile = $this->service->getProfile(1);

        $this->assertNotNull($profile);
        $this->assertSame(1, $profile['id']);
        $this->assertSame('user1@example.com', $profile['email']);
        $this->assertSame('Test', $profile['first_name']);
    }

    public function testGetProfileReturnsNullForMissingUser(): void
    {
        $profile = $this->service->getProfile(999);

        $this->assertNull($profile);
    }

    public function testUpdateProfileUpdatesFields(): void
    {
        $this->registerUser(1);

        $profile = $this->service->updateProfile(1, [
            'first_name' => 'Updated',
            'last_name'  => 'Name',
        ]);

        $this->assertSame('Updated', $profile['first_name']);
    }

    public function testUpdateProfileThrowsForMissingUser(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found.');

        $this->service->updateProfile(999, ['first_name' => 'Nope']);
    }

    public function testUpdateProfileThrowsOnUpdateError(): void
    {
        $this->registerUser(1);
        $GLOBALS['wp_update_user_error'] = true;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update user profile.');

        try {
            $this->service->updateProfile(1, ['first_name' => 'Fail']);
        } finally {
            unset($GLOBALS['wp_update_user_error']);
        }
    }

    public function testChangePasswordSucceeds(): void
    {
        $this->registerUser(1, ['password' => 'OldPass1!']);

        $result = $this->service->changePassword(1, 'OldPass1!', 'NewPass1!');

        $this->assertTrue($result);
    }

    public function testChangePasswordThrowsForWrongCurrentPassword(): void
    {
        $this->registerUser(1, ['password' => 'OldPass1!']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Current password is incorrect.');

        $this->service->changePassword(1, 'WrongPass', 'NewPass1!');
    }

    public function testChangePasswordThrowsForMissingUser(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found.');

        $this->service->changePassword(999, 'Old', 'New');
    }
}
