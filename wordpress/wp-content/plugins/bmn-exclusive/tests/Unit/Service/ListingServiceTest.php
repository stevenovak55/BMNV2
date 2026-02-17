<?php

declare(strict_types=1);

namespace BMN\Exclusive\Tests\Unit\Service;

use BMN\Exclusive\Repository\ExclusiveListingRepository;
use BMN\Exclusive\Repository\ExclusivePhotoRepository;
use BMN\Exclusive\Service\ListingService;
use BMN\Exclusive\Service\ValidationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ListingServiceTest extends TestCase
{
    private ExclusiveListingRepository&MockObject $listingRepo;
    private ExclusivePhotoRepository&MockObject $photoRepo;
    private ValidationService $validator;
    private ListingService $service;

    protected function setUp(): void
    {
        $this->listingRepo = $this->createMock(ExclusiveListingRepository::class);
        $this->photoRepo = $this->createMock(ExclusivePhotoRepository::class);
        $this->validator = new ValidationService(); // Pure class, use real instance.

        $this->service = new ListingService(
            $this->listingRepo,
            $this->photoRepo,
            $this->validator,
        );
    }

    // -- createListing --

    public function testCreateListingSuccess(): void
    {
        $this->listingRepo->method('getNextListingId')->willReturn(1);
        $this->listingRepo->method('create')->willReturn(10);

        $result = $this->service->createListing(42, [
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(10, $result['listing_id']);
        $this->assertSame(1, $result['listing_number']);
    }

    public function testCreateListingValidationFailure(): void
    {
        $result = $this->service->createListing(42, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertNotEmpty($result['errors']);
    }

    public function testCreateListingDatabaseFailure(): void
    {
        $this->listingRepo->method('getNextListingId')->willReturn(1);
        $this->listingRepo->method('create')->willReturn(false);

        $result = $this->service->createListing(42, [
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('database', $result['errors']);
    }

    public function testCreateListingDefaultStatusIsDraft(): void
    {
        $this->listingRepo->method('getNextListingId')->willReturn(1);
        $this->listingRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['status'] === 'draft';
            }))
            ->willReturn(10);

        $this->service->createListing(42, [
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);
    }

    public function testCreateListingSetsAgentUserIdAndListingId(): void
    {
        $this->listingRepo->method('getNextListingId')->willReturn(5);
        $this->listingRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data['agent_user_id'] === 42 && $data['listing_id'] === 5;
            }))
            ->willReturn(10);

        $this->service->createListing(42, [
            'property_type' => 'Residential',
            'list_price' => 500000,
            'street_number' => '123',
            'street_name' => 'Main St',
            'city' => 'Boston',
            'state' => 'MA',
            'postal_code' => '02101',
        ]);
    }

    // -- updateListing --

    public function testUpdateListingSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->listingRepo->method('update')->willReturn(true);

        $result = $this->service->updateListing(1, 42, ['city' => 'Cambridge']);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['updated']);
    }

    public function testUpdateListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $result = $this->service->updateListing(999, 42, ['city' => 'Cambridge']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('listing', $result['errors']);
    }

    public function testUpdateListingWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->updateListing(1, 42, ['city' => 'Cambridge']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('listing', $result['errors']);
    }

    public function testUpdateListingValidationFailure(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->updateListing(1, 42, ['list_price' => -100]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('list_price', $result['errors']);
    }

    public function testUpdateListingInvalidStatusTransition(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->updateListing(1, 42, ['status' => 'pending']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testUpdateListingValidStatusTransition(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->listingRepo->method('update')->willReturn(true);

        $result = $this->service->updateListing(1, 42, ['status' => 'active']);

        $this->assertTrue($result['success']);
    }

    public function testUpdateListingPreventsChangingAgentUserIdAndListingId(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->listingRepo->expects($this->once())
            ->method('update')
            ->with(1, $this->callback(function (array $data): bool {
                return !isset($data['agent_user_id']) && !isset($data['listing_id']);
            }))
            ->willReturn(true);

        $this->service->updateListing(1, 42, [
            'city' => 'Cambridge',
            'agent_user_id' => 99,
            'listing_id' => 999,
        ]);
    }

    // -- deleteListing --

    public function testDeleteListingSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->expects($this->once())->method('deleteByListing')->with(1);
        $this->listingRepo->method('delete')->willReturn(true);

        $deleted = $this->service->deleteListing(1, 42);

        $this->assertTrue($deleted);
    }

    public function testDeleteListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $deleted = $this->service->deleteListing(999, 42);

        $this->assertFalse($deleted);
    }

    public function testDeleteListingWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99];
        $this->listingRepo->method('find')->willReturn($listing);

        $deleted = $this->service->deleteListing(1, 42);

        $this->assertFalse($deleted);
    }

    public function testDeleteListingCascadesPhotos(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->listingRepo->method('delete')->willReturn(true);

        // Verify photos are deleted BEFORE listing.
        $deleteOrder = [];
        $this->photoRepo->expects($this->once())
            ->method('deleteByListing')
            ->with(1)
            ->willReturnCallback(function () use (&$deleteOrder) {
                $deleteOrder[] = 'photos';
                return true;
            });
        $this->listingRepo->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturnCallback(function () use (&$deleteOrder) {
                $deleteOrder[] = 'listing';
                return true;
            });

        $this->service->deleteListing(1, 42);

        $this->assertSame(['photos', 'listing'], $deleteOrder);
    }

    // -- getListing --

    public function testGetListingSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'city' => 'Boston'];
        $photos = [(object) ['id' => 1, 'media_url' => 'https://example.com/1.jpg']];

        $this->listingRepo->method('find')->willReturn($listing);
        $this->photoRepo->method('findByListing')->with(1)->willReturn($photos);

        $result = $this->service->getListing(1, 42);

        $this->assertNotNull($result);
        $this->assertSame('Boston', $result['city']);
        $this->assertCount(1, $result['photos']);
    }

    public function testGetListingNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $result = $this->service->getListing(999, 42);

        $this->assertNull($result);
    }

    public function testGetListingWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->getListing(1, 42);

        $this->assertNull($result);
    }

    // -- getAgentListings --

    public function testGetAgentListings(): void
    {
        $listings = [
            (object) ['id' => 1, 'city' => 'Boston'],
            (object) ['id' => 2, 'city' => 'Cambridge'],
        ];
        $this->listingRepo->method('findByAgent')->willReturn($listings);
        $this->listingRepo->method('countByAgent')->willReturn(30);

        $result = $this->service->getAgentListings(42, 1, 20);

        $this->assertCount(2, $result['listings']);
        $this->assertSame(30, $result['total']);
    }

    public function testGetAgentListingsWithStatusFilter(): void
    {
        $this->listingRepo->expects($this->once())
            ->method('findByAgent')
            ->with(42, 20, 0, 'active')
            ->willReturn([]);
        $this->listingRepo->expects($this->once())
            ->method('countByAgent')
            ->with(42, 'active')
            ->willReturn(0);

        $this->service->getAgentListings(42, 1, 20, 'active');
    }

    public function testGetAgentListingsOffset(): void
    {
        $this->listingRepo->expects($this->once())
            ->method('findByAgent')
            ->with(42, 10, 20, null)
            ->willReturn([]);
        $this->listingRepo->method('countByAgent')->willReturn(0);

        $this->service->getAgentListings(42, 3, 10);
    }

    // -- updateStatus --

    public function testUpdateStatusSuccess(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);
        $this->listingRepo->method('update')->willReturn(true);

        $result = $this->service->updateStatus(1, 42, 'active');

        $this->assertTrue($result['success']);
    }

    public function testUpdateStatusNotFound(): void
    {
        $this->listingRepo->method('find')->willReturn(null);

        $result = $this->service->updateStatus(999, 42, 'active');

        $this->assertFalse($result['success']);
    }

    public function testUpdateStatusInvalidStatus(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->updateStatus(1, 42, 'bogus');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testUpdateStatusInvalidTransition(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 42, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->updateStatus(1, 42, 'closed');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('status', $result['errors']);
    }

    public function testUpdateStatusWrongOwner(): void
    {
        $listing = (object) ['id' => 1, 'agent_user_id' => 99, 'status' => 'draft'];
        $this->listingRepo->method('find')->willReturn($listing);

        $result = $this->service->updateStatus(1, 42, 'active');

        $this->assertFalse($result['success']);
    }
}
