<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Service;

use BMN\Users\Service\UserProfileFormatter;
use PHPUnit\Framework\TestCase;

final class UserProfileFormatterTest extends TestCase
{
    private function makeUser(array $overrides = []): object
    {
        $user = new \stdClass();
        $user->ID = $overrides['ID'] ?? 1;
        $user->user_email = $overrides['user_email'] ?? 'test@example.com';
        $user->display_name = $overrides['display_name'] ?? 'Test User';
        $user->first_name = $overrides['first_name'] ?? 'Test';
        $user->last_name = $overrides['last_name'] ?? 'User';
        $user->roles = $overrides['roles'] ?? ['subscriber'];

        return $user;
    }

    public function testFormatReturnsCompleteProfile(): void
    {
        $user = $this->makeUser();
        $profile = UserProfileFormatter::format($user);

        $this->assertSame(1, $profile['id']);
        $this->assertSame('test@example.com', $profile['email']);
        $this->assertSame('Test User', $profile['name']);
        $this->assertSame('Test', $profile['first_name']);
        $this->assertSame('User', $profile['last_name']);
        $this->assertArrayHasKey('phone', $profile);
        $this->assertArrayHasKey('avatar_url', $profile);
        $this->assertArrayHasKey('user_type', $profile);
        $this->assertArrayHasKey('assigned_agent', $profile);
        $this->assertArrayHasKey('mls_agent_id', $profile);
    }

    public function testFormatMapsAdministratorToAdmin(): void
    {
        $user = $this->makeUser(['roles' => ['administrator']]);
        $profile = UserProfileFormatter::format($user);

        $this->assertSame('admin', $profile['user_type']);
    }

    public function testFormatMapsBmnAgentToAgent(): void
    {
        $user = $this->makeUser(['roles' => ['bmn_agent']]);
        $profile = UserProfileFormatter::format($user);

        $this->assertSame('agent', $profile['user_type']);
    }

    public function testFormatMapsDefaultToClient(): void
    {
        $user = $this->makeUser(['roles' => ['subscriber']]);
        $profile = UserProfileFormatter::format($user);

        $this->assertSame('client', $profile['user_type']);
    }
}
