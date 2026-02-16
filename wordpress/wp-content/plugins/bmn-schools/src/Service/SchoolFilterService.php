<?php

declare(strict_types=1);

namespace BMN\Schools\Service;

use BMN\Platform\Cache\CacheService;
use BMN\Platform\Geocoding\GeocodingService;
use BMN\Schools\Repository\SchoolRankingRepository;
use BMN\Schools\Repository\SchoolRepository;
use BMN\Schools\Repository\SchoolDistrictRepository;

/**
 * Implements the bmn_filter_by_school WordPress filter hook.
 *
 * This service integrates with Phase 3's PropertySearchService.
 * It receives overfetched property rows + school criteria,
 * then filters properties based on nearby school attributes.
 */
class SchoolFilterService
{
    /**
     * Grade comparison order (higher index = better grade).
     */
    private const GRADE_ORDER = [
        'F' => 0, 'D' => 1, 'C-' => 2, 'C' => 3, 'C+' => 4,
        'B-' => 5, 'B' => 6, 'B+' => 7, 'A-' => 8, 'A' => 9, 'A+' => 10,
    ];

    private readonly SchoolRepository $schoolRepo;
    private readonly SchoolDistrictRepository $districtRepo;
    private readonly SchoolRankingRepository $rankingRepo;
    private readonly GeocodingService $geocoding;
    private readonly CacheService $cache;

    /** Default radius in miles for finding nearby schools. */
    private const NEARBY_RADIUS = 2.0;

    public function __construct(
        SchoolRepository $schoolRepo,
        SchoolDistrictRepository $districtRepo,
        SchoolRankingRepository $rankingRepo,
        GeocodingService $geocoding,
        CacheService $cache,
    ) {
        $this->schoolRepo = $schoolRepo;
        $this->districtRepo = $districtRepo;
        $this->rankingRepo = $rankingRepo;
        $this->geocoding = $geocoding;
        $this->cache = $cache;
    }

    /**
     * Filter properties by school criteria.
     *
     * This is the handler for the `bmn_filter_by_school` WordPress filter.
     *
     * @param object[] $properties Overfetched property rows with latitude/longitude.
     * @param array    $criteria   School filter criteria.
     *
     * @return object[] Filtered property array.
     */
    public function filter(array $properties, array $criteria): array
    {
        if ($properties === [] || $criteria === []) {
            return $properties;
        }

        // Build a mapping of property index → nearby schools.
        $propertySchools = $this->batchFindNearbySchools($properties);

        // Get year for ranking lookups.
        $year = $this->rankingRepo->getLatestDataYear();

        // Batch-load rankings for all nearby schools.
        $allSchoolIds = [];
        foreach ($propertySchools as $schools) {
            foreach ($schools as $school) {
                $allSchoolIds[(int) $school->id] = true;
            }
        }
        $allSchoolIds = array_keys($allSchoolIds);

        $rankings = [];
        if ($allSchoolIds !== []) {
            $cacheKey = 'filter_rankings_' . md5(implode(',', $allSchoolIds) . "_{$year}");
            $rankings = $this->cache->remember($cacheKey, 3600, function () use ($allSchoolIds, $year): array {
                $result = [];
                foreach ($allSchoolIds as $id) {
                    $ranking = $this->rankingRepo->getRanking($id, $year);
                    if ($ranking !== null) {
                        $result[$id] = $ranking;
                    }
                }
                return $result;
            }, 'schools');
        }

        // Filter each property.
        $filtered = [];
        foreach ($properties as $i => $property) {
            $nearbySchools = $propertySchools[$i] ?? [];

            if ($this->propertySatisfiesCriteria($property, $nearbySchools, $rankings, $criteria)) {
                $filtered[] = $property;
            }
        }

        return $filtered;
    }

    /**
     * Check if a property satisfies all school filter criteria.
     */
    private function propertySatisfiesCriteria(
        object $property,
        array $nearbySchools,
        array $rankings,
        array $criteria,
    ): bool {
        foreach ($criteria as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $passes = match ($key) {
                'school_grade' => $this->checkSchoolGrade($nearbySchools, $rankings, $value),
                'school_district' => $this->checkSchoolDistrict($property, $value),
                'elementary_school' => $this->checkSpecificSchool($nearbySchools, $value, 'Elementary'),
                'middle_school' => $this->checkSpecificSchool($nearbySchools, $value, 'Middle'),
                'high_school' => $this->checkSpecificSchool($nearbySchools, $value, 'High'),
                default => true,
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if at least one nearby school has a letter grade >= requested.
     */
    private function checkSchoolGrade(array $nearbySchools, array $rankings, string $minGrade): bool
    {
        $minOrder = self::GRADE_ORDER[$minGrade] ?? 0;

        foreach ($nearbySchools as $school) {
            $schoolId = (int) $school->id;
            $ranking = $rankings[$schoolId] ?? null;

            if ($ranking === null || ! isset($ranking->letter_grade)) {
                continue;
            }

            $schoolOrder = self::GRADE_ORDER[$ranking->letter_grade] ?? 0;
            if ($schoolOrder >= $minOrder) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if property's city maps to the requested school district.
     */
    private function checkSchoolDistrict(object $property, string $districtName): bool
    {
        $city = $property->city ?? '';
        if ($city === '') {
            return false;
        }

        // Check regional mapping first.
        $mappedDistrict = $this->districtRepo->getRegionalMapping($city);
        if ($mappedDistrict !== null) {
            return strcasecmp($mappedDistrict, $districtName) === 0;
        }

        // Fall back to city name matching district name.
        return strcasecmp($city, $districtName) === 0;
    }

    /**
     * Check if a specific school (by name or ID) is within radius and matches level.
     */
    private function checkSpecificSchool(array $nearbySchools, string $schoolIdentifier, string $level): bool
    {
        foreach ($nearbySchools as $school) {
            if ($school->level !== $level) {
                continue;
            }

            // Match by ID or name.
            if ((string) $school->id === $schoolIdentifier || strcasecmp($school->name, $schoolIdentifier) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Batch-find nearby schools for all properties using a single bounding box query.
     *
     * @param object[] $properties
     * @return array<int, object[]> Keyed by property index.
     */
    private function batchFindNearbySchools(array $properties): array
    {
        // Find the bounding box that encompasses all properties.
        $minLat = PHP_FLOAT_MAX;
        $maxLat = -PHP_FLOAT_MAX;
        $minLng = PHP_FLOAT_MAX;
        $maxLng = -PHP_FLOAT_MAX;
        $hasCoords = false;

        foreach ($properties as $property) {
            $lat = (float) ($property->latitude ?? 0);
            $lng = (float) ($property->longitude ?? 0);

            if ($lat == 0 && $lng == 0) {
                continue;
            }

            $hasCoords = true;
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
            $minLng = min($minLng, $lng);
            $maxLng = max($maxLng, $lng);
        }

        if (! $hasCoords) {
            return [];
        }

        // Expand bounding box by the nearby radius (~2 miles ≈ 0.029 degrees latitude).
        $latExpansion = self::NEARBY_RADIUS / 69.0; // ~69 miles per degree.
        $lngExpansion = self::NEARBY_RADIUS / (69.0 * cos(deg2rad(($minLat + $maxLat) / 2)));

        $allSchools = $this->schoolRepo->findInBoundingBox(
            $minLat - $latExpansion,
            $maxLat + $latExpansion,
            $minLng - $lngExpansion,
            $maxLng + $lngExpansion,
        );

        // Assign schools to properties using haversine distance.
        $result = [];
        foreach ($properties as $i => $property) {
            $propLat = (float) ($property->latitude ?? 0);
            $propLng = (float) ($property->longitude ?? 0);

            if ($propLat == 0 && $propLng == 0) {
                $result[$i] = [];
                continue;
            }

            $nearby = [];
            foreach ($allSchools as $school) {
                $schoolLat = (float) ($school->latitude ?? 0);
                $schoolLng = (float) ($school->longitude ?? 0);

                if ($schoolLat == 0 && $schoolLng == 0) {
                    continue;
                }

                $distance = $this->geocoding->haversineDistance($propLat, $propLng, $schoolLat, $schoolLng);

                if ($distance <= self::NEARBY_RADIUS) {
                    $school = clone $school;
                    $school->distance = round($distance, 2);
                    $nearby[] = $school;
                }
            }

            // Sort by distance.
            usort($nearby, static fn ($a, $b) => $a->distance <=> $b->distance);
            $result[$i] = $nearby;
        }

        return $result;
    }
}
