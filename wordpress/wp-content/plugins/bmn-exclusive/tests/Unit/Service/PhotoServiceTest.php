<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Service;

use BMN\Exclusive\Repository\ExclusiveListingRepository;
use BMN\Exclusive\Repository\ExclusivePhotoRepository;
use BMN\Exclusive\Service\PhotoService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PhotoServiceTest extends TestCase
{
    private ExclusiveListingRepository&MockObject $listingRepo;
    private ExclusivePhotoRepository&MockObject $photoRepo;
    private PhotoService $service;

    protected function setUp(): void
    {
        $this->listingRepo = $this->createMock(ExclusiveListingRepository::class);
        $this->photoRepo = $this->createMock(ExclusivePhotoRepository::class);

        $this->service = new PhotoService(
            $this->listingRepo,
            $this->photoRepo,
        );
    }

    // -- addPhoto --

    public function testAddPhotoFirstPhotoIsPrimary(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('countByListing')->willReturn(0);
        $this->photoRepo->method('findByListing')->willReturn([]);
        $this->photoRepo->method('create')->willReturn(100);
        // refreshListingPhotoInfo will call these:
        // After create, countByListing is called again for refresh, findByListing for refresh.
        // We use willReturn for multiple calls — the mock returns the same value each time.
        $this->listingRepo->method('updatePhotoInfo');

        $result = $this->service->addPhoto(1, 42, 'https://example.com/photo1.jpg');

        $this->assertTrue($result['success']);
        $this->assertSame(100, $result['photo_id']);
    }

    public function testAddPhotoSubsequentPhotoNotPrimary(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $existingPhoto = (object) ['id' => 1, 'sort_order' => 0, 'is_primary' => 1, 'media_url' => 'https://example.com/1.jpg'];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('countByListing')->willReturn(1);
        $this->photoRepo->method('findByListing')->willReturn([$existingPhoto]);
        $this->photoRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['is_primary'] === 0 && $data['sort_order'] === 1;
            }))
            ->willReturn(101);
        $this->listingRepo->method('updatePhotoInfo');

        $result = $this->service->addPhoto(1, 42, 'https://example.com/photo2.jpg');

        $this->assertTrue($result['success']);
    }

    public function testAddPhotoListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $result = $this->service->addPhoto(999, 42, 'https://example.com/photo.jpg');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('listing', $result['errors']);
    }

    public function testAddPhotoWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->addPhoto(1, 42, 'https://example.com/photo.jpg');

        $this->assertFalse($result['success']);
    }

    public function testAddPhotoExceedsMaxPhotos(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('countByListing')->willReturn(100);

        $result = $this->service->addPhoto(1, 42, 'https://example.com/photo.jpg');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('photos', $result['errors']);
    }

    public function testAddPhotoDatabaseFailure(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('countByListing')->willReturn(0);
        $this->photoRepo->method('findByListing')->willReturn([]);
        $this->photoRepo->method('create')->willReturn(false);

        $result = $this->service->addPhoto(1, 42, 'https://example.com/photo.jpg');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('database', $result['errors']);
    }

    // -- deletePhoto --

    public function testDeletePhotoSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $photo = (object) ['id' => 5, 'exclusive_listing_id' => 1, 'is_primary' => 0];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('find')->willReturn($photo);
        $this->photoRepo->method('delete')->willReturn(true);
        $this->photoRepo->method('countByListing')->willReturn(1);
        $this->photoRepo->method('findByListing')->willReturn([
            (object) ['id' => 3, 'is_primary' => 1, 'media_url' => 'https://example.com/3.jpg'],
        ]);
        $this->listingRepo->method('updatePhotoInfo');

        $deleted = $this->service->deletePhoto(1, 42, 5);

        $this->assertTrue($deleted);
    }

    public function testDeletePhotoPrimaryReassigned(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $photo = (object) ['id' => 5, 'exclusive_listing_id' => 1, 'is_primary' => 1];
        $remainingPhoto = (object) ['id' => 3, 'sort_order' => 0, 'is_primary' => 0, 'media_url' => 'https://example.com/3.jpg'];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('find')->willReturn($photo);
        $this->photoRepo->method('delete')->willReturn(true);
        $this->photoRepo->method('findByListing')->willReturn([$remainingPhoto]);
        $this->photoRepo->expects($this->once())->method('setPrimary')->with(1, 3);
        $this->photoRepo->method('countByListing')->willReturn(1);
        $this->listingRepo->method('updatePhotoInfo');

        $this->service->deletePhoto(1, 42, 5);
    }

    public function testDeletePhotoNotFound(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('find')->willReturn(null);

        $deleted = $this->service->deletePhoto(1, 42, 999);

        $this->assertFalse($deleted);
    }

    public function testDeletePhotoWrongListing(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $photo = (object) ['id' => 5, 'exclusive_listing_id' => 99, 'is_primary' => 0];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('find')->willReturn($photo);

        $deleted = $this->service->deletePhoto(1, 42, 5);

        $this->assertFalse($deleted);
    }

    public function testDeletePhotoListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $deleted = $this->service->deletePhoto(999, 42, 5);

        $this->assertFalse($deleted);
    }

    // -- reorderPhotos --

    public function testReorderPhotosSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $photos = [
            (object) ['id' => 3, 'sort_order' => 0, 'is_primary' => 0, 'media_url' => 'https://example.com/3.jpg'],
            (object) ['id' => 1, 'sort_order' => 1, 'is_primary' => 0, 'media_url' => 'https://example.com/1.jpg'],
        ];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('updateSortOrders')->willReturn(true);
        $this->photoRepo->method('findByListing')->willReturn($photos);
        $this->photoRepo->expects($this->once())->method('setPrimary')->with(1, 3);
        $this->photoRepo->method('countByListing')->willReturn(2);
        $this->listingRepo->method('updatePhotoInfo');

        $result = $this->service->reorderPhotos(1, 42, [
            ['id' => 3, 'sort_order' => 0],
            ['id' => 1, 'sort_order' => 1],
        ]);

        $this->assertTrue($result);
    }

    public function testReorderPhotosListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $result = $this->service->reorderPhotos(999, 42, []);

        $this->assertFalse($result);
    }

    public function testReorderPhotosWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->reorderPhotos(1, 42, []);

        $this->assertFalse($result);
    }

    public function testReorderPhotosUpdateFails(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('updateSortOrders')->willReturn(false);

        $result = $this->service->reorderPhotos(1, 42, [['id' => 1, 'sort_order' => 0]]);

        $this->assertFalse($result);
    }

    // -- getPhotos --

    public function testGetPhotosSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $photos = [
            (object) ['id' => 1, 'media_url' => 'https://example.com/1.jpg'],
            (object) ['id' => 2, 'media_url' => 'https://example.com/2.jpg'],
        ];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('findByListing')->willReturn($photos);

        $result = $this->service->getPhotos(1, 42);

        $this->assertNotNull($result);
        $this->assertCount(2, $result);
    }

    public function testGetPhotosListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $result = $this->service->getPhotos(999, 42);

        $this->assertNull($result);
    }

    public function testGetPhotosWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->getPhotos(1, 42);

        $this->assertNull($result);
    }

    // -- MAX_PHOTOS constant --

    public function testMaxPhotosConstant(): void
    {
        $this->assertSame(100, PhotoService::MAX_PHOTOS);
    }
}
