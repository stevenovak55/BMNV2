<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Service;

use BMN\Users\Repository\FavoriteRepository;
use BMN\Users\Service\FavoriteService;
use PHPUnit\Framework\TestCase;

final class FavoriteServiceTest extends TestCase
{
    private FavoriteRepository $repo;
    private FavoriteService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(FavoriteRepository::class);
        $this->service = new FavoriteService($this->repo);
    }

    public function testListFavoritesReturnsPaginatedResults(): void
    {
        $this->repo->method('findByUser')->willReturn([
            (object) ['listing_id' => '73464868'],
            (object) ['listing_id' => '73464869'],
        ]);
        $this->repo->method('countByUser')->willReturn(5);

        $result = $this->service->listFavorites(1, 1, 25);

        $this->assertSame(['73464868', '73464869'], $result['listing_ids']);
        $this->assertSame(5, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(25, $result['per_page']);
    }

    public function testListFavoritesCalculatesOffset(): void
    {
        $this->repo->expects($this->once())
            ->method('findByUser')
            ->with(1, 25, 25)
            ->willReturn([]);
        $this->repo->method('countByUser')->willReturn(0);

        $this->service->listFavorites(1, 2, 25);
    }

    public function testToggleFavoriteAddsWhenNotExists(): void
    {
        $this->repo->method('findByUserAndListing')->willReturn(null);
        $this->repo->expects($this->once())->method('addFavorite')->with(1, '73464868');

        $result = $this->service->toggleFavorite(1, '73464868');

        $this->assertTrue($result);
    }

    public function testToggleFavoriteRemovesWhenExists(): void
    {
        $this->repo->method('findByUserAndListing')->willReturn(
            (object) ['id' => 1, 'user_id' => 1, 'listing_id' => '73464868']
        );
        $this->repo->expects($this->once())->method('removeFavorite')->with(1, '73464868');

        $result = $this->service->toggleFavorite(1, '73464868');

        $this->assertFalse($result);
    }

    public function testAddFavoriteIsIdempotent(): void
    {
        $this->repo->method('findByUserAndListing')->willReturn(
            (object) ['id' => 1, 'user_id' => 1, 'listing_id' => '73464868']
        );
        $this->repo->expects($this->never())->method('addFavorite');

        $this->service->addFavorite(1, '73464868');
    }

    public function testAddFavoriteAddsWhenNotExists(): void
    {
        $this->repo->method('findByUserAndListing')->willReturn(null);
        $this->repo->expects($this->once())->method('addFavorite');

        $this->service->addFavorite(1, '73464868');
    }

    public function testRemoveFavoriteDelegatesToRepo(): void
    {
        $this->repo->expects($this->once())->method('removeFavorite')->with(1, '73464868');

        $this->service->removeFavorite(1, '73464868');
    }

    public function testIsFavoritedReturnsTrue(): void
    {
        $this->repo->method('findByUserAndListing')->willReturn(
            (object) ['id' => 1]
        );

        $this->assertTrue($this->service->isFavorited(1, '73464868'));
    }

    public function testIsFavoritedReturnsFalse(): void
    {
        $this->repo->method('findByUserAndListing')->willReturn(null);

        $this->assertFalse($this->service->isFavorited(1, '73464868'));
    }

    public function testGetFavoriteListingIdsDelegatesToRepo(): void
    {
        $this->repo->method('getListingIdsForUser')->willReturn(['73464868', '73464869']);

        $result = $this->service->getFavoriteListingIds(1);

        $this->assertSame(['73464868', '73464869'], $result);
    }

    public function testRemoveAllForUserDelegatesToRepo(): void
    {
        $this->repo->method('removeAllForUser')->willReturn(5);

        $result = $this->service->removeAllForUser(1);

        $this->assertSame(5, $result);
    }

    public function testListFavoritesReturnsEmptyForNewUser(): void
    {
        $this->repo->method('findByUser')->willReturn([]);
        $this->repo->method('countByUser')->willReturn(0);

        $result = $this->service->listFavorites(99, 1, 25);

        $this->assertSame([], $result['listing_ids']);
        $this->assertSame(0, $result['total']);
    }

    public function testToggleFavoriteWithDifferentListings(): void
    {
        $this->repo->method('findByUserAndListing')
            ->willReturnMap([
                [1, '111', null],
                [1, '222', (object) ['id' => 2]],
            ]);
        $this->repo->expects($this->once())->method('addFavorite');

        $added = $this->service->toggleFavorite(1, '111');
        $this->assertTrue($added);
    }
}
