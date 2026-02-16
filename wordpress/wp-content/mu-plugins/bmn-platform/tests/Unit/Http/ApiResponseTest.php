<?php

declare(strict_types=1);

namespace BMN\Platform\Tests\Unit\Http;

use BMN\Platform\Http\ApiResponse;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $response = ApiResponse::success(['name' => 'Test']);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertSame(['name' => 'Test'], $data['data']);
        $this->assertSame(200, $response->get_status());
    }

    public function testSuccessWithMeta(): void
    {
        $response = ApiResponse::success(['id' => 1], ['cached' => true]);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('meta', $data);
        $this->assertTrue($data['meta']['cached']);
    }

    public function testSuccessOmitsMetaWhenEmpty(): void
    {
        $response = ApiResponse::success(['id' => 1]);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('meta', $data);
    }

    public function testErrorResponse(): void
    {
        $response = ApiResponse::error('Not found', 404);
        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertNull($data['data']);
        $this->assertSame('Not found', $data['meta']['error']);
        $this->assertSame(404, $data['meta']['code']);
        $this->assertSame(404, $response->get_status());
    }

    public function testErrorResponseDefaultStatus(): void
    {
        $response = ApiResponse::error('Bad request');
        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertSame(400, $data['meta']['code']);
        $this->assertSame(400, $response->get_status());
    }

    public function testErrorResponseWithDetails(): void
    {
        $details = ['field' => 'email', 'reason' => 'invalid'];
        $response = ApiResponse::error('Validation failed', 422, $details);
        $data = $response->get_data();

        $this->assertFalse($data['success']);
        $this->assertSame('Validation failed', $data['meta']['error']);
        $this->assertSame($details, $data['meta']['details']);
    }

    public function testPaginatedResponse(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = ApiResponse::paginated($items, 100, 1, 50);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
        $this->assertSame(100, $data['meta']['total']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertSame(50, $data['meta']['per_page']);
        $this->assertSame(2, $data['meta']['total_pages']);
    }

    public function testPaginatedResponseSetsPaginationHeaders(): void
    {
        $response = ApiResponse::paginated([['id' => 1]], 50, 1, 25);
        $headers = $response->get_headers();

        $this->assertSame('50', $headers['X-WP-Total']);
        $this->assertSame('2', $headers['X-WP-TotalPages']);
    }

    public function testPaginatedResponseWithExtraMeta(): void
    {
        $response = ApiResponse::paginated([['id' => 1]], 10, 1, 10, ['cached' => true]);
        $data = $response->get_data();

        $this->assertTrue($data['meta']['cached']);
        $this->assertSame(10, $data['meta']['total']);
    }
}
