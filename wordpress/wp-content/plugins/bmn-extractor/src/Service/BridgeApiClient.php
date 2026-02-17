<?php

declare(strict_types=1);

namespace BMN\Extractor\Service;

use RuntimeException;

class BridgeApiClient
{
    private const BASE_URL = 'https://api.bridgedataoutput.com/api/v2/OData/';
    private const PAGE_SIZE = 200;
    private const REQUEST_DELAY_MS = 2000; // 2s between requests
    private const MAX_RETRIES = 3;

    private string $serverToken;
    private string $datasetId;
    private int $requestCount = 0;

    public function __construct(?string $serverToken = null, ?string $datasetId = null)
    {
        if ($serverToken !== null && $datasetId !== null) {
            $this->serverToken = $serverToken;
            $this->datasetId = $datasetId;
        } else {
            $credentials = get_option('bmn_bridge_credentials', []);
            $this->serverToken = $credentials['server_token'] ?? '';
            $this->datasetId = $credentials['dataset_id'] ?? '';
        }
    }

    /**
     * Check if credentials are configured.
     */
    public function hasCredentials(): bool
    {
        return $this->serverToken !== '' && $this->datasetId !== '';
    }

    /**
     * Validate credentials by making a test API call.
     * @throws RuntimeException if credentials are invalid.
     */
    public function validateCredentials(): bool
    {
        if (!$this->hasCredentials()) {
            throw new RuntimeException('Bridge API credentials not configured.');
        }

        $url = $this->buildUrl('Property', ['$top' => '1']);
        $response = $this->makeRequest($url);

        return isset($response['value']);
    }

    /**
     * Fetch listings with pagination, calling $callback for each batch.
     *
     * The callback receives (array $listings, int $totalProcessed) and should
     * return an array. If it contains ['stop_session' => true], pagination stops.
     *
     * @param string   $filter       OData $filter expression
     * @param callable $callback     Called with each batch of listings
     * @param int      $sessionLimit Max listings before stopping (0 = unlimited)
     * @return int Total listings processed
     */
    public function fetchListings(string $filter, callable $callback, int $sessionLimit = 1000): int
    {
        $totalProcessed = 0;
        $url = $this->buildUrl('Property', [
            '$filter' => $filter,
            '$top' => (string) self::PAGE_SIZE,
            '$orderby' => 'ModificationTimestamp asc',
        ]);

        while ($url !== null) {
            $response = $this->makeRequest($url);
            $listings = $response['value'] ?? [];

            if (empty($listings)) {
                break;
            }

            $totalProcessed += count($listings);

            $result = $callback($listings, $totalProcessed);

            // Check if session should stop
            if (is_array($result) && ($result['stop_session'] ?? false)) {
                break;
            }

            // Check session limit
            if ($sessionLimit > 0 && $totalProcessed >= $sessionLimit) {
                break;
            }

            // Follow @odata.nextLink for pagination
            $url = $response['@odata.nextLink'] ?? null;

            if ($url !== null) {
                // Append access_token to nextLink if not present
                if (strpos($url, 'access_token') === false) {
                    $url .= (strpos($url, '?') !== false ? '&' : '?') . 'access_token=' . $this->serverToken;
                }
            }
        }

        return $totalProcessed;
    }

    /**
     * Build an OData filter for incremental sync.
     * Fetches listings modified after the given timestamp.
     */
    public function buildIncrementalFilter(?string $lastModified = null): string
    {
        $statusFilter = "(StandardStatus eq 'Active' or StandardStatus eq 'Pending' or StandardStatus eq 'Active Under Contract')";

        if ($lastModified === null) {
            return $statusFilter;
        }

        // Format: 2026-02-16T00:00:00Z
        $formatted = gmdate('Y-m-d\TH:i:s\Z', strtotime($lastModified));
        return "ModificationTimestamp gt {$formatted} and {$statusFilter}";
    }

    /**
     * Build an OData filter for full resync (all active + recently closed).
     */
    public function buildResyncFilter(): string
    {
        return "(StandardStatus eq 'Active' or StandardStatus eq 'Pending' or StandardStatus eq 'Active Under Contract')";
    }

    /**
     * Fetch related resources for a list of keys (e.g., agents, offices, media).
     * Chunks the IDs to avoid URL length limits.
     *
     * @param string $resource  OData resource name (e.g., 'Member', 'Office', 'Media')
     * @param string $keyField  The API field to filter on (e.g., 'MemberMlsId', 'OfficeMlsId')
     * @param array  $ids       Array of IDs to look up
     * @return array All fetched records
     */
    public function fetchRelatedResource(string $resource, string $keyField, array $ids): array
    {
        $ids = array_unique(array_filter($ids));
        if (empty($ids)) {
            return [];
        }

        $allRecords = [];
        $chunks = array_chunk($ids, 50); // 50 per request to avoid URL length limits

        foreach ($chunks as $chunk) {
            $filterParts = array_map(
                fn(string $id) => "{$keyField} eq '{$id}'",
                $chunk
            );
            $filter = implode(' or ', $filterParts);

            $url = $this->buildUrl($resource, [
                '$filter' => $filter,
                '$top' => '200',
            ]);

            $response = $this->makeRequest($url);
            $records = $response['value'] ?? [];
            $allRecords = array_merge($allRecords, $records);
        }

        return $allRecords;
    }

    /**
     * Fetch media for given listing keys.
     */
    public function fetchMediaForListings(array $listingKeys): array
    {
        return $this->fetchRelatedResource('Media', 'ResourceRecordKey', $listingKeys);
    }

    /**
     * Get the request count for this session.
     */
    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Build a full API URL with query parameters.
     */
    private function buildUrl(string $resource, array $params = []): string
    {
        $url = self::BASE_URL . $this->datasetId . '/' . $resource;
        $params['access_token'] = $this->serverToken;

        return $url . '?' . http_build_query($params);
    }

    /**
     * Make an HTTP GET request with retry logic and rate limiting.
     *
     * @throws RuntimeException on permanent failure
     */
    private function makeRequest(string $url): array
    {
        $retries = 0;
        $delay = self::REQUEST_DELAY_MS;

        while ($retries <= self::MAX_RETRIES) {
            // Rate limiting delay (skip on first request)
            if ($this->requestCount > 0) {
                usleep($delay * 1000);
            }

            $this->requestCount++;

            $response = wp_remote_get($url, [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                $retries++;
                $delay *= 2; // Exponential backoff
                if ($retries > self::MAX_RETRIES) {
                    throw new RuntimeException(
                        'Bridge API request failed: ' . $response->get_error_message()
                    );
                }
                continue;
            }

            $statusCode = (int) wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($statusCode === 429) {
                // Rate limited - exponential backoff
                $retries++;
                $delay *= 2;
                if ($retries > self::MAX_RETRIES) {
                    throw new RuntimeException('Bridge API rate limit exceeded after retries.');
                }
                continue;
            }

            if ($statusCode >= 400) {
                throw new RuntimeException(
                    "Bridge API error (HTTP {$statusCode}): " . substr($body, 0, 500)
                );
            }

            $decoded = json_decode($body, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'Bridge API returned invalid JSON: ' . json_last_error_msg()
                );
            }

            return $decoded;
        }

        throw new RuntimeException('Bridge API request failed after max retries.');
    }
}
