<?php

declare(strict_types=1);

namespace BMN\Schools\Tests\Unit\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Schools\Repository\SchoolDistrictRepository;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Service\SchoolFilterService;
use PHPUnit\Framework\TestCase;

final class SchoolFilterServiceTest extends TestCase
{
    private SchoolRepository $schoolRepo;
    private SchoolDistrictRepository $districtRepo;
    private SchoolRankingRepository $rankingRepo;
    private GeocodingService $geocoding;
    private CacheService $cache;
    private SchoolFilterService $service;

    protected function setUp(): void
    {
        $this->schoolRepo = $this->createMock(SchoolRepository::class);
        $this->districtRepo = $this->createMock(SchoolDistrictRepository::class);
        $this->rankingRepo = $this->createMock(SchoolRankingRepository::class);
        $this->geocoding = $this->createMock(GeocodingService::class);
        $this->cache = $this->createMock(CacheService::class);

        // Make cache transparent.
        $this->cache->method('remember')->willReturnCallback(
            fn ($key, $ttl, $callback) => $callback()
        );

        $this->rankingRepo->method('getLatestDataYear')->willReturn(2025);

        $this->service = new SchoolFilterService(
            $this->schoolRepo,
            $this->districtRepo,
            $this->rankingRepo,
            $this->geocoding,
            $this->cache,
        );
    }

    // ------------------------------------------------------------------
    // Empty criteria / empty properties
    // ------------------------------------------------------------------

    public function testEmptyCriteriaReturnsAllProperties(): void
    {
        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, []);
        $this->assertCount(1, $result);
    }

    public function testEmptyPropertiesReturnsEmpty(): void
    {
        $result = $this->service->filter([], ['school_grade' => 'A']);
        $this->assertSame([], $result);
    }

    public function testNullCriteriaValuesAreIgnored(): void
    {
        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => null]);
        $this->assertCount(1, $result);
    }

    // ------------------------------------------------------------------
    // school_grade filter
    // ------------------------------------------------------------------

    public function testSchoolGradeFilterKeepsPropertyWithGoodSchool(): void
    {
        $school = $this->makeSchool(1, 'Winchester High', 'High');
        $this->setupNearbySchools([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(
            (object) ['letter_grade' => 'A', 'composite_score' => 85.0]
        );

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => 'A']);

        $this->assertCount(1, $result);
    }

    public function testSchoolGradeFilterRemovesPropertyWithLowGradeSchools(): void
    {
        $school = $this->makeSchool(1, 'Low School', 'High');
        $this->setupNearbySchools([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(
            (object) ['letter_grade' => 'C', 'composite_score' => 50.0]
        );

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => 'A']);

        $this->assertCount(0, $result);
    }

    public function testSchoolGradeComparisonAPlusGreaterThanA(): void
    {
        $school = $this->makeSchool(1, 'Top School', 'High');
        $this->setupNearbySchools([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(
            (object) ['letter_grade' => 'A+', 'composite_score' => 95.0]
        );

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => 'A']);

        $this->assertCount(1, $result); // A+ >= A, so passes.
    }

    public function testSchoolGradeComparisonBPlusLessThanAMinus(): void
    {
        $school = $this->makeSchool(1, 'OK School', 'High');
        $this->setupNearbySchools([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(
            (object) ['letter_grade' => 'B+', 'composite_score' => 65.0]
        );

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => 'A-']);

        $this->assertCount(0, $result); // B+ < A-, so fails.
    }

    public function testSchoolGradePassesIfAnyNearbySchoolQualifies(): void
    {
        $schools = [
            $this->makeSchool(1, 'Bad School', 'Elementary'),
            $this->makeSchool(2, 'Great School', 'High'),
        ];
        $this->setupNearbySchools($schools);

        $this->rankingRepo->method('getRanking')->willReturnCallback(
            function (int $id): ?object {
                if ($id === 1) {
                    return (object) ['letter_grade' => 'C'];
                }
                return (object) ['letter_grade' => 'A'];
            }
        );

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => 'A']);

        $this->assertCount(1, $result); // Great School qualifies.
    }

    // ------------------------------------------------------------------
    // school_district filter
    // ------------------------------------------------------------------

    public function testSchoolDistrictMatchesCityName(): void
    {
        $this->districtRepo->method('getRegionalMapping')->willReturn(null);
        $this->setupNearbySchools([]); // District check doesn't need nearby schools.

        $property = $this->makeProperty(1);
        $property->city = 'Winchester';

        $result = $this->service->filter([$property], ['school_district' => 'Winchester']);

        $this->assertCount(1, $result);
    }

    public function testSchoolDistrictUsesRegionalMapping(): void
    {
        // Nahant maps to Swampscott.
        $this->districtRepo->method('getRegionalMapping')->willReturn('Swampscott');
        $this->setupNearbySchools([]);

        $property = $this->makeProperty(1);
        $property->city = 'Nahant';

        $result = $this->service->filter([$property], ['school_district' => 'Swampscott']);

        $this->assertCount(1, $result);
    }

    public function testSchoolDistrictRejectsNonMatchingCity(): void
    {
        $this->districtRepo->method('getRegionalMapping')->willReturn(null);
        $this->setupNearbySchools([]);

        $property = $this->makeProperty(1);
        $property->city = 'Boston';

        $result = $this->service->filter([$property], ['school_district' => 'Winchester']);

        $this->assertCount(0, $result);
    }

    // ------------------------------------------------------------------
    // Specific school filters
    // ------------------------------------------------------------------

    public function testElementarySchoolFilterByName(): void
    {
        $school = $this->makeSchool(1, 'Ambrose Elementary', 'Elementary');
        $this->setupNearbySchools([$school]);

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['elementary_school' => 'Ambrose Elementary']);

        $this->assertCount(1, $result);
    }

    public function testMiddleSchoolFilterByName(): void
    {
        $school = $this->makeSchool(1, 'McCall Middle', 'Middle');
        $this->setupNearbySchools([$school]);

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['middle_school' => 'McCall Middle']);

        $this->assertCount(1, $result);
    }

    public function testHighSchoolFilterById(): void
    {
        $school = $this->makeSchool(42, 'Winchester High', 'High');
        $this->setupNearbySchools([$school]);

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['high_school' => '42']);

        $this->assertCount(1, $result);
    }

    public function testSpecificSchoolFilterRequiresCorrectLevel(): void
    {
        // School is High but filter is for Elementary.
        $school = $this->makeSchool(1, 'Winchester High', 'High');
        $this->setupNearbySchools([$school]);

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['elementary_school' => 'Winchester High']);

        $this->assertCount(0, $result);
    }

    // ------------------------------------------------------------------
    // Combined criteria
    // ------------------------------------------------------------------

    public function testMultipleCriteriaAllMustPass(): void
    {
        $school = $this->makeSchool(1, 'Great School', 'High');
        $this->setupNearbySchools([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(
            (object) ['letter_grade' => 'A']
        );
        $this->districtRepo->method('getRegionalMapping')->willReturn(null);

        $property = $this->makeProperty(1);
        $property->city = 'Winchester';

        $result = $this->service->filter([$property], [
            'school_grade' => 'A',
            'school_district' => 'Winchester',
        ]);

        $this->assertCount(1, $result);
    }

    public function testMultipleCriteriaFailsIfOneFails(): void
    {
        $school = $this->makeSchool(1, 'Great School', 'High');
        $this->setupNearbySchools([$school]);
        $this->rankingRepo->method('getRanking')->willReturn(
            (object) ['letter_grade' => 'A']
        );
        $this->districtRepo->method('getRegionalMapping')->willReturn(null);

        $property = $this->makeProperty(1);
        $property->city = 'Boston'; // Doesn't match district.

        $result = $this->service->filter([$property], [
            'school_grade' => 'A',
            'school_district' => 'Winchester',
        ]);

        $this->assertCount(0, $result);
    }

    // ------------------------------------------------------------------
    // No nearby schools
    // ------------------------------------------------------------------

    public function testPropertyWithNoNearbySchoolsFailsGradeFilter(): void
    {
        $this->setupNearbySchools([]); // No schools nearby.

        $properties = [$this->makeProperty(1)];
        $result = $this->service->filter($properties, ['school_grade' => 'A']);

        $this->assertCount(0, $result);
    }

    public function testPropertyWithNoCoordinatesGetsEmptySchools(): void
    {
        $this->schoolRepo->method('findInBoundingBox')->willReturn([]);

        $property = (object) [
            'listing_id' => '1',
            'latitude' => 0,
            'longitude' => 0,
            'city' => 'Winchester',
        ];

        $result = $this->service->filter([$property], ['school_grade' => 'A']);

        $this->assertCount(0, $result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeProperty(int $id, float $lat = 42.4520, float $lng = -71.1370): object
    {
        return (object) [
            'listing_id' => (string) $id,
            'latitude' => $lat,
            'longitude' => $lng,
            'city' => 'Winchester',
        ];
    }

    private function makeSchool(int $id, string $name, string $level): object
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'level' => $level,
            'latitude' => 42.4530,
            'longitude' => -71.1380,
            'school_type' => 'public',
            'city' => 'Winchester',
        ];
    }

    private function setupNearbySchools(array $schools): void
    {
        $this->schoolRepo->method('findInBoundingBox')->willReturn($schools);
        // All schools within radius.
        $this->geocoding->method('haversineDistance')->willReturn(0.5);
    }
}
