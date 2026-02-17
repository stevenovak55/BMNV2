<?php

declare(strict_types=1);

namespace BMN\Agents\Tests\Unit\Service;

use BMN\Agents\Repository\ActivityLogRepository;
use BMN\Agents\Repository\RelationshipRepository;
use BMN\Agents\Service\ActivityService;
use PHPUnit\Framework\TestCase;

final class ActivityServiceTest extends TestCase
{
    private ActivityLogRepository $activityRepo;
    private RelationshipRepository $relationshipRepo;
    private ActivityService $service;

    protected function setUp(): void
    {
        $this->activityRepo = $this->createMock(ActivityLogRepository::class);
        $this->relationshipRepo = $this->createMock(RelationshipRepository::class);
        $this->service = new ActivityService($this->activityRepo, $this->relationshipRepo);
    }

    public function testLogActivityAutoResolvesAgent(): void
    {
        $this->relationshipRepo->method('findActiveForClient')->willReturn(
            (object) ['agent_user_id' => 10, 'status' => 'active']
        );
        $this->activityRepo->expects($this->once())->method('create')
            ->with($this->callback(fn (array $data): bool =>
                $data['agent_user_id'] === 10 &&
                $data['client_user_id'] === 20 &&
                $data['activity_type'] === 'favorite_added'
            ))
            ->willReturn(1);

        $result = $this->service->logActivity(20, 'favorite_added', 'MLS001', 'property');

        $this->assertSame(1, $result);
    }

    public function testLogActivityReturnsFalseWhenNoAgent(): void
    {
        $this->relationshipRepo->method('findActiveForClient')->willReturn(null);

        $result = $this->service->logActivity(20, 'favorite_added');

        $this->assertFalse($result);
    }

    public function testLogActivityWithMetadata(): void
    {
        $this->relationshipRepo->method('findActiveForClient')->willReturn(
            (object) ['agent_user_id' => 10]
        );
        $this->activityRepo->expects($this->once())->method('create')
            ->with($this->callback(fn (array $data): bool =>
                $data['metadata'] === ['query' => 'Boston condos']
            ))
            ->willReturn(1);

        $this->service->logActivity(20, 'search_created', null, null, ['query' => 'Boston condos']);
    }

    public function testGetAgentActivityFeedFormatsData(): void
    {
        $this->activityRepo->method('findByAgent')->willReturn([
            (object) [
                'id' => 1, 'client_user_id' => 20, 'activity_type' => 'favorite_added',
                'entity_id' => 'MLS001', 'entity_type' => 'property',
                'metadata' => '{"beds":3}', 'created_at' => '2026-01-01 10:00:00',
            ],
        ]);

        $result = $this->service->getAgentActivityFeed(10, 1, 50);

        $this->assertCount(1, $result);
        $this->assertSame('favorite_added', $result[0]['activity_type']);
        $this->assertSame(['beds' => 3], $result[0]['metadata']);
    }

    public function testGetAgentActivityFeedEmptyResult(): void
    {
        $this->activityRepo->method('findByAgent')->willReturn([]);

        $result = $this->service->getAgentActivityFeed(10);

        $this->assertSame([], $result);
    }

    public function testGetClientActivityFormatsData(): void
    {
        $this->activityRepo->method('findByAgentAndClient')->willReturn([
            (object) [
                'id' => 1, 'activity_type' => 'client_login',
                'entity_id' => null, 'entity_type' => null,
                'metadata' => null, 'created_at' => '2026-01-01 10:00:00',
            ],
        ]);

        $result = $this->service->getClientActivity(10, 20, 50);

        $this->assertCount(1, $result);
        $this->assertSame('client_login', $result[0]['activity_type']);
        $this->assertNull($result[0]['metadata']);
    }

    public function testGetAgentMetricsReturnsAllData(): void
    {
        $this->relationshipRepo->method('countClientsByAgent')->willReturn(15);
        $this->activityRepo->method('countActiveClients')->willReturn(8);
        $this->activityRepo->method('countRecent')->willReturn(42);
        $this->activityRepo->method('countByType')->willReturn([
            'favorite_added' => 20,
            'client_login' => 10,
        ]);

        $metrics = $this->service->getAgentMetrics(10, 30);

        $this->assertSame(15, $metrics['total_clients']);
        $this->assertSame(8, $metrics['active_clients']);
        $this->assertSame(42, $metrics['recent_activities']);
        $this->assertSame(30, $metrics['period_days']);
        $this->assertArrayHasKey('activity_by_type', $metrics);
    }

    public function testGetAgentMetricsCustomDays(): void
    {
        $this->relationshipRepo->method('countClientsByAgent')->willReturn(0);
        $this->activityRepo->method('countActiveClients')->willReturn(0);
        $this->activityRepo->method('countRecent')->willReturn(0);
        $this->activityRepo->method('countByType')->willReturn([]);

        $metrics = $this->service->getAgentMetrics(10, 7);

        $this->assertSame(7, $metrics['period_days']);
    }

    public function testLogActivityWithEntityFields(): void
    {
        $this->relationshipRepo->method('findActiveForClient')->willReturn(
            (object) ['agent_user_id' => 10]
        );
        $this->activityRepo->expects($this->once())->method('create')
            ->with($this->callback(fn (array $data): bool =>
                $data['entity_id'] === 'MLS999' &&
                $data['entity_type'] === 'property'
            ))
            ->willReturn(1);

        $this->service->logActivity(20, 'property_viewed', 'MLS999', 'property');
    }
}
