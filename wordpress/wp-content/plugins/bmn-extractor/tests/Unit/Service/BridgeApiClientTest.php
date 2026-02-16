<?php

declare(strict_types=1);

namespace BMN\Extractor\Tests\Unit\Service;

use BMN\Extractor\Service\BridgeApiClient;
use PHPUnit\Framework\TestCase;

class BridgeApiClientTest extends TestCase
{
    private const BASE_URL = 'https://api.bridgedataoutput.com/api/v2/OData/';

    protected function setUp(): void
    {
        // Reset all wp_remote_response_* globals.
        foreach (array_keys($GLOBALS) as $key) {
            if (str_starts_with($key, 'wp_remote_response_')) {
                unset($GLOBALS[$key]);
            }
        }
    }

    // ------------------------------------------------------------------
    // hasCredentials
    // ------------------------------------------------------------------

    public function testHasCredentialsReturnsTrueWhenBothSet(): void
    {
        $client = new BridgeApiClient('token123', 'dataset456');
        $this->assertTrue($client->hasCredentials());
    }

    public function testHasCredentialsReturnsFalseWhenTokenEmpty(): void
    {
        $client = new BridgeApiClient('', 'dataset456');
        $this->assertFalse($client->hasCredentials());
    }

    public function testHasCredentialsReturnsFalseWhenDatasetEmpty(): void
    {
        $client = new BridgeApiClient('token123', '');
        $this->assertFalse($client->hasCredentials());
    }

    public function testHasCredentialsReturnsFalseWhenBothEmpty(): void
    {
        $client = new BridgeApiClient('', '');
        $this->assertFalse($client->hasCredentials());
    }

    // ------------------------------------------------------------------
    // buildIncrementalFilter
    // ------------------------------------------------------------------

    public function testBuildIncrementalFilterWithoutTimestamp(): void
    {
        $client = new BridgeApiClient('t', 'd');
        $filter = $client->buildIncrementalFilter(null);

        $this->assertSame('MlgCanView eq true', $filter);
    }

    public function testBuildIncrementalFilterWithTimestamp(): void
    {
        $client = new BridgeApiClient('t', 'd');
        $filter = $client->buildIncrementalFilter('2026-02-15 10:30:00');

        $this->assertStringContainsString('ModificationTimestamp gt', $filter);
        $this->assertStringContainsString('MlgCanView eq true', $filter);
        $this->assertStringContainsString('2026-02-15T10:30:00Z', $filter);
    }

    // ------------------------------------------------------------------
    // buildResyncFilter
    // ------------------------------------------------------------------

    public function testBuildResyncFilter(): void
    {
        $client = new BridgeApiClient('t', 'd');
        $filter = $client->buildResyncFilter();

        $this->assertSame('MlgCanView eq true', $filter);
    }

    // ------------------------------------------------------------------
    // fetchListings
    // ------------------------------------------------------------------

    public function testFetchListingsCallsCallbackWithBatch(): void
    {
        $token = 'mytoken';
        $dataset = 'mydataset';
        $filter = 'MlgCanView eq true';

        $url = $this->buildExpectedUrl($dataset, 'Property', [
            '$filter' => $filter,
            '$top' => '200',
            '$orderby' => 'ModificationTimestamp asc',
        ], $token);

        $this->setMockResponse($url, [
            'value' => [
                ['ListingKey' => 'LK1'],
                ['ListingKey' => 'LK2'],
            ],
        ]);

        $client = new BridgeApiClient($token, $dataset);
        $batches = [];
        $total = $client->fetchListings($filter, function (array $listings, int $totalProcessed) use (&$batches) {
            $batches[] = $listings;
            return null;
        }, 1000);

        $this->assertCount(1, $batches);
        $this->assertCount(2, $batches[0]);
        $this->assertSame(2, $total);
    }

    public function testFetchListingsStopsOnCallbackSignal(): void
    {
        $token = 'mytoken';
        $dataset = 'mydataset';
        $filter = 'test';

        $url = $this->buildExpectedUrl($dataset, 'Property', [
            '$filter' => $filter,
            '$top' => '200',
            '$orderby' => 'ModificationTimestamp asc',
        ], $token);

        $this->setMockResponse($url, [
            'value' => [['ListingKey' => 'LK1']],
            '@odata.nextLink' => 'https://api.example.com/next',
        ]);

        $client = new BridgeApiClient($token, $dataset);
        $callCount = 0;
        $total = $client->fetchListings($filter, function () use (&$callCount) {
            $callCount++;
            return ['stop_session' => true];
        }, 0);

        $this->assertSame(1, $callCount);
    }

    public function testFetchListingsStopsOnEmptyResponse(): void
    {
        $token = 'mytoken';
        $dataset = 'mydataset';
        $filter = 'test';

        $url = $this->buildExpectedUrl($dataset, 'Property', [
            '$filter' => $filter,
            '$top' => '200',
            '$orderby' => 'ModificationTimestamp asc',
        ], $token);

        $this->setMockResponse($url, ['value' => []]);

        $client = new BridgeApiClient($token, $dataset);
        $callCount = 0;
        $total = $client->fetchListings($filter, function () use (&$callCount) {
            $callCount++;
            return null;
        }, 0);

        $this->assertSame(0, $callCount);
        $this->assertSame(0, $total);
    }

    // ------------------------------------------------------------------
    // fetchRelatedResource
    // ------------------------------------------------------------------

    public function testFetchRelatedResourceReturnsEmptyForNoIds(): void
    {
        $client = new BridgeApiClient('t', 'd');
        $result = $client->fetchRelatedResource('Member', 'MemberMlsId', []);

        $this->assertSame([], $result);
    }

    public function testFetchRelatedResourceDeduplicatesIds(): void
    {
        $token = 't';
        $dataset = 'd';

        // The filter for a single deduplicated ID 'A1':
        $filter = "MemberMlsId eq 'A1'";
        $url = $this->buildExpectedUrl($dataset, 'Member', [
            '$filter' => $filter,
            '$top' => '200',
        ], $token);

        $this->setMockResponse($url, ['value' => [['MemberMlsId' => 'A1']]]);

        $client = new BridgeApiClient($token, $dataset);
        $result = $client->fetchRelatedResource('Member', 'MemberMlsId', ['A1', 'A1', 'A1']);
        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // getRequestCount
    // ------------------------------------------------------------------

    public function testGetRequestCountStartsAtZero(): void
    {
        $client = new BridgeApiClient('t', 'd');
        $this->assertSame(0, $client->getRequestCount());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build the exact URL that BridgeApiClient will construct internally.
     * Mirrors the private buildUrl() method logic.
     */
    private function buildExpectedUrl(string $dataset, string $resource, array $params, string $token): string
    {
        $url = self::BASE_URL . $dataset . '/' . $resource;
        $params['access_token'] = $token;
        return $url . '?' . http_build_query($params);
    }

    /**
     * Set a mock HTTP response for a specific URL.
     */
    private function setMockResponse(string $url, array $body): void
    {
        $key = 'wp_remote_response_' . md5($url);
        $GLOBALS[$key] = [
            'response' => ['code' => 200],
            'body' => json_encode($body),
        ];
    }
}
