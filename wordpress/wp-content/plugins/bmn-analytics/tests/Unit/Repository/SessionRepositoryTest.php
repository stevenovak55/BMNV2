<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Repository;

use BMN\Analytics\Repository\SessionRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SessionRepository.
 */
final class SessionRepositoryTest extends TestCase
{
    private \wpdb $wpdb;
    private SessionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = $this->createMock(\wpdb::class);
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->insert_id = 0;

        $this->repository = new SessionRepository($this->wpdb);
    }

    // ------------------------------------------------------------------
    // findBySessionId()
    // ------------------------------------------------------------------

    public function testFindBySessionIdReturnsSessionWhenFound(): void
    {
        $session = (object) [
            'id' => 1,
            'session_id' => 'sess-abc',
            'user_id' => null,
            'page_views' => 5,
        ];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_row')->willReturn($session);

        $result = $this->repository->findBySessionId('sess-abc');

        $this->assertNotNull($result);
        $this->assertSame('sess-abc', $result->session_id);
    }

    public function testFindBySessionIdReturnsNullWhenNotFound(): void
    {
        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_row')->willReturn(null);

        $result = $this->repository->findBySessionId('nonexistent');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // createOrUpdate()
    // ------------------------------------------------------------------

    public function testCreateOrUpdateReturnsInsertIdOnSuccess(): void
    {
        $this->wpdb->insert_id = 7;

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('query')->willReturn(1);

        $result = $this->repository->createOrUpdate([
            'session_id'  => 'sess-new',
            'page_views'  => 0,
            'events_count' => 0,
        ]);

        $this->assertSame(7, $result);
    }

    public function testCreateOrUpdateReturnsFalseOnFailure(): void
    {
        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('query')->willReturn(false);

        $result = $this->repository->createOrUpdate([
            'session_id' => 'sess-fail',
        ]);

        $this->assertFalse($result);
    }

    public function testCreateOrUpdateSetsTimestampsAutomatically(): void
    {
        $this->wpdb->insert_id = 1;

        $this->wpdb->expects($this->once())
            ->method('prepare')
            ->with(
                $this->callback(function (string $sql): bool {
                    // SQL should contain first_seen_at and last_seen_at columns.
                    return str_contains($sql, 'first_seen_at')
                        && str_contains($sql, 'last_seen_at');
                }),
                $this->anything(),
                $this->anything(),
                $this->anything(),
            )
            ->willReturn('prepared-sql');

        $this->wpdb->method('query')->willReturn(1);

        $this->repository->createOrUpdate([
            'session_id' => 'sess-ts',
        ]);
    }

    // ------------------------------------------------------------------
    // getActiveSessions()
    // ------------------------------------------------------------------

    public function testGetActiveSessionsReturnsRecentSessions(): void
    {
        $session1 = (object) ['id' => 1, 'session_id' => 's1', 'last_seen_at' => '2026-02-16 12:00:00'];
        $session2 = (object) ['id' => 2, 'session_id' => 's2', 'last_seen_at' => '2026-02-16 11:55:00'];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$session1, $session2]);

        $result = $this->repository->getActiveSessions(15);

        $this->assertCount(2, $result);
    }

    // ------------------------------------------------------------------
    // countUnique()
    // ------------------------------------------------------------------

    public function testCountUniqueReturnsIntegerCount(): void
    {
        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_var')->willReturn('42');

        $result = $this->repository->countUnique('2026-02-01 00:00:00', '2026-02-02 00:00:00');

        $this->assertSame(42, $result);
    }

    // ------------------------------------------------------------------
    // getTrafficSources()
    // ------------------------------------------------------------------

    public function testGetTrafficSourcesReturnsGroupedResults(): void
    {
        $row1 = (object) ['traffic_source' => 'organic', 'session_count' => 100];
        $row2 = (object) ['traffic_source' => 'direct', 'session_count' => 50];

        $this->wpdb->method('prepare')->willReturn('prepared-sql');
        $this->wpdb->method('get_results')->willReturn([$row1, $row2]);

        $result = $this->repository->getTrafficSources('2026-02-01', '2026-02-15');

        $this->assertCount(2, $result);
        $this->assertSame('organic', $result[0]->traffic_source);
        $this->assertSame(100, $result[0]->session_count);
    }
}
