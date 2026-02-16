<?php

declare(strict_types=1);

namespace BMN\Users\Tests\Unit\Service;

use BMN\Users\Repository\SavedSearchRepository;
use BMN\Users\Service\SavedSearchService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SavedSearchServiceTest extends TestCase
{
    private SavedSearchRepository $repo;
    private SavedSearchService $service;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SavedSearchRepository::class);
        $this->service = new SavedSearchService($this->repo);
    }

    private function makeSearch(array $overrides = []): object
    {
        $search = new \stdClass();
        $search->id = $overrides['id'] ?? 1;
        $search->user_id = $overrides['user_id'] ?? 1;
        $search->name = $overrides['name'] ?? 'Back Bay Condos';
        $search->filters = $overrides['filters'] ?? '{"city":"Boston"}';
        $search->polygon_shapes = $overrides['polygon_shapes'] ?? null;
        $search->is_active = $overrides['is_active'] ?? 1;
        $search->last_alert_at = $overrides['last_alert_at'] ?? null;
        $search->result_count = $overrides['result_count'] ?? 0;
        $search->new_count = $overrides['new_count'] ?? 0;
        $search->created_at = $overrides['created_at'] ?? '2026-02-16 10:00:00';
        $search->updated_at = $overrides['updated_at'] ?? '2026-02-16 10:00:00';

        return $search;
    }

    public function testListSearchesReturnsFormattedResults(): void
    {
        $this->repo->method('findByUser')->willReturn([$this->makeSearch()]);

        $result = $this->service->listSearches(1);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Back Bay Condos', $result[0]['name']);
        $this->assertSame(['city' => 'Boston'], $result[0]['filters']);
    }

    public function testCreateSearchReturnsId(): void
    {
        $this->repo->method('countByUser')->willReturn(0);
        $this->repo->method('create')->willReturn(42);

        $id = $this->service->createSearch(1, 'My Search', ['city' => 'Boston']);

        $this->assertSame(42, $id);
    }

    public function testCreateSearchThrowsWhenLimitReached(): void
    {
        $this->repo->method('countByUser')->willReturn(25);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Maximum of 25 saved searches reached.');

        $this->service->createSearch(1, 'One Too Many', ['city' => 'Boston']);
    }

    public function testCreateSearchThrowsOnDbFailure(): void
    {
        $this->repo->method('countByUser')->willReturn(0);
        $this->repo->method('create')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create saved search.');

        $this->service->createSearch(1, 'Fail', []);
    }

    public function testGetSearchReturnsFormattedSearch(): void
    {
        $this->repo->method('find')->willReturn($this->makeSearch());

        $result = $this->service->getSearch(1, 1);

        $this->assertSame(1, $result['id']);
        $this->assertSame('Back Bay Condos', $result['name']);
    }

    public function testGetSearchThrowsOnOwnershipMismatch(): void
    {
        $this->repo->method('find')->willReturn($this->makeSearch(['user_id' => 2]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Saved search not found.');

        $this->service->getSearch(1, 1);
    }

    public function testGetSearchThrowsWhenNotFound(): void
    {
        $this->repo->method('find')->willReturn(null);

        $this->expectException(RuntimeException::class);

        $this->service->getSearch(1, 999);
    }

    public function testUpdateSearchUpdatesFields(): void
    {
        $this->repo->method('find')->willReturn($this->makeSearch());
        $this->repo->expects($this->once())->method('update')->with(1, $this->callback(
            fn (array $data) => $data['name'] === 'Updated Name'
        ))->willReturn(true);

        $result = $this->service->updateSearch(1, 1, ['name' => 'Updated Name']);

        $this->assertTrue($result);
    }

    public function testUpdateSearchThrowsOnOwnershipMismatch(): void
    {
        $this->repo->method('find')->willReturn($this->makeSearch(['user_id' => 2]));

        $this->expectException(RuntimeException::class);

        $this->service->updateSearch(1, 1, ['name' => 'Hack']);
    }

    public function testDeleteSearchDelegatesToRepo(): void
    {
        $this->repo->method('find')->willReturn($this->makeSearch());
        $this->repo->expects($this->once())->method('delete')->with(1)->willReturn(true);

        $result = $this->service->deleteSearch(1, 1);

        $this->assertTrue($result);
    }

    public function testDeleteSearchThrowsOnOwnershipMismatch(): void
    {
        $this->repo->method('find')->willReturn($this->makeSearch(['user_id' => 2]));

        $this->expectException(RuntimeException::class);

        $this->service->deleteSearch(1, 1);
    }

    public function testGetSearchesForAlertProcessingDelegates(): void
    {
        $this->repo->method('findActiveForAlerts')->willReturn([$this->makeSearch()]);

        $result = $this->service->getSearchesForAlertProcessing();

        $this->assertCount(1, $result);
    }

    public function testMarkAlertProcessedDelegates(): void
    {
        $this->repo->expects($this->once())->method('updateAlertTimestamp')->with(1, 100, 5);

        $this->service->markAlertProcessed(1, 100, 5);
    }

    public function testRemoveAllForUserDelegates(): void
    {
        $this->repo->method('removeAllForUser')->willReturn(3);

        $this->assertSame(3, $this->service->removeAllForUser(1));
    }
}
