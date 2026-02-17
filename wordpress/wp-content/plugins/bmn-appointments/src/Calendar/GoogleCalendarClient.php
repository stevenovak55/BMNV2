<?php

declare(strict_types=1);

namespace BMN\Appointments\Calendar;

use BMN\Appointments\Repository\StaffRepository;

/**
 * Real Google Calendar API client using wp_remote_post().
 *
 * Uses per-staff OAuth2 tokens with automatic refresh. All datetimes
 * are in RFC3339 format with seconds for Google API compatibility.
 */
final class GoogleCalendarClient implements GoogleCalendarService
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private readonly StaffRepository $staffRepo;
    private readonly string $clientId;
    private readonly string $clientSecret;

    public function __construct(StaffRepository $staffRepo, string $clientId, string $clientSecret)
    {
        $this->staffRepo = $staffRepo;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public function isStaffConnected(int $staffId): bool
    {
        $staff = $this->staffRepo->find($staffId);

        return $staff !== null && !empty($staff->google_refresh_token);
    }

    public function createEvent(int $staffId, array $eventData): array|false
    {
        $accessToken = $this->getAccessToken($staffId);

        if ($accessToken === null) {
            return false;
        }

        $response = wp_remote_post(self::API_BASE . '/calendars/primary/events', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($this->formatEventPayload($eventData)),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && isset($body['id'])) {
            return ['id' => $body['id'], 'htmlLink' => $body['htmlLink'] ?? ''];
        }

        return false;
    }

    public function updateEvent(int $staffId, string $eventId, array $eventData): array|false
    {
        $accessToken = $this->getAccessToken($staffId);

        if ($accessToken === null) {
            return false;
        }

        $response = wp_remote_request(self::API_BASE . '/calendars/primary/events/' . urlencode($eventId), [
            'method'  => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($this->formatEventPayload($eventData)),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300 && isset($body['id'])) {
            return ['id' => $body['id'], 'htmlLink' => $body['htmlLink'] ?? ''];
        }

        return false;
    }

    public function deleteEvent(int $staffId, string $eventId): bool
    {
        $accessToken = $this->getAccessToken($staffId);

        if ($accessToken === null) {
            return true; // Can't connect, treat as deleted.
        }

        $response = wp_remote_request(self::API_BASE . '/calendars/primary/events/' . urlencode($eventId), [
            'method'  => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300 || $code === 404 || $code === 410;
    }

    public function getFreeBusy(int $staffId, string $startDatetime, string $endDatetime): array
    {
        $accessToken = $this->getAccessToken($staffId);

        if ($accessToken === null) {
            return [];
        }

        $response = wp_remote_post(self::API_BASE . '/freeBusy', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode([
                'timeMin'  => $startDatetime,
                'timeMax'  => $endDatetime,
                'items'    => [['id' => 'primary']],
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['calendars']['primary']['busy'] ?? [];
    }

    /**
     * Get a valid access token for a staff member, refreshing if needed.
     */
    private function getAccessToken(int $staffId): ?string
    {
        $staff = $this->staffRepo->find($staffId);

        if ($staff === null || empty($staff->google_refresh_token)) {
            return null;
        }

        // Check if current token is still valid (with 5-minute buffer).
        if (!empty($staff->google_access_token) && !empty($staff->google_token_expires)) {
            $expires = strtotime($staff->google_token_expires);
            if ($expires > ((int) current_time('timestamp') + 300)) {
                return $staff->google_access_token;
            }
        }

        // Refresh the token.
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $staff->google_refresh_token,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            return null;
        }

        $expiresIn = (int) ($body['expires_in'] ?? 3600);
        $expiresAt = date('Y-m-d H:i:s', (int) current_time('timestamp') + $expiresIn);

        $this->staffRepo->updateGoogleTokens(
            $staffId,
            $body['access_token'],
            $body['refresh_token'] ?? $staff->google_refresh_token,
            $expiresAt
        );

        return $body['access_token'];
    }

    /**
     * Format event data into the Google Calendar API payload format.
     */
    private function formatEventPayload(array $eventData): array
    {
        $payload = [
            'summary'     => $eventData['summary'] ?? '',
            'description' => $eventData['description'] ?? '',
            'start'       => [
                'dateTime' => $eventData['start'],
                'timeZone' => wp_timezone()->getName(),
            ],
            'end'         => [
                'dateTime' => $eventData['end'],
                'timeZone' => wp_timezone()->getName(),
            ],
        ];

        if (!empty($eventData['attendees'])) {
            $payload['attendees'] = array_map(
                static fn (string $email): array => ['email' => $email],
                $eventData['attendees']
            );
        }

        return $payload;
    }
}
