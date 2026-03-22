<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

/**
 * Result of test coverage analysis from Clover XML format
 */
class CoverageResult
{
    public function __construct(
        public readonly bool $found = false,
        public readonly float $lineCoverage = 0.0,
        public readonly float $methodCoverage = 0.0,
        public readonly float $classCoverage = 0.0,
        public readonly int $coveredLines = 0,
        public readonly int $totalLines = 0,
        public readonly int $coveredMethods = 0,
        public readonly int $totalMethods = 0,
        public readonly int $coveredClasses = 0,
        public readonly int $totalClasses = 0,
        public readonly array $files = [],
        public readonly array $packages = [],
        public readonly string $rating = 'F',
        public readonly ?string $generatedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'lineCoverage' => $this->lineCoverage,
            'methodCoverage' => $this->methodCoverage,
            'classCoverage' => $this->classCoverage,
            'coveredLines' => $this->coveredLines,
            'totalLines' => $this->totalLines,
            'coveredMethods' => $this->coveredMethods,
            'totalMethods' => $this->totalMethods,
            'coveredClasses' => $this->coveredClasses,
            'totalClasses' => $this->totalClasses,
            'rating' => $this->rating,
            'generatedAt' => $this->generatedAt,
            'files' => $this->files,
            'packages' => $this->packages,
        ];
    }
}