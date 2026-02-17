<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Service;

use BMN\Agents\Repository\ReferralCodeRepository;
use BMN\Agents\Repository\ReferralSignupRepository;
use BMN\Agents\Service\ReferralService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReferralServiceTest extends TestCase
{
    private ReferralCodeRepository $codeRepo;
    private ReferralSignupRepository $signupRepo;
    private ReferralService $service;

    protected function setUp(): void
    {
        $this->codeRepo = $this->createMock(ReferralCodeRepository::class);
        $this->signupRepo = $this->createMock(ReferralSignupRepository::class);
        $this->service = new ReferralService($this->codeRepo, $this->signupRepo);
    }

    public function testGenerateCodeWithCustomCode(): void
    {
        $this->codeRepo->method('codeExists')->willReturn(false);
        $this->codeRepo->method('deactivateForAgent')->willReturn(true);
        $this->codeRepo->method('create')->willReturn(1);

        $code = $this->service->generateCode(10, 'MYCODE');

        $this->assertSame('MYCODE', $code);
    }

    public function testGenerateCodeAutoGenerates(): void
    {
        $this->codeRepo->method('codeExists')->willReturn(false);
        $this->codeRepo->method('deactivateForAgent')->willReturn(true);
        $this->codeRepo->method('create')->willReturn(1);

        $code = $this->service->generateCode(10);

        $this->assertNotEmpty($code);
        $this->assertSame(8, strlen($code));
    }

    public function testGenerateCodeThrowsWhenCodeExists(): void
    {
        $this->codeRepo->method('codeExists')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Referral code already exists.');

        $this->service->generateCode(10, 'TAKEN');
    }

    public function testGetAgentReferralReturnsData(): void
    {
        $this->codeRepo->method('findActiveForAgent')->willReturn(
            (object) ['referral_code' => 'ABC123', 'is_active' => 1, 'created_at' => '2026-01-01']
        );
        $this->signupRepo->method('countByAgent')->willReturn(5);

        $result = $this->service->getAgentReferral(10);

        $this->assertNotNull($result);
        $this->assertSame('ABC123', $result['referral_code']);
        $this->assertStringContainsString('ref=ABC123', $result['referral_url']);
        $this->assertSame(5, $result['total_signups']);
    }

    public function testGetAgentReferralReturnsNullWhenNoCode(): void
    {
        $this->codeRepo->method('findActiveForAgent')->willReturn(null);

        $result = $this->service->getAgentReferral(10);

        $this->assertNull($result);
    }

    public function testUpdateCodeCreatesNewCode(): void
    {
        $this->codeRepo->method('codeExists')->willReturn(false);
        $this->codeRepo->method('deactivateForAgent')->willReturn(true);
        $this->codeRepo->method('create')->willReturn(2);

        $code = $this->service->updateCode(10, 'NEWCODE');

        $this->assertSame('NEWCODE', $code);
    }

    public function testUpdateCodeThrowsWhenCodeTaken(): void
    {
        $this->codeRepo->method('codeExists')->willReturn(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already in use');

        $this->service->updateCode(10, 'TAKEN');
    }

    public function testTrackSignupWithReferralCode(): void
    {
        $this->codeRepo->method('findByCode')->willReturn(
            (object) ['agent_user_id' => 10]
        );
        $this->signupRepo->method('create')->willReturn(1);

        $result = $this->service->trackSignup(100, 'ABC123', 'organic', 'web');

        $this->assertSame(1, $result);
    }

    public function testTrackSignupWithoutReferralCode(): void
    {
        $this->signupRepo->method('create')->willReturn(1);

        $result = $this->service->trackSignup(100, null, 'organic', 'ios');

        $this->assertSame(1, $result);
    }

    public function testResolveAgentForSignupReturnsAgentId(): void
    {
        $this->codeRepo->method('findByCode')->willReturn(
            (object) ['agent_user_id' => 10]
        );

        $result = $this->service->resolveAgentForSignup('ABC123');

        $this->assertSame(10, $result);
    }

    public function testResolveAgentForSignupReturnsZeroWhenNotFound(): void
    {
        $this->codeRepo->method('findByCode')->willReturn(null);

        $result = $this->service->resolveAgentForSignup('NONEXISTENT');

        $this->assertSame(0, $result);
    }

    public function testGetReferralStatsReturnsAllData(): void
    {
        $this->signupRepo->method('countByAgent')->willReturn(20);
        $this->signupRepo->method('countByAgentThisMonth')->willReturn(5);
        $this->signupRepo->method('countBySource')->willReturn([
            'referral_link' => 15,
            'organic' => 5,
        ]);

        $stats = $this->service->getReferralStats(10);

        $this->assertSame(20, $stats['total_signups']);
        $this->assertSame(5, $stats['this_month']);
        $this->assertArrayHasKey('by_source', $stats);
    }
}
