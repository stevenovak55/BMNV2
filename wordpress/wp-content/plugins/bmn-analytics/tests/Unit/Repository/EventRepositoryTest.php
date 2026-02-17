<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Repository;

use BMN\Analytics\Repository\EventRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for EventRepository.
 */
final class EventRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private EventRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->insert_id = 0;

        $this->repository = new EventRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // create()
    // ------------------------------------------------------------------

    public function testCreateInsertsEventAndReturnsId(): void
    {
        $this->wpdb->insert_id = 42;

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_bmn_analytics_events',
                $this->callback(function (array $data): bool {
                    return $data['event_type'] === 'pageview'
                        && isset($data['created_at']);
                })
            )
            ->willReturn(1);

        $result = $this->repository->create([
            'event_type' => 'pageview',
            'session_id' => 'abc123',
        ]);

        $this->assertSame(42, $result);
    }

    public function testCreateReturnsFalseOnInsertFailure(): void
    {
        $this->wpdb->expects($this->once())
            ->method('insert')
            ->willReturn(false);

        $result = $this->repository->create(['event_type' => 'pageview']);

        $this->assertFalse($result);
    }

    public function testCreateJsonEncodesMetadataArray(): void
    {
        $this->wpdb->insert_id = 1;

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                'wp_bmn_analytics_events',
                $this->callback(function (array $data): bool {
                    // metadata should be JSON-encoded string, not array.
                    return is_string($data['metadata'])
                        && json_decode($data['metadata'], true) === ['key' => 'value'];
                })
            )
            ->willReturn(1);

        $this->repository->create([
            'event_type' => 'search',
            'metadata'   => ['key' => 'value'],
        ]);
    }

    public function testCreateSetsCreatedAtAutomatically(): void
    {
        $this->wpdb->insert_id = 1;

        $this->wpdb->expects($this->once())
            ->method('insert')
            ->with(
                $this->anything(),
                $this->callback(function (array $data): bool {
                    return isset($data['created_at']) && $data['created_at'] !== '';
                })
            )
            ->willReturn(1);

        $this->repository->create(['event_type' => 'pageview']);
    }

    // ------------------------------------------------------------------
    // findBySession()
    // ------------------------------------------------------------------

    public function testFindBySessionReturnsEventsForSession(): void
    {
        $event = (object) ['id' => 1, 'session_id' => 'sess-abc', 'event_type' => 'pageview'];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$event]);

        $result = $this->repository->findBySession('sess-abc');

        $this->assertCount(1, $result);
        $this->assertSame('pageview', $result[0]->event_type);
    }

    // ------------------------------------------------------------------
    // findByUser()
    // ------------------------------------------------------------------

    public function testFindByUserReturnsEventsForUser(): void
    {
        $event = (object) ['id' => 2, 'user_id' => 5, 'event_type' => 'property_view'];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$event]);

        $result = $this->repository->findByUser(5);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]->user_id);
    }

    // ------------------------------------------------------------------
    // countByType()
    // ------------------------------------------------------------------

    public function testCountByTypeReturnsInteger(): void
    {
        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_var')->willReturn('15');

        $result = $this->repository->countByType('pageview', '2026-02-01 00:00:00', '2026-02-02 00:00:00');

        $this->assertSame(15, $result);
    }

    // ------------------------------------------------------------------
    // getTopEntities()
    // ------------------------------------------------------------------

    public function testGetTopEntitiesReturnsRankedResults(): void
    {
        $row1 = (object) ['entity_id' => 'MLS123', 'view_count' => 50];
        $row2 = (object) ['entity_id' => 'MLS456', 'view_count' => 30];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$row1, $row2]);

        $result = $this->repository->getTopEntities('property_view', '2026-02-01', '2026-02-15', 10);

        $this->assertCount(2, $result);
        $this->assertSame('MLS123', $result[0]->entity_id);
        $this->assertSame(50, $result[0]->view_count);
    }

    // ------------------------------------------------------------------
    // getRecentByEntity()
    // ------------------------------------------------------------------

    public function testGetRecentByEntityReturnsFilteredResults(): void
    {
        $event = (object) [
            'id' => 10,
            'entity_id' => 'MLS789',
            'entity_type' => 'property',
            'event_type' => 'property_view',
        ];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$event]);

        $result = $this->repository->getRecentByEntity('MLS789', 'property', 50);

        $this->assertCount(1, $result);
        $this->assertSame('MLS789', $result[0]->entity_id);
    }
}
