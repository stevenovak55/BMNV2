<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Controller;

use BMN\Exclusive\Api\Controllers\PhotoController;
use BMN\Exclusive\Service\PhotoService;
use BMN\Platform\Auth\AuthMiddleware;
use BMN\Platform\Auth\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

final class PhotoControllerTest extends TestCase
{
    private PhotoController $controller;
    private PhotoService&MockObject $photoService;

    protected function setUp(): void
    {
        $this->photoService = $this->createMock(PhotoService::class);

        $authService = $this->createMock(AuthService::class);
        $authMiddleware = new AuthMiddleware($authService);

        $this->controller = new PhotoController(
            $this->photoService,
            $authMiddleware,
        );

        wp_set_current_user(42, 'testuser');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['current_user']);
    }

    // -- getPhotos --

    public function testGetPhotosSuccess(): void
    {
        $this->photoService->method('getPhotos')->willReturn([
            (object) ['id' => 1, 'media_url' => 'https://example.com/1.jpg'],
            (object) ['id' => 2, 'media_url' => 'https://example.com/2.jpg'],
        ]);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/1/photos');
        $request->set_param('id', 1);

        $response = $this->controller->getPhotos($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    public function testGetPhotosNotFound(): void
    {
        $this->photoService->method('getPhotos')->willReturn(null);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/999/photos');
        $request->set_param('id', 999);

        $response = $this->controller->getPhotos($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testGetPhotosUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('GET', '/bmn/v1/exclusive/1/photos');
        $request->set_param('id', 1);

        $response = $this->controller->getPhotos($request);

        $this->assertSame(401, $response->get_status());
    }

    // -- addPhoto --

    public function testAddPhotoSuccess(): void
    {
        $this->photoService->method('addPhoto')->willReturn([
            'success' => true,
            'photo_id' => 100,
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/exclusive/1/photos');
        $request->set_param('id', 1);
        $request->set_param('media_url', 'https://example.com/new.jpg');

        $response = $this->controller->addPhoto($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertSame(100, $data['data']['photo_id']);
    }

    public function testAddPhotoError(): void
    {
        $this->photoService->method('addPhoto')->willReturn([
            'success' => false,
            'errors' => ['photos' => 'Maximum of 100 photos per listing.'],
        ]);

        $request = new WP_REST_Request('POST', '/bmn/v1/exclusive/1/photos');
        $request->set_param('id', 1);
        $request->set_param('media_url', 'https://example.com/new.jpg');

        $response = $this->controller->addPhoto($request);

        $this->assertSame(422, $response->get_status());
        $data = $response->get_data();
        $this->assertFalse($data['success']);
    }

    public function testAddPhotoUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('POST', '/bmn/v1/exclusive/1/photos');
        $request->set_param('id', 1);
        $request->set_param('media_url', 'https://example.com/new.jpg');

        $response = $this->controller->addPhoto($request);

        $this->assertSame(401, $response->get_status());
    }

    // -- deletePhoto --

    public function testDeletePhotoSuccess(): void
    {
        $this->photoService->method('deletePhoto')->willReturn(true);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/exclusive/1/photos/5');
        $request->set_param('id', 1);
        $request->set_param('photo_id', 5);

        $response = $this->controller->deletePhoto($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['data']['deleted']);
    }

    public function testDeletePhotoNotFound(): void
    {
        $this->photoService->method('deletePhoto')->willReturn(false);

        $request = new WP_REST_Request('DELETE', '/bmn/v1/exclusive/1/photos/999');
        $request->set_param('id', 1);
        $request->set_param('photo_id', 999);

        $response = $this->controller->deletePhoto($request);

        $this->assertSame(404, $response->get_status());
    }

    // -- reorderPhotos --

    public function testReorderPhotosSuccess(): void
    {
        $this->photoService->method('reorderPhotos')->willReturn(true);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/1/photos/order');
        $request->set_param('id', 1);
        $request->set_param('photos', [
            ['id' => 3, 'sort_order' => 0],
            ['id' => 1, 'sort_order' => 1],
        ]);

        $response = $this->controller->reorderPhotos($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['data']['reordered']);
    }

    public function testReorderPhotosFailure(): void
    {
        $this->photoService->method('reorderPhotos')->willReturn(false);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/1/photos/order');
        $request->set_param('id', 1);
        $request->set_param('photos', []);

        $response = $this->controller->reorderPhotos($request);

        $this->assertSame(422, $response->get_status());
    }

    public function testReorderPhotosUnauthenticated(): void
    {
        wp_set_current_user(0);

        $request = new WP_REST_Request('PUT', '/bmn/v1/exclusive/1/photos/order');
        $request->set_param('id', 1);

        $response = $this->controller->reorderPhotos($request);

        $this->assertSame(401, $response->get_status());
    }
}
