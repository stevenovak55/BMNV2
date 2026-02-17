<?php

declare(strict_types=1);

namespace BMN\Analytics\Service;

use BMN\Analytics\Repository\EventRepository;
use BMN\Analytics\Repository\SessionRepository;

/**
 * Service for recording analytics events and managing sessions.
 *
 * Provides both low-level event recording and convenient shorthand
 * methods for common event types (pageviews, property views).
 */
class TrackingService
{
    private readonly EventRepository $eventRepo;
    private readonly SessionRepository $sessionRepo;

    public function __construct(EventRepository $eventRepo, SessionRepository $sessionRepo)
    {
        $this->eventRepo = $eventRepo;
        $this->sessionRepo = $sessionRepo;
    }

    /**
     * Record a generic analytics event.
     *
     * Required: event_type
     * Optional: session_id, user_id, entity_id, entity_type, metadata,
     *           ip_address, user_agent, referrer
     *
     * Automatically updates the associated session's events_count and last_seen_at.
     *
     * @param array<string, mixed> $eventData Event data.
     *
     * @return int|false The event ID, or false on failure.
     */
    public function recordEvent(array $eventData): int|false
    {
        if (empty($eventData['event_type'])) {
            return false;
        }

        $eventId = $this->eventRepo->create($eventData);

        // Update session counters if a session_id was provided.
        if ($eventId !== false && !empty($eventData['session_id'])) {
            $session = $this->sessionRepo->findBySessionId($eventData['session_id']);

            if ($session !== null) {
                $this->sessionRepo->update((int) $session->id, [
                    'events_count' => (int) $session->events_count + 1,
                    'last_seen_at' => current_time('mysql'),
                ]);
            }
        }

        return $eventId;
    }

    /**
     * Record a pageview event (shorthand).
     *
     * Also increments the session's page_views counter.
     *
     * @param string      $path      The page path being viewed.
     * @param string|null $sessionId Session identifier.
     * @param int|null    $userId    WordPress user ID if authenticated.
     * @param array<string, mixed> $extra     Additional event data.
     *
     * @return int|false The event ID, or false on failure.
     */
    public function recordPageview(string $path, ?string $sessionId = null, ?int $userId = null, array $extra = []): int|false
    {
        $eventData = array_merge($extra, [
            'event_type'  => 'pageview',
            'entity_id'   => $path,
            'entity_type' => 'page',
            'session_id'  => $sessionId,
            'user_id'     => $userId,
        ]);

        $eventId = $this->recordEvent($eventData);

        // Increment page_views on the session.
        if ($eventId !== false && $sessionId !== null) {
            $session = $this->sessionRepo->findBySessionId($sessionId);

            if ($session !== null) {
                $this->sessionRepo->update((int) $session->id, [
                    'page_views' => (int) $session->page_views + 1,
                ]);
            }
        }

        return $eventId;
    }

    /**
     * Record a property view event (shorthand).
     *
     * Uses the listing_id (MLS number) as the entity_id.
     *
     * @param string      $listingId The MLS listing ID.
     * @param string|null $sessionId Session identifier.
     * @param int|null    $userId    WordPress user ID if authenticated.
     * @param array<string, mixed> $extra     Additional event data.
     *
     * @return int|false The event ID, or false on failure.
     */
    public function recordPropertyView(string $listingId, ?string $sessionId = null, ?int $userId = null, array $extra = []): int|false
    {
        $eventData = array_merge($extra, [
            'event_type'  => 'property_view',
            'entity_id'   => $listingId,
            'entity_type' => 'property',
            'session_id'  => $sessionId,
            'user_id'     => $userId,
        ]);

        return $this->recordEvent($eventData);
    }

    /**
     * Start a new analytics session.
     *
     * Creates a session record with device detection and traffic source
     * classification. Returns the generated session_id.
     *
     * @param array<string, mixed> $sessionData Session data.
     *
     * @return string The session_id.
     */
    public function startSession(array $sessionData): string
    {
        $sessionId = $sessionData['session_id'] ?? bin2hex(random_bytes(32));
        $now = current_time('mysql');

        $userAgent = $sessionData['user_agent'] ?? '';
        $referrer = $sessionData['referrer'] ?? '';

        $data = [
            'session_id'     => $sessionId,
            'user_id'        => $sessionData['user_id'] ?? null,
            'ip_address'     => $sessionData['ip_address'] ?? null,
            'user_agent'     => $userAgent,
            'device_type'    => $sessionData['device_type'] ?? $this->detectDeviceType($userAgent),
            'browser'        => $sessionData['browser'] ?? null,
            'platform'       => $sessionData['platform'] ?? null,
            'referrer'       => $referrer,
            'traffic_source' => $sessionData['traffic_source'] ?? $this->classifyTrafficSource($referrer),
            'landing_page'   => $sessionData['landing_page'] ?? null,
            'page_views'     => 0,
            'events_count'   => 0,
            'first_seen_at'  => $now,
            'last_seen_at'   => $now,
        ];

        $this->sessionRepo->createOrUpdate($data);

        return $sessionId;
    }

    /**
     * Update an existing session.
     *
     * @param string               $sessionId The session_id to update.
     * @param array<string, mixed> $data      Fields to update.
     */
    public function updateSession(string $sessionId, array $data): bool
    {
        $session = $this->sessionRepo->findBySessionId($sessionId);

        if ($session === null) {
            return false;
        }

        return $this->sessionRepo->update((int) $session->id, $data);
    }

    /**
     * Get the number of currently active visitors.
     *
     * @param int $minutes Consider sessions active if seen within this many minutes.
     */
    public function getActiveVisitors(int $minutes = 15): int
    {
        $sessions = $this->sessionRepo->getActiveSessions($minutes);

        return count($sessions);
    }

    /**
     * Detect device type from user agent string.
     *
     * Simple heuristic: checks for mobile/tablet keywords.
     */
    private function detectDeviceType(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Classify traffic source from referrer URL.
     *
     * Simple heuristic based on common referrer patterns.
     */
    private function classifyTrafficSource(string $referrer): string
    {
        if ($referrer === '' || $referrer === null) {
            return 'direct';
        }

        $referrerLower = strtolower($referrer);

        // Search engines.
        $searchEngines = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.'];
        foreach ($searchEngines as $engine) {
            if (str_contains($referrerLower, $engine)) {
                return 'organic';
            }
        }

        // Social media.
        $socialNetworks = ['facebook.', 'twitter.', 'instagram.', 'linkedin.', 'pinterest.', 'tiktok.', 'youtube.'];
        foreach ($socialNetworks as $network) {
            if (str_contains($referrerLower, $network)) {
                return 'social';
            }
        }

        // Paid traffic (check for common UTM/ad parameters in the referrer).
        if (str_contains($referrerLower, 'gclid') || str_contains($referrerLower, 'fbclid') || str_contains($referrerLower, 'utm_medium=cpc')) {
            return 'paid';
        }

        return 'referral';
    }
}
