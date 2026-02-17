<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Service;

use BMN\Agents\Repository\AgentProfileRepository;
use BMN\Agents\Repository\AgentReadRepository;
use BMN\Agents\Repository\OfficeReadRepository;
use BMN\Agents\Service\AgentProfileService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AgentProfileServiceTest extends TestCase
{
    private AgentReadRepository $agentReadRepo;
    private OfficeReadRepository $officeReadRepo;
    private AgentProfileRepository $profileRepo;
    private AgentProfileService $service;

    protected function setUp(): void
    {
        $this->agentReadRepo = $this->createMock(AgentReadRepository::class);
        $this->officeReadRepo = $this->createMock(OfficeReadRepository::class);
        $this->profileRepo = $this->createMock(AgentProfileRepository::class);

        $this->service = new AgentProfileService(
            $this->agentReadRepo,
            $this->officeReadRepo,
            $this->profileRepo,
        );
    }

    private function makeAgent(string $mlsId = 'AGT001', string $name = 'John Smith'): object
    {
        return (object) [
            'agent_mls_id' => $mlsId, 'full_name' => $name,
            'first_name' => 'John', 'last_name' => 'Smith',
            'email' => 'john@test.com', 'phone' => '555-0100',
            'office_mls_id' => 'OFF001', 'designation' => 'Broker',
        ];
    }

    private function makeProfile(string $mlsId = 'AGT001'): object
    {
        return (object) [
            'id' => 1, 'agent_mls_id' => $mlsId, 'user_id' => 42,
            'bio' => 'Expert agent', 'photo_url' => 'https://example.com/photo.jpg',
            'specialties' => '["luxury","condos"]', 'is_featured' => 1,
            'is_active' => 1, 'snab_staff_id' => null, 'display_order' => 0,
        ];
    }

    private function makeOffice(): object
    {
        return (object) [
            'office_mls_id' => 'OFF001', 'office_name' => 'Test Realty',
            'phone' => '555-0200', 'address' => '123 Main St',
            'city' => 'Boston', 'state_or_province' => 'MA', 'postal_code' => '02101',
        ];
    }

    public function testGetAgentReturnsMergedData(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->profileRepo->method('findByMlsId')->willReturn($this->makeProfile());
        $this->officeReadRepo->method('findByMlsId')->willReturn($this->makeOffice());

        $result = $this->service->getAgent('AGT001');

        $this->assertNotNull($result);
        $this->assertSame('AGT001', $result['agent_mls_id']);
        $this->assertSame('John Smith', $result['full_name']);
        $this->assertSame('Expert agent', $result['bio']);
        $this->assertSame(['luxury', 'condos'], $result['specialties']);
        $this->assertNotNull($result['office']);
        $this->assertSame('Test Realty', $result['office']['office_name']);
    }

    public function testGetAgentReturnsNullWhenNotFound(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn(null);

        $result = $this->service->getAgent('NONEXISTENT');

        $this->assertNull($result);
    }

    public function testGetAgentWithoutProfile(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->profileRepo->method('findByMlsId')->willReturn(null);
        $this->officeReadRepo->method('findByMlsId')->willReturn(null);

        $result = $this->service->getAgent('AGT001');

        $this->assertNotNull($result);
        $this->assertNull($result['bio']);
        $this->assertSame([], $result['specialties']);
        $this->assertNull($result['office']);
    }

    public function testListAgentsReturnsPaginatedResult(): void
    {
        $this->agentReadRepo->method('findAll')->willReturn([
            $this->makeAgent('AGT001', 'Alice'),
            $this->makeAgent('AGT002', 'Bob'),
        ]);
        $this->agentReadRepo->method('count')->willReturn(50);
        $this->profileRepo->method('findByMlsId')->willReturn(null);
        $this->officeReadRepo->method('findByMlsId')->willReturn(null);

        $result = $this->service->listAgents([], 1, 20);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertCount(2, $result['items']);
        $this->assertSame(50, $result['total']);
    }

    public function testListAgentsWithSearch(): void
    {
        $this->agentReadRepo->method('searchByName')->willReturn([
            $this->makeAgent('AGT001', 'John Smith'),
        ]);
        $this->profileRepo->method('findByMlsId')->willReturn(null);
        $this->officeReadRepo->method('findByMlsId')->willReturn(null);

        $result = $this->service->listAgents(['search' => 'John'], 1, 20);

        $this->assertCount(1, $result['items']);
        $this->assertSame(1, $result['total']);
    }

    public function testGetFeaturedAgentsReturnsMergedData(): void
    {
        $profile = $this->makeProfile();
        $this->profileRepo->method('findFeatured')->willReturn([$profile]);
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->officeReadRepo->method('findByMlsId')->willReturn($this->makeOffice());

        $result = $this->service->getFeaturedAgents();

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['is_featured']);
    }

    public function testGetFeaturedAgentsHandlesMissingMlsAgent(): void
    {
        $profile = $this->makeProfile();
        $this->profileRepo->method('findFeatured')->willReturn([$profile]);
        $this->agentReadRepo->method('findByMlsId')->willReturn(null);

        $result = $this->service->getFeaturedAgents();

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['full_name']);
    }

    public function testSearchAgentsReturnsResults(): void
    {
        $this->agentReadRepo->method('searchByName')->willReturn([
            $this->makeAgent('AGT001', 'John Smith'),
        ]);
        $this->profileRepo->method('findByMlsId')->willReturn(null);
        $this->officeReadRepo->method('findByMlsId')->willReturn(null);

        $result = $this->service->searchAgents('John');

        $this->assertCount(1, $result);
        $this->assertSame('John Smith', $result[0]['full_name']);
    }

    public function testSaveProfileCreatesProfile(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->profileRepo->method('upsert')->willReturn(5);

        $result = $this->service->saveProfile('AGT001', ['bio' => 'New bio']);

        $this->assertSame(5, $result);
    }

    public function testSaveProfileThrowsWhenAgentNotFound(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Agent not found.');

        $this->service->saveProfile('NONEXISTENT', ['bio' => 'Test']);
    }

    public function testSaveProfileThrowsOnFailure(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->profileRepo->method('upsert')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to save profile.');

        $this->service->saveProfile('AGT001', ['bio' => 'Test']);
    }

    public function testSaveProfileFiltersAllowedFields(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->profileRepo->expects($this->once())
            ->method('upsert')
            ->with('AGT001', $this->callback(function (array $data): bool {
                return isset($data['bio']) && !isset($data['malicious_field']);
            }))
            ->willReturn(1);

        $this->service->saveProfile('AGT001', [
            'bio' => 'Safe bio',
            'malicious_field' => 'should not be passed',
        ]);
    }

    public function testLinkToUserCallsSaveProfile(): void
    {
        $this->agentReadRepo->method('findByMlsId')->willReturn($this->makeAgent());
        $this->profileRepo->method('upsert')->willReturn(3);

        $result = $this->service->linkToUser('AGT001', 42);

        $this->assertSame(3, $result);
    }

    public function testGetAgentWithOfficeData(): void
    {
        $agent = $this->makeAgent();
        $this->agentReadRepo->method('findByMlsId')->willReturn($agent);
        $this->profileRepo->method('findByMlsId')->willReturn(null);
        $this->officeReadRepo->method('findByMlsId')->willReturn($this->makeOffice());

        $result = $this->service->getAgent('AGT001');

        $this->assertNotNull($result['office']);
        $this->assertSame('Boston', $result['office']['city']);
        $this->assertSame('MA', $result['office']['state']);
    }
}
