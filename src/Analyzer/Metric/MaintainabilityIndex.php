<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Metric;

/**
 * Maintainability Index calculator
 *
 * Formula: MI = 171 - 5.2 * ln(V) - 0.23 * CCN - 16.2 * ln(LOC)
 *
 * Where:
 * - V = Halstead Volume
 * - CCN = Cyclomatic Complexity
 * - LOC = Lines of Code (LLOC)
 *
 * Normalized to 0-100 scale
 */
class MaintainabilityIndex implements MetricInterface
{
    public function getName(): string
    {
        return 'Maintainability Index';
    }

    public function getDescription(): string
    {
        return 'Measures how maintainable the code is (0-100, higher is better)';
    }

    /**
     * @param array{halstead: array, ccn: array, loc: array} $visitorData
     */
    public function calculate(array $visitorData): MetricResult
    {
        $halstead = $visitorData['halstead'] ?? [];
        $ccn = $visitorData['ccn'] ?? [];
        $loc = $visitorData['loc'] ?? [];

        $volume = $halstead['volume'] ?? 1;
        $avgCcn = $ccn['summary']['averageCcn'] ?? 1;
        $lloc = $loc['lloc'] ?? 1;

        // Avoid log(0)
        $volume = max($volume, 1);
        $lloc = max($lloc, 1);

        // Original MI formula
        $mi = 171
            - 5.2 * log($volume)
            - 0.23 * $avgCcn
            - 16.2 * log($lloc);

        // Normalize to 0-100
        $miNormalized = max(0, min(100, $mi));

        // Calculate with comments weight (optional enhancement)
        $commentRatio = $loc['commentRatio'] ?? 0;
        $miWithComments = $miNormalized;
        if ($commentRatio > 0) {
            // Add bonus for well-commented code (up to +10 points)
            $commentBonus = min(10, $commentRatio / 2);
            $miWithComments = min(100, $miNormalized + $commentBonus);
        }

        $rating = $this->getRating($miNormalized);

        return new MetricResult(
            name: $this->getName(),
            value: round($miNormalized, 2),
            unit: '',
            details: [
                'raw' => round($mi, 2),
                'withComments' => round($miWithComments, 2),
                'halsteadVolume' => round($volume, 2),
                'averageCcn' => round($avgCcn, 2),
                'lloc' => $lloc,
                'commentRatio' => $commentRatio,
            ],
            rating: $rating,
        );
    }

    /**
     * Calculate MI for a single method/class
     */
    public function calculateForUnit(float $volume, float $ccn, int $lloc): float
    {
        $volume = max($volume, 1);
        $lloc = max($lloc, 1);

        $mi = 171
            - 5.2 * log($volume)
            - 0.23 * $ccn
            - 16.2 * log($lloc);

        return max(0, min(100, $mi));
    }

    private function getRating(float $mi): string
    {
        return match (true) {
            $mi >= 85 => 'A',  // Highly maintainable
            $mi >= 65 => 'B',  // Moderately maintainable
            $mi >= 40 => 'C',  // Difficult to maintain
            $mi >= 20 => 'D',  // Very difficult to maintain
            default => 'F',    // Unmaintainable
        };
    }
}
