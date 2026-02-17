<?php

declare(strict_types=1);

namespace BMN\Flip\Service;

use wpdb;

/**
 * Calculate ARV (After Repair Value) using comparable sales with
 * Haversine-based search, appraisal-style adjustments, and confidence scoring.
 */
class ArvService
{
    /** @var float[] Expanding search radius tiers in miles. */
    private const RADIUS_TIERS = [0.5, 1.0, 2.0, 5.0, 10.0];

    /** Minimum number of comps required for a meaningful ARV. */
    private const MIN_COMPS = 3;

    /** Maximum number of comps to use. */
    private const MAX_COMPS = 15;

    /** How far back to look for closed sales. */
    private const LOOKBACK_MONTHS = 12;

    /** Cap total adjustments at +/-25% of comp price. */
    private const MAX_ADJUSTMENT_PCT = 0.25;

    public function __construct(
        private readonly wpdb $wpdb,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Run the full ARV calculation for a subject property.
     *
     * @param array $subject Subject property data (associative array).
     *
     * @return array{arv: float, confidence: string, confidence_score: float,
     *               comp_count: int, avg_ppsf: float, comparables: array,
     *               neighborhood_ceiling: float|null}
     */
    public function calculateArv(array $subject): array
    {
        $comparables = [];

        // 1. Expand radius until we hit MIN_COMPS or exhaust tiers.
        foreach (self::RADIUS_TIERS as $radius) {
            $comparables = $this->findComparables($subject, $radius, self::MAX_COMPS);

            if (count($comparables) >= self::MIN_COMPS) {
                break;
            }
        }

        // Early exit when no comps found at all.
        if ($comparables === []) {
            return [
                'arv'                  => 0.0,
                'confidence'           => 'none',
                'confidence_score'     => 0.0,
                'comp_count'           => 0,
                'avg_ppsf'             => 0.0,
                'comparables'          => [],
                'neighborhood_ceiling' => null,
            ];
        }

        // 2. Compute avg PPSF across comps (needed for adjustment scaling).
        $avgPpsf = $this->computeAvgPpsf($comparables);

        // 3. Calculate adjustments and weights for each comp.
        $subjectObj = (object) $subject;
        $enriched   = [];

        foreach ($comparables as $comp) {
            $adj    = $this->calculateAdjustments($subjectObj, $comp, $avgPpsf);
            $weight = $this->calculateWeight($comp, $avgPpsf);

            $enriched[] = [
                'comp'           => $comp,
                'adjustments'    => $adj['adjustments'],
                'total_adj'      => $adj['total'],
                'adjusted_price' => $adj['adjusted_price'],
                'gross_pct'      => $adj['gross_pct'],
                'weight'         => $weight,
            ];
        }

        // 4. Weighted average of adjusted prices = ARV.
        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($enriched as $item) {
            $weightedSum += $item['adjusted_price'] * $item['weight'];
            $totalWeight += $item['weight'];
        }

        $arv = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;

        // 5. Neighborhood ceiling (P90 within 0.5 mi).
        $lat          = (float) ($subject['latitude'] ?? 0);
        $lng          = (float) ($subject['longitude'] ?? 0);
        $propertyType = (string) ($subject['property_type'] ?? '');
        $ceiling      = $this->getNeighborhoodCeiling($lat, $lng, $propertyType);

        // 6. Confidence.
        $confidence = $this->calculateConfidence($comparables, $subject);

        return [
            'arv'                  => round($arv, 2),
            'confidence'           => $confidence['level'],
            'confidence_score'     => $confidence['score'],
            'comp_count'           => count($comparables),
            'avg_ppsf'             => round($avgPpsf, 2),
            'comparables'          => $enriched,
            'neighborhood_ceiling' => $ceiling,
        ];
    }

    /**
     * Find closed comparable sales within a given radius.
     *
     * @param array $subject Subject property data.
     * @param float $radius  Search radius in miles.
     * @param int   $limit   Max results.
     *
     * @return object[]
     */
    public function findComparables(array $subject, float $radius, int $limit): array
    {
        $lat          = (float) ($subject['latitude'] ?? 0);
        $lng          = (float) ($subject['longitude'] ?? 0);
        $propertyType = (string) ($subject['property_type'] ?? '');
        $bedrooms     = (int) ($subject['bedrooms_total'] ?? 0);
        $bathrooms    = (float) ($subject['bathrooms_total'] ?? 0);
        $listingId    = (string) ($subject['listing_id'] ?? '');

        if ($lat === 0.0 || $lng === 0.0) {
            return [];
        }

        $table = $this->wpdb->prefix . 'bmn_properties';
        $cutoffDate = gmdate('Y-m-d', strtotime('-' . self::LOOKBACK_MONTHS . ' months'));

        // Haversine distance in miles.
        $haversine = "(3959 * ACOS(
            COS(RADIANS(%f)) * COS(RADIANS(latitude))
            * COS(RADIANS(longitude) - RADIANS(%f))
            + SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
        ))";

        // Build property type filter — allow compatible fallbacks.
        $typeCondition = $this->buildPropertyTypeCondition($propertyType);

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $this->wpdb->prepare(
            "SELECT *,
                    {$haversine} AS distance_miles
             FROM {$table}
             WHERE standard_status = 'Closed'
               AND close_date >= %s
               AND {$typeCondition}
               AND bedrooms_total BETWEEN %d AND %d
               AND bathrooms_total BETWEEN %f AND %f
               AND listing_id != %s
               AND latitude IS NOT NULL
               AND longitude IS NOT NULL
             HAVING distance_miles <= %f
             ORDER BY distance_miles ASC
             LIMIT %d",
            $lat,
            $lng,
            $lat,
            $cutoffDate,
            max(0, $bedrooms - 1),
            $bedrooms + 1,
            max(0, $bathrooms - 1),
            $bathrooms + 1,
            $listingId,
            $radius,
            $limit
        );
        // phpcs:enable

        return $this->wpdb->get_results($sql) ?: [];
    }

    /**
     * Calculate appraisal-style adjustments between subject and comp.
     *
     * @return array{adjustments: array, total: float, adjusted_price: float, gross_pct: float}
     */
    public function calculateAdjustments(object $subject, object $comp, float $avgPpsf = 0.0): array
    {
        $closePrice = (float) ($comp->close_price ?? 0);

        if ($closePrice <= 0) {
            return [
                'adjustments'    => [],
                'total'          => 0.0,
                'adjusted_price' => 0.0,
                'gross_pct'      => 0.0,
            ];
        }

        if ($avgPpsf <= 0) {
            $compArea = (int) ($comp->living_area ?? 0);
            $avgPpsf  = $compArea > 0 ? $closePrice / $compArea : 350.0;
        }

        $ppsf        = $avgPpsf;
        $scaleFactor = $avgPpsf / 350.0;
        $adjustments = [];

        // Bedroom adjustment.
        $subBeds  = (int) ($subject->bedrooms_total ?? 0);
        $compBeds = (int) ($comp->bedrooms_total ?? 0);
        if ($subBeds > 0 && $compBeds > 0) {
            $diff = $subBeds - $compBeds;
            $adjustments['bedroom'] = $ppsf * 40.0 * $scaleFactor * $diff;
        }

        // Bathroom adjustment.
        $subBaths  = (float) ($subject->bathrooms_total ?? 0);
        $compBaths = (float) ($comp->bathrooms_total ?? 0);
        if ($subBaths > 0 && $compBaths > 0) {
            $diff = $subBaths - $compBaths;
            $adjustments['bathroom'] = $ppsf * 55.0 * $scaleFactor * $diff;
        }

        // Square footage adjustment (capped at +/-15% of comp price).
        $subSqft  = (int) ($subject->living_area ?? 0);
        $compSqft = (int) ($comp->living_area ?? 0);
        if ($subSqft > 0 && $compSqft > 0) {
            $diff   = $subSqft - $compSqft;
            $rawAdj = $ppsf * 0.5 * $diff;
            $cap    = $closePrice * 0.15;
            $adjustments['sqft'] = max(-$cap, min($cap, $rawAdj));
        }

        // Year built adjustment (0.4% per year, capped at +/-10%).
        $subYear  = (int) ($subject->year_built ?? 0);
        $compYear = (int) ($comp->year_built ?? 0);
        if ($subYear > 0 && $compYear > 0) {
            $diff   = $subYear - $compYear;
            $rawAdj = 0.004 * $closePrice * $diff;
            $cap    = $closePrice * 0.10;
            $adjustments['year_built'] = max(-$cap, min($cap, $rawAdj));
        }

        // Garage adjustment.
        $subGarage  = (int) ($subject->garage_spaces ?? 0);
        $compGarage = (int) ($comp->garage_spaces ?? 0);
        $garageDiff = $subGarage - $compGarage;
        if ($garageDiff !== 0) {
            $adjustments['garage'] = $ppsf * 40.0 * $scaleFactor * $garageDiff;
        }

        // Lot size adjustment (2% per 0.25 acre, capped at +/-10%).
        $subLot  = (float) ($subject->lot_size_acres ?? 0);
        $compLot = (float) ($comp->lot_size_acres ?? 0);
        if ($subLot > 0 && $compLot > 0) {
            $diff   = $subLot - $compLot;
            $units  = $diff / 0.25;
            $rawAdj = 0.02 * $closePrice * $units;
            $cap    = $closePrice * 0.10;
            $adjustments['lot_size'] = max(-$cap, min($cap, $rawAdj));
        }

        // Total adjustment, capped at MAX_ADJUSTMENT_PCT of comp price.
        $totalRaw = array_sum($adjustments);
        $maxAdj   = $closePrice * self::MAX_ADJUSTMENT_PCT;
        $total    = max(-$maxAdj, min($maxAdj, $totalRaw));

        $adjustedPrice = $closePrice + $total;
        $grossPct      = $closePrice > 0 ? abs($total) / $closePrice * 100 : 0.0;

        return [
            'adjustments'    => $adjustments,
            'total'          => round($total, 2),
            'adjusted_price' => round($adjustedPrice, 2),
            'gross_pct'      => round($grossPct, 2),
        ];
    }

    /**
     * Calculate a weight for a comparable based on renovation status,
     * recency, and distance.
     */
    public function calculateWeight(object $comp, float $avgPpsf): float
    {
        // Renovation multiplier.
        $remarks  = strtolower((string) ($comp->remarks ?? ''));
        $renovated = (
            str_contains($remarks, 'renovated')
            || str_contains($remarks, 'updated')
            || str_contains($remarks, 'remodeled')
        );
        $renoMult = $renovated ? 1.3 : 1.0;

        // Time weight — exponential decay.
        $closeDate = (string) ($comp->close_date ?? '');
        $monthsAgo = 0.0;

        if ($closeDate !== '') {
            $closeTs = strtotime($closeDate);
            $nowTs   = (int) current_time('timestamp');

            if ($closeTs !== false && $closeTs > 0) {
                $monthsAgo = max(0, ($nowTs - $closeTs) / (30 * 86400));
            }
        }

        $timeWeight = exp(-0.115 * $monthsAgo);

        // Distance factor.
        $distance = (float) ($comp->distance_miles ?? 0);

        $weight = ($renoMult * $timeWeight) / pow($distance + 0.1, 2);

        return round($weight, 6);
    }

    /**
     * Score the confidence of the ARV estimate.
     *
     * @param object[] $comps   Comparable sales.
     * @param array    $subject Subject property data.
     *
     * @return array{score: float, level: string}
     */
    public function calculateConfidence(array $comps, array $subject): array
    {
        $count = count($comps);

        // Factor 1: Comp count (0-40 points).
        if ($count === 0) {
            $countScore = 0;
        } elseif ($count <= 2) {
            $countScore = 15;
        } elseif ($count <= 4) {
            $countScore = 25;
        } elseif ($count <= 7) {
            $countScore = 35;
        } else {
            $countScore = 40;
        }

        // Factor 2: Average distance (0-30 points).
        $avgDist = 0.0;

        if ($count > 0) {
            $totalDist = 0.0;

            foreach ($comps as $comp) {
                $totalDist += (float) ($comp->distance_miles ?? 0);
            }

            $avgDist = $totalDist / $count;
        }

        if ($avgDist < 0.5) {
            $distScore = 30;
        } elseif ($avgDist < 1.0) {
            $distScore = 20;
        } elseif ($avgDist < 2.0) {
            $distScore = 10;
        } else {
            $distScore = 5;
        }

        // Factor 3: Average recency in months (0-20 points).
        $avgMonths = 0.0;

        if ($count > 0) {
            $totalMonths = 0.0;
            $nowTs       = (int) current_time('timestamp');

            foreach ($comps as $comp) {
                $closeDate = (string) ($comp->close_date ?? '');

                if ($closeDate !== '') {
                    $closeTs = strtotime($closeDate);

                    if ($closeTs !== false && $closeTs > 0) {
                        $totalMonths += max(0, ($nowTs - $closeTs) / (30 * 86400));
                    }
                }
            }

            $avgMonths = $totalMonths / $count;
        }

        if ($avgMonths < 3) {
            $recencyScore = 20;
        } elseif ($avgMonths < 6) {
            $recencyScore = 15;
        } elseif ($avgMonths < 9) {
            $recencyScore = 10;
        } else {
            $recencyScore = 5;
        }

        // Factor 4: Price variance / coefficient of variation (0-10 points).
        $cvScore = 2;

        if ($count >= 2) {
            $prices = [];

            foreach ($comps as $comp) {
                $price = (float) ($comp->close_price ?? 0);

                if ($price > 0) {
                    $prices[] = $price;
                }
            }

            if (count($prices) >= 2) {
                $mean   = array_sum($prices) / count($prices);
                $sumSqD = 0.0;

                foreach ($prices as $p) {
                    $sumSqD += ($p - $mean) ** 2;
                }

                $stdDev = sqrt($sumSqD / count($prices));
                $cv     = $mean > 0 ? $stdDev / $mean : 1.0;

                if ($cv < 0.10) {
                    $cvScore = 10;
                } elseif ($cv < 0.20) {
                    $cvScore = 7;
                } elseif ($cv < 0.30) {
                    $cvScore = 4;
                } else {
                    $cvScore = 2;
                }
            }
        }

        $score = (float) ($countScore + $distScore + $recencyScore + $cvScore);

        // Map score to level.
        if ($score >= 75) {
            $level = 'high';
        } elseif ($score >= 50) {
            $level = 'medium';
        } elseif ($score >= 20) {
            $level = 'low';
        } else {
            $level = 'none';
        }

        return [
            'score' => $score,
            'level' => $level,
        ];
    }

    /**
     * Get the P90 (90th percentile) price within 0.5 miles of the same property type.
     * Serves as a neighborhood ceiling for the ARV.
     */
    public function getNeighborhoodCeiling(float $lat, float $lng, string $propertyType): ?float
    {
        if ($lat === 0.0 || $lng === 0.0) {
            return null;
        }

        $table      = $this->wpdb->prefix . 'bmn_properties';
        $cutoffDate = gmdate('Y-m-d', strtotime('-' . self::LOOKBACK_MONTHS . ' months'));
        $radius     = 0.5;

        $haversine = "(3959 * ACOS(
            COS(RADIANS(%f)) * COS(RADIANS(latitude))
            * COS(RADIANS(longitude) - RADIANS(%f))
            + SIN(RADIANS(%f)) * SIN(RADIANS(latitude))
        ))";

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $this->wpdb->prepare(
            "SELECT close_price,
                    {$haversine} AS distance_miles
             FROM {$table}
             WHERE standard_status = 'Closed'
               AND close_date >= %s
               AND property_type = %s
               AND close_price > 0
               AND latitude IS NOT NULL
               AND longitude IS NOT NULL
             HAVING distance_miles <= %f
             ORDER BY close_price ASC",
            $lat,
            $lng,
            $lat,
            $cutoffDate,
            $propertyType,
            $radius
        );
        // phpcs:enable

        $results = $this->wpdb->get_results($sql);

        if (empty($results)) {
            return null;
        }

        $prices = array_map(
            static fn(object $row): float => (float) $row->close_price,
            $results
        );

        // P90: 90th percentile.
        $count = count($prices);
        $index = (int) ceil(0.90 * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return round($prices[$index], 2);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Compute the average price per square foot across comps.
     *
     * @param object[] $comps
     */
    private function computeAvgPpsf(array $comps): float
    {
        $totalPpsf = 0.0;
        $counted   = 0;

        foreach ($comps as $comp) {
            $price = (float) ($comp->close_price ?? 0);
            $area  = (int) ($comp->living_area ?? 0);

            if ($price > 0 && $area > 0) {
                $totalPpsf += $price / $area;
                $counted++;
            }
        }

        return $counted > 0 ? $totalPpsf / $counted : 0.0;
    }

    /**
     * Build SQL condition for compatible property types.
     */
    private function buildPropertyTypeCondition(string $propertyType): string
    {
        // Group compatible types together for broader matching.
        $typeGroups = [
            'Single Family Residence' => ['Single Family Residence'],
            'Condominium'             => ['Condominium'],
            'Multi Family'            => ['Multi Family', 'Two Family', 'Three Family'],
            'Two Family'              => ['Multi Family', 'Two Family', 'Three Family'],
            'Three Family'            => ['Multi Family', 'Two Family', 'Three Family'],
            'Townhouse'               => ['Townhouse', 'Condominium'],
        ];

        $types = $typeGroups[$propertyType] ?? [$propertyType];
        $placeholders = implode(',', array_fill(0, count($types), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $this->wpdb->prepare(
            "property_type IN ({$placeholders})",
            ...$types
        );
    }
}
