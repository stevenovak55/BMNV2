<?php

declare(strict_types=1);

namespace BMN\Analytics\Tests\Unit\Service;

use BMN\Analytics\Repository\EventRepository;
use BMN\Analytics\Repository\SessionRepository;
use BMN\Analytics\Service\TrackingService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TrackingService.
 */
final class TrackingServiceTest extends TestCase
{
    private EventRepository&MockObject $eventRepo;
    private SessionRepository&MockObject $sessionRepo;
    private TrackingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventRepo = $this->createMock(EventRepository::class);
        $this->sessionRepo = $this->createMock(SessionRepository::class);
        $this->service = new TrackingService($this->eventRepo, $this->sessionRepo);
    }

    // ------------------------------------------------------------------
    // recordEvent()
    // ------------------------------------------------------------------

    public function testRecordEventCreatesEventAndReturnsId(): void
    {
        $this->eventRepo->expects($this->once())
            ->method('create')
            ->willReturn(42);

        // No session_id provided, so no session lookup.
        $result = $this->service->recordEvent([
            'event_type' => 'pageview',
        ]);

        $this->assertSame(42, $result);
    }

    public function testRecordEventReturnsFalseWhenEventTypeIsMissing(): void
    {
        $this->eventRepo->expects($this->never())
            ->method('create');

        $result = $this->service->recordEvent([]);

        $this->assertFalse($result);
    }

    public function testRecordEventUpdatesSessionCounterWhenSessionExists(): void
    {
        $session = (object) ['id' => 10, 'events_count' => 3, 'page_views' => 2];

        $this->eventRepo->method('create')->willReturn(1);

        $this->sessionRepo->expects($this->once())
            ->method('findBySessionId')
            ->with('sess-abc')
            ->willReturn($session);

        $this->sessionRepo->expects($this->once())
            ->method('update')
            ->with(
                10,
                $this->callback(function (array $data): bool {
                    return $data['events_count'] === 4
                        && isset($data['last_seen_at']);
                })
            )
            ->willReturn(true);

        $this->service->recordEvent([
            'event_type' => 'search',
            'session_id' => 'sess-abc',
        ]);
    }

    public function testRecordEventDoesNotUpdateSessionWhenSessionNotFound(): void
    {
        $this->eventRepo->method('create')->willReturn(1);

        $this->sessionRepo->method('findBySessionId')->willReturn(null);
        $this->sessionRepo->expects($this->never())->method('update');

        $this->service->recordEvent([
            'event_type' => 'search',
            'session_id' => 'sess-missing',
        ]);
    }

    // ------------------------------------------------------------------
    // recordPageview()
    // ------------------------------------------------------------------

    public function testRecordPageviewCreatesPageviewEventAndIncrementsPageViews(): void
    {
        $session = (object) ['id' => 5, 'events_count' => 1, 'page_views' => 3];

        // recordEvent will call create and then findBySessionId for events_count.
        // recordPageview will also call findBySessionId for page_views.
        $this->eventRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['event_type'] === 'pageview'
                    && $data['entity_id'] === '/listings'
                    && $data['entity_type'] === 'page';
            }))
            ->willReturn(10);

        // findBySessionId called twice: once by recordEvent, once by recordPageview.
        $this->sessionRepo->method('findBySessionId')
            ->with('sess-pv')
            ->willReturn($session);

        // update called twice: events_count++ by recordEvent, page_views++ by recordPageview.
        $this->sessionRepo->expects($this->exactly(2))
            ->method('update')
            ->willReturn(true);

        $result = $this->service->recordPageview('/listings', 'sess-pv', null, []);

        $this->assertSame(10, $result);
    }

    public function testRecordPageviewReturnsFalseOnCreateFailure(): void
    {
        $this->eventRepo->method('create')->willReturn(false);

        $result = $this->service->recordPageview('/about');

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // recordPropertyView()
    // ------------------------------------------------------------------

    public function testRecordPropertyViewCreatesPropertyViewEvent(): void
    {
        $this->eventRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['event_type'] === 'property_view'
                    && $data['entity_id'] === 'MLS12345'
                    && $data['entity_type'] === 'property';
            }))
            ->willReturn(20);

        $result = $this->service->recordPropertyView('MLS12345');

        $this->assertSame(20, $result);
    }

    // ------------------------------------------------------------------
    // startSession()
    // ------------------------------------------------------------------

    public function testStartSessionCreatesSessionAndReturnsSessionId(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['session_id'] === 'custom-sess'
                    && $data['page_views'] === 0
                    && $data['events_count'] === 0
                    && isset($data['first_seen_at'])
                    && isset($data['last_seen_at']);
            }))
            ->willReturn(1);

        $result = $this->service->startSession([
            'session_id' => 'custom-sess',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertSame('custom-sess', $result);
    }

    public function testStartSessionGeneratesSessionIdWhenNotProvided(): void
    {
        $this->sessionRepo->method('createOrUpdate')->willReturn(1);

        $result = $this->service->startSession([
            'user_agent' => 'Mozilla/5.0',
        ]);

        // Should be a 64-char hex string (32 bytes).
        $this->assertSame(64, strlen($result));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    // ------------------------------------------------------------------
    // Device detection (tested via startSession)
    // ------------------------------------------------------------------

    public function testStartSessionDetectsMobileDevice(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['device_type'] === 'mobile';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'mobile-sess',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
        ]);
    }

    public function testStartSessionDetectsTabletDevice(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['device_type'] === 'tablet';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'tablet-sess',
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X)',
        ]);
    }

    public function testStartSessionDetectsDesktopDevice(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['device_type'] === 'desktop';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'desktop-sess',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        ]);
    }

    // ------------------------------------------------------------------
    // Traffic source classification (tested via startSession)
    // ------------------------------------------------------------------

    public function testStartSessionClassifiesOrganicTraffic(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['traffic_source'] === 'organic';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'organic-sess',
            'referrer'   => 'https://www.google.com/search?q=boston+real+estate',
        ]);
    }

    public function testStartSessionClassifiesSocialTraffic(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['traffic_source'] === 'social';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'social-sess',
            'referrer'   => 'https://www.facebook.com/share/abc',
        ]);
    }

    public function testStartSessionClassifiesDirectTrafficWhenNoReferrer(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['traffic_source'] === 'direct';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'direct-sess',
            'referrer'   => '',
        ]);
    }

    public function testStartSessionClassifiesReferralTrafficForUnknownDomain(): void
    {
        $this->sessionRepo->expects($this->once())
            ->method('createOrUpdate')
            ->with($this->callback(function (array $data): bool {
                return $data['traffic_source'] === 'referral';
            }))
            ->willReturn(1);

        $this->service->startSession([
            'session_id' => 'referral-sess',
            'referrer'   => 'https://www.someotherblog.com/article',
        ]);
    }

    // ------------------------------------------------------------------
    // updateSession()
    // ------------------------------------------------------------------

    public function testUpdateSessionReturnsTrueWhenSessionExists(): void
    {
        $session = (object) ['id' => 8];

        $this->sessionRepo->method('findBySessionId')
            ->with('sess-upd')
            ->willReturn($session);

        $this->sessionRepo->expects($this->once())
            ->method('update')
            ->with(8, ['user_id' => 42])
            ->willReturn(true);

        $result = $this->service->updateSession('sess-upd', ['user_id' => 42]);

        $this->assertTrue($result);
    }

    public function testUpdateSessionReturnsFalseWhenSessionNotFound(): void
    {
        $this->sessionRepo->method('findBySessionId')->willReturn(null);
        $this->sessionRepo->expects($this->never())->method('update');

        $result = $this->service->updateSession('nonexistent', ['user_id' => 1]);

        $this->assertFalse($result);
    }

    // ------------------------------------------------------------------
    // getActiveVisitors()
    // ------------------------------------------------------------------

    public function testGetActiveVisitorsReturnsCount(): void
    {
        $sessions = [
            (object) ['id' => 1],
            (object) ['id' => 2],
            (object) ['id' => 3],
        ];

        $this->sessionRepo->method('getActiveSessions')
            ->with(15)
            ->willReturn($sessions);

        $result = $this->service->getActiveVisitors(15);

        $this->assertSame(3, $result);
    }
}
