<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Service;

use BMN\Agents\Repository\SharedPropertyRepository;
use BMN\Agents\Service\SharedPropertyService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SharedPropertyServiceTest extends TestCase
{
    private SharedPropertyRepository $repo;
    private SharedPropertyService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SharedPropertyRepository::class);
        $this->service = new SharedPropertyService($this->repo);
    }

    public function testSharePropertiesCreatesNewShares(): void
    {
        $this->repo->method('findByAgentClientListing')->willReturn(null);
        $this->repo->method('create')->willReturn(1);

        $count = $this->service->shareProperties(10, [20, 30], ['MLS001', 'MLS002']);

        $this->assertSame(4, $count); // 2 clients x 2 listings
    }

    public function testSharePropertiesUpdatesExistingShares(): void
    {
        $this->repo->method('findByAgentClientListing')->willReturn(
            (object) ['id' => 5, 'is_dismissed' => 1]
        );
        $this->repo->expects($this->once())->method('update')
            ->with(5, $this->callback(fn (array $data): bool => $data['is_dismissed'] === 0));

        $this->service->shareProperties(10, [20], ['MLS001'], 'Check this out');
    }

    public function testSharePropertiesWithNote(): void
    {
        $this->repo->method('findByAgentClientListing')->willReturn(null);
        $this->repo->expects($this->once())->method('create')
            ->with($this->callback(fn (array $data): bool => $data['agent_note'] === 'Great listing'))
            ->willReturn(1);

        $this->service->shareProperties(10, [20], ['MLS001'], 'Great listing');
    }

    public function testGetSharedForClientReturnsPaginated(): void
    {
        $this->repo->method('findForClient')->willReturn([
            (object) ['id' => 1, 'listing_id' => 'MLS001'],
        ]);
        $this->repo->method('countForClient')->willReturn(5);

        $result = $this->service->getSharedForClient(20, false, 1, 20);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(1, $result['items']);
        $this->assertSame(5, $result['total']);
    }

    public function testGetSharedByAgentReturnsResults(): void
    {
        $this->repo->method('findByAgent')->willReturn([
            (object) ['id' => 1, 'listing_id' => 'MLS001'],
        ]);

        $result = $this->service->getSharedByAgent(10);

        $this->assertCount(1, $result);
    }

    public function testRespondToShareUpdatesResponse(): void
    {
        $this->repo->method('find')->willReturn(
            (object) ['id' => 1, 'client_user_id' => 20]
        );
        $this->repo->expects($this->once())->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['client_response'] === 'interested'))
            ->willReturn(true);

        $result = $this->service->respondToShare(1, 20, 'interested', 'Love it');

        $this->assertTrue($result);
    }

    public function testRespondToShareThrowsWhenNotFound(): void
    {
        $this->repo->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shared property not found.');

        $this->service->respondToShare(999, 20, 'interested');
    }

    public function testRespondToShareThrowsWhenUnauthorized(): void
    {
        $this->repo->method('find')->willReturn(
            (object) ['id' => 1, 'client_user_id' => 99]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not authorized');

        $this->service->respondToShare(1, 20, 'interested');
    }

    public function testRespondToShareThrowsOnInvalidResponse(): void
    {
        $this->repo->method('find')->willReturn(
            (object) ['id' => 1, 'client_user_id' => 20]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid response');

        $this->service->respondToShare(1, 20, 'maybe');
    }

    public function testDismissShareSetsDismissed(): void
    {
        $this->repo->method('find')->willReturn(
            (object) ['id' => 1, 'client_user_id' => 20]
        );
        $this->repo->expects($this->once())->method('update')
            ->with(1, $this->callback(fn (array $data): bool => $data['is_dismissed'] === 1))
            ->willReturn(true);

        $result = $this->service->dismissShare(1, 20);

        $this->assertTrue($result);
    }

    public function testRecordViewDelegatesToRepo(): void
    {
        $this->repo->method('find')->willReturn(
            (object) ['id' => 1, 'client_user_id' => 20]
        );
        $this->repo->method('recordView')->willReturn(true);

        $result = $this->service->recordView(1, 20);

        $this->assertTrue($result);
    }

    public function testRecordViewReturnsFalseWhenUnauthorized(): void
    {
        $this->repo->method('find')->willReturn(
            (object) ['id' => 1, 'client_user_id' => 99]
        );

        $result = $this->service->recordView(1, 20);

        $this->assertFalse($result);
    }
}
