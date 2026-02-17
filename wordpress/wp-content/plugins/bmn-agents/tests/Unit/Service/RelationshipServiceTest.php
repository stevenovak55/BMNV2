<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Service;

use BMN\Agents\Repository\RelationshipRepository;
use BMN\Agents\Service\RelationshipService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RelationshipServiceTest extends TestCase
{
    private RelationshipRepository $repo;
    private RelationshipService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(RelationshipRepository::class);
        $this->service = new RelationshipService($this->repo);
    }

    public function testAssignAgentCreatesNewRelationship(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(null);
        $this->repo->method('findByAgentAndClient')->willReturn(null);
        $this->repo->method('create')->willReturn(1);

        $result = $this->service->assignAgent(10, 20, 'manual', 'Test');

        $this->assertSame(1, $result);
    }

    public function testAssignAgentReactivatesExistingRelationship(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(null);
        $this->repo->method('findByAgentAndClient')->willReturn(
            (object) ['id' => 5, 'agent_user_id' => 10, 'client_user_id' => 20, 'status' => 'inactive']
        );
        $this->repo->method('update')->willReturn(true);

        $result = $this->service->assignAgent(10, 20, 'manual');

        $this->assertSame(5, $result);
    }

    public function testAssignAgentThrowsWhenClientHasDifferentAgent(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(
            (object) ['id' => 1, 'agent_user_id' => 99, 'client_user_id' => 20, 'status' => 'active']
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Client already has an active agent.');

        $this->service->assignAgent(10, 20);
    }

    public function testAssignAgentAllowsSameAgent(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(
            (object) ['id' => 1, 'agent_user_id' => 10, 'client_user_id' => 20, 'status' => 'active']
        );
        $this->repo->method('findByAgentAndClient')->willReturn(
            (object) ['id' => 1, 'agent_user_id' => 10, 'client_user_id' => 20, 'status' => 'active']
        );
        $this->repo->method('update')->willReturn(true);

        $result = $this->service->assignAgent(10, 20);

        $this->assertSame(1, $result);
    }

    public function testUnassignAgentSetsInactive(): void
    {
        $this->repo->method('findByAgentAndClient')->willReturn(
            (object) ['id' => 5, 'agent_user_id' => 10, 'client_user_id' => 20]
        );
        $this->repo->expects($this->once())->method('update')
            ->with(5, $this->callback(fn (array $data): bool => $data['status'] === 'inactive'))
            ->willReturn(true);

        $result = $this->service->unassignAgent(10, 20);

        $this->assertTrue($result);
    }

    public function testUnassignAgentThrowsWhenNotFound(): void
    {
        $this->repo->method('findByAgentAndClient')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Relationship not found.');

        $this->service->unassignAgent(10, 20);
    }

    public function testGetClientAgentReturnsRelationship(): void
    {
        $rel = (object) ['id' => 1, 'agent_user_id' => 10, 'status' => 'active'];
        $this->repo->method('findActiveForClient')->willReturn($rel);

        $result = $this->service->getClientAgent(20);

        $this->assertSame($rel, $result);
    }

    public function testGetClientAgentReturnsNullWhenNone(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(null);

        $result = $this->service->getClientAgent(20);

        $this->assertNull($result);
    }

    public function testGetAgentClientsReturnsPaginatedResult(): void
    {
        $this->repo->method('findClientsByAgent')->willReturn([
            (object) ['id' => 1, 'client_user_id' => 20],
        ]);
        $this->repo->method('countClientsByAgent')->willReturn(10);

        $result = $this->service->getAgentClients(10, 'active', 1, 20);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(1, $result['items']);
        $this->assertSame(10, $result['total']);
    }

    public function testCreateClientCreatesUserAndAssigns(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(null);
        $this->repo->method('findByAgentAndClient')->willReturn(null);
        $this->repo->method('create')->willReturn(1);

        $result = $this->service->createClient([
            'email'      => 'client@test.com',
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ], 10);

        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('relationship_id', $result);
        $this->assertIsInt($result['user_id']);
    }

    public function testCreateClientThrowsWhenEmailMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email is required.');

        $this->service->createClient([
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ], 10);
    }

    public function testIsAgentForClientReturnsTrue(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(
            (object) ['id' => 1, 'agent_user_id' => 10, 'status' => 'active']
        );

        $this->assertTrue($this->service->isAgentForClient(10, 20));
    }

    public function testIsAgentForClientReturnsFalse(): void
    {
        $this->repo->method('findActiveForClient')->willReturn(
            (object) ['id' => 1, 'agent_user_id' => 99, 'status' => 'active']
        );

        $this->assertFalse($this->service->isAgentForClient(10, 20));
    }

    public function testUpdateStatusUpdatesRelationship(): void
    {
        $this->repo->method('findByAgentAndClient')->willReturn(
            (object) ['id' => 5, 'agent_user_id' => 10, 'client_user_id' => 20]
        );
        $this->repo->method('update')->willReturn(true);

        $result = $this->service->updateStatus(10, 20, 'inactive');

        $this->assertTrue($result);
    }
}
