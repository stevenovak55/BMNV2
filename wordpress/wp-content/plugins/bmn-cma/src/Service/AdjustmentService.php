<?php

declare(strict_types=1);

namespace BMN\CMA\Service;

/**
 * Calculates price adjustments and confidence scoring for CMA comparables.
 */
class AdjustmentService
{
    /** @var float Maximum gross adjustment percentage before capping. */
    private const MAX_GROSS_PCT = 40.0;

    /**
     * Calculate all adjustments between a subject property and a comparable.
     *
     * @return array{adjustments: array, total: float, adjusted_price: float, gross_pct: float}
     */
    public function calculateAdjustments(object $subject, object $comparable): array
    {
        $closePrice = (float) ($comparable->close_price ?? 0);

        if ($closePrice <= 0) {
            return [
                'adjustments'    => [],
                'total'          => 0.0,
                'adjusted_price' => 0.0,
                'gross_pct'      => 0.0,
            ];
        }

        $adjustments = [];

        // Bedroom adjustment: 2.5% of close_price per bedroom difference.
        $subjectBeds = (int) ($subject->bedrooms_total ?? 0);
        $compBeds = (int) ($comparable->bedrooms_total ?? 0);
        if ($subjectBeds > 0 && $compBeds > 0) {
            $bedDiff = $subjectBeds - $compBeds;
            $bedAdj = $bedDiff * 0.025 * $closePrice;
            $adjustments['bedrooms'] = [
                'subject_value' => $subjectBeds,
                'comp_value'    => $compBeds,
                'difference'    => $bedDiff,
                'adjustment'    => round($bedAdj, 2),
            ];
        }

        // Bathroom adjustment: 1% per bath difference.
        $subjectBaths = (float) ($subject->bathrooms_total ?? 0);
        $compBaths = (float) ($comparable->bathrooms_total ?? 0);
        if ($subjectBaths > 0 && $compBaths > 0) {
            $bathDiff = $subjectBaths - $compBaths;
            $bathAdj = $bathDiff * 0.01 * $closePrice;
            $adjustments['bathrooms'] = [
                'subject_value' => $subjectBaths,
                'comp_value'    => $compBaths,
                'difference'    => $bathDiff,
                'adjustment'    => round($bathAdj, 2),
            ];
        }

        // SqFt adjustment: proportional, capped at 10%.
        $subjectSqft = (int) ($subject->living_area ?? 0);
        $compSqft = (int) ($comparable->living_area ?? 0);
        if ($subjectSqft > 0 && $compSqft > 0) {
            $sqftPct = ($subjectSqft - $compSqft) / $compSqft;
            $sqftPct = max(-0.10, min(0.10, $sqftPct));
            $sqftAdj = $sqftPct * $closePrice;
            $adjustments['sqft'] = [
                'subject_value' => $subjectSqft,
                'comp_value'    => $compSqft,
                'difference'    => $subjectSqft - $compSqft,
                'pct'           => round($sqftPct * 100, 2),
                'adjustment'    => round($sqftAdj, 2),
            ];
        }

        // Year Built adjustment: 0.4% per year, max 10%.
        $subjectYear = (int) ($subject->year_built ?? 0);
        $compYear = (int) ($comparable->year_built ?? 0);
        if ($subjectYear > 0 && $compYear > 0) {
            $yearDiff = $subjectYear - $compYear;
            $yearPct = $yearDiff * 0.004;
            $yearPct = max(-0.10, min(0.10, $yearPct));
            $yearAdj = $yearPct * $closePrice;
            $adjustments['year_built'] = [
                'subject_value' => $subjectYear,
                'comp_value'    => $compYear,
                'difference'    => $yearDiff,
                'pct'           => round($yearPct * 100, 2),
                'adjustment'    => round($yearAdj, 2),
            ];
        }

        // Garage adjustment: 2.5% for first space diff, 1.5% additional.
        $subjectGarage = (int) ($subject->garage_spaces ?? 0);
        $compGarage = (int) ($comparable->garage_spaces ?? 0);
        $garageDiff = $subjectGarage - $compGarage;
        if ($garageDiff !== 0) {
            $garageAdj = 0.0;
            if (abs($garageDiff) >= 1) {
                $garageAdj += ($garageDiff > 0 ? 1 : -1) * 0.025 * $closePrice;
            }
            if (abs($garageDiff) > 1) {
                $additionalSpaces = abs($garageDiff) - 1;
                $garageAdj += ($garageDiff > 0 ? 1 : -1) * $additionalSpaces * 0.015 * $closePrice;
            }
            $adjustments['garage'] = [
                'subject_value' => $subjectGarage,
                'comp_value'    => $compGarage,
                'difference'    => $garageDiff,
                'adjustment'    => round($garageAdj, 2),
            ];
        }

        // Lot size adjustment: 2% per 0.25 acre, max 10%.
        $subjectLot = (float) ($subject->lot_size_acres ?? 0);
        $compLot = (float) ($comparable->lot_size_acres ?? 0);
        if ($subjectLot > 0 && $compLot > 0) {
            $lotDiffQuarters = ($subjectLot - $compLot) / 0.25;
            $lotPct = $lotDiffQuarters * 0.02;
            $lotPct = max(-0.10, min(0.10, $lotPct));
            $lotAdj = $lotPct * $closePrice;
            $adjustments['lot_size'] = [
                'subject_value' => $subjectLot,
                'comp_value'    => $compLot,
                'difference'    => round($subjectLot - $compLot, 4),
                'pct'           => round($lotPct * 100, 2),
                'adjustment'    => round($lotAdj, 2),
            ];
        }

        // Calculate totals.
        $total = 0.0;
        $grossTotal = 0.0;
        foreach ($adjustments as $adj) {
            $total += $adj['adjustment'];
            $grossTotal += abs($adj['adjustment']);
        }

        $grossPct = $closePrice > 0 ? ($grossTotal / $closePrice) * 100 : 0.0;

        // Cap at gross 40%.
        if ($grossPct > self::MAX_GROSS_PCT) {
            $capFactor = (self::MAX_GROSS_PCT / 100) * $closePrice / $grossTotal;
            $total *= $capFactor;
            $grossPct = self::MAX_GROSS_PCT;
        }

        $adjustedPrice = $closePrice + $total;

        return [
            'adjustments'    => $adjustments,
            'total'          => round($total, 2),
            'adjusted_price' => round($adjustedPrice, 2),
            'gross_pct'      => round($grossPct, 2),
        ];
    }

    /**
     * Calculate confidence score based on 6 weighted factors.
     *
     * @param array $comparables Array of comparable objects with adjustment data
     * @param object $subject Subject property
     * @return array{score: float, level: string, factors: array}
     */
    public function calculateConfidence(array $comparables, object $subject): array
    {
        $factors = [];

        // Factor 1: Sample size (0-25 points).
        $count = count($comparables);
        if ($count >= 10) {
            $factors['sample_size'] = 25.0;
        } elseif ($count >= 5) {
            $factors['sample_size'] = 15.0 + ($count - 5) * 2.0;
        } elseif ($count >= 3) {
            $factors['sample_size'] = 5.0 + ($count - 3) * 5.0;
        } else {
            $factors['sample_size'] = $count * 2.5;
        }

        // Factor 2: Data completeness (0-20 points).
        $requiredFields = ['bedrooms_total', 'bathrooms_total', 'living_area', 'year_built', 'lot_size_acres'];
        $subjectComplete = 0;
        foreach ($requiredFields as $field) {
            if (!empty($subject->$field)) {
                $subjectComplete++;
            }
        }
        $factors['data_completeness'] = ($subjectComplete / count($requiredFields)) * 20.0;

        // Factor 3: Market stability (0-20 points) - based on price spread.
        if ($count > 1) {
            $prices = array_map(
                static fn (object $c): float => (float) ($c->adjusted_price ?? $c->close_price ?? 0),
                $comparables
            );
            $prices = array_filter($prices, static fn (float $p): bool => $p > 0);

            if (count($prices) > 1) {
                $mean = array_sum($prices) / count($prices);
                $variance = array_sum(array_map(
                    static fn (float $p): float => ($p - $mean) ** 2,
                    $prices
                )) / count($prices);
                $cv = $mean > 0 ? (sqrt($variance) / $mean) * 100 : 100;

                if ($cv <= 5) {
                    $factors['market_stability'] = 20.0;
                } elseif ($cv <= 10) {
                    $factors['market_stability'] = 15.0;
                } elseif ($cv <= 20) {
                    $factors['market_stability'] = 10.0;
                } elseif ($cv <= 30) {
                    $factors['market_stability'] = 5.0;
                } else {
                    $factors['market_stability'] = 0.0;
                }
            } else {
                $factors['market_stability'] = 5.0;
            }
        } else {
            $factors['market_stability'] = 0.0;
        }

        // Factor 4: Time relevance (0-15 points) - based on recency of sales.
        if ($count > 0) {
            $now = strtotime(current_time('mysql'));
            $totalMonths = 0;
            $validDates = 0;

            foreach ($comparables as $comp) {
                $closeDate = $comp->close_date ?? null;
                if ($closeDate) {
                    $closeTimestamp = strtotime((string) $closeDate);
                    if ($closeTimestamp) {
                        $months = ($now - $closeTimestamp) / (30 * 86400);
                        $totalMonths += $months;
                        $validDates++;
                    }
                }
            }

            if ($validDates > 0) {
                $avgMonths = $totalMonths / $validDates;
                if ($avgMonths <= 3) {
                    $factors['time_relevance'] = 15.0;
                } elseif ($avgMonths <= 6) {
                    $factors['time_relevance'] = 12.0;
                } elseif ($avgMonths <= 9) {
                    $factors['time_relevance'] = 8.0;
                } elseif ($avgMonths <= 12) {
                    $factors['time_relevance'] = 5.0;
                } else {
                    $factors['time_relevance'] = 2.0;
                }
            } else {
                $factors['time_relevance'] = 5.0;
            }
        } else {
            $factors['time_relevance'] = 0.0;
        }

        // Factor 5: Geographic concentration (0-10 points).
        if ($count > 0) {
            $distances = array_map(
                static fn (object $c): float => (float) ($c->distance_miles ?? 10),
                $comparables
            );
            $avgDistance = array_sum($distances) / count($distances);

            if ($avgDistance <= 0.5) {
                $factors['geographic_concentration'] = 10.0;
            } elseif ($avgDistance <= 1.0) {
                $factors['geographic_concentration'] = 8.0;
            } elseif ($avgDistance <= 2.0) {
                $factors['geographic_concentration'] = 6.0;
            } elseif ($avgDistance <= 3.0) {
                $factors['geographic_concentration'] = 4.0;
            } else {
                $factors['geographic_concentration'] = 2.0;
            }
        } else {
            $factors['geographic_concentration'] = 0.0;
        }

        // Factor 6: Comparability quality (0-10 points) - based on gross adjustment pcts.
        if ($count > 0) {
            $grossPcts = array_map(
                static fn (object $c): float => (float) ($c->gross_pct ?? 50),
                $comparables
            );
            $avgGross = array_sum($grossPcts) / count($grossPcts);

            if ($avgGross <= 10) {
                $factors['comparability_quality'] = 10.0;
            } elseif ($avgGross <= 15) {
                $factors['comparability_quality'] = 8.0;
            } elseif ($avgGross <= 25) {
                $factors['comparability_quality'] = 5.0;
            } elseif ($avgGross <= 35) {
                $factors['comparability_quality'] = 3.0;
            } else {
                $factors['comparability_quality'] = 1.0;
            }
        } else {
            $factors['comparability_quality'] = 0.0;
        }

        $score = round(array_sum($factors), 1);
        $level = $this->getConfidenceLevel($score);

        return [
            'score'   => $score,
            'level'   => $level,
            'factors' => $factors,
        ];
    }

    /**
     * Map a confidence score to a level string.
     */
    public function getConfidenceLevel(float $score): string
    {
        if ($score >= 80) {
            return 'high';
        }
        if ($score >= 60) {
            return 'medium';
        }
        if ($score >= 40) {
            return 'low';
        }

        return 'insufficient';
    }

    /**
     * Calculate valuation range from adjusted prices using mean and standard deviation.
     *
     * @param float[] $adjustedPrices
     * @return array{low: float, mid: float, high: float}
     */
    public function calculateValuation(array $adjustedPrices): array
    {
        $prices = array_filter($adjustedPrices, static fn (float $p): bool => $p > 0);

        if (count($prices) === 0) {
            return ['low' => 0.0, 'mid' => 0.0, 'high' => 0.0];
        }

        $count = count($prices);
        $mean = array_sum($prices) / $count;

        if ($count === 1) {
            // Single comp: use +/- 5% range.
            return [
                'low'  => round($mean * 0.95, 2),
                'mid'  => round($mean, 2),
                'high' => round($mean * 1.05, 2),
            ];
        }

        $variance = array_sum(array_map(
            static fn (float $p): float => ($p - $mean) ** 2,
            $prices
        )) / $count;

        $stddev = sqrt($variance);

        return [
            'low'  => round($mean - $stddev, 2),
            'mid'  => round($mean, 2),
            'high' => round($mean + $stddev, 2),
        ];
    }

    /**
     * Grade a comparable based on gross adjustment percentage.
     */
    public function gradeComparable(float $grossPct): string
    {
        if ($grossPct < 10) {
            return 'A';
        }
        if ($grossPct < 15) {
            return 'B';
        }
        if ($grossPct < 25) {
            return 'C';
        }
        if ($grossPct < 35) {
            return 'D';
        }

        return 'F';
    }
}
