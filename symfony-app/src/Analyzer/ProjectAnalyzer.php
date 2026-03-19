<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Result\ProjectResult;
use App\Analyzer\Result\FileResult;
use App\Analyzer\ProjectType\ProjectTypeInterface;
use App\Analyzer\ProjectType\ProjectTypeDetector;
use Symfony\Component\Finder\Finder;

class ProjectAnalyzer
{
    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer,
        private readonly ProjectTypeDetector $typeDetector,
        private readonly DependenciesAnalyzer $dependenciesAnalyzer,
    ) {}

    /**
     * @param array<string> $excludes
     */
    public function analyze(
        string $sourcePath,
        ?string $projectTypeName = null,
        array $excludes = [],
        ?callable $progressCallback = null
    ): ProjectResult {
        $projectType = $this->resolveProjectType($sourcePath, $projectTypeName);
        $files = $this->findPhpFiles($sourcePath, $projectType, $excludes);
        $fileResults = $this->analyzeFiles($files, $sourcePath, $projectType, $progressCallback);
        $summary = $this->calculateSummary($fileResults, $projectType);
        $dependencies = $this->dependenciesAnalyzer->analyze($sourcePath);

        return new ProjectResult(
            sourcePath: $sourcePath,
            projectType: $projectType,
            files: $fileResults,
            summary: $summary,
            analyzedAt: new \DateTimeImmutable(),
            dependencies: $dependencies,
        );
    }

    private function resolveProjectType(string $sourcePath, ?string $typeName): ProjectTypeInterface
    {
        if ($typeName === 'auto' || $typeName === null) {
            return $this->typeDetector->detect($sourcePath);
        }
        return $this->typeDetector->getProjectType($typeName);
    }

    private function findPhpFiles(string $sourcePath, ProjectTypeInterface $projectType, array $excludes): array
    {
        $allExcludes = array_unique(array_merge($projectType->getExcludedPaths(), $excludes));

        $finder = new Finder();
        $finder->files()
            ->in($sourcePath)
            ->name('*.php')
            ->ignoreVCS(true)
            ->ignoreDotFiles(true);

        foreach ($allExcludes as $exclude) {
            $finder->notPath($exclude);
        }

        return iterator_to_array($finder);
    }

    private function analyzeFiles(
        array $files,
        string $sourcePath,
        ProjectTypeInterface $projectType,
        ?callable $progressCallback
    ): array {
        $totalFiles = count($files);
        $processedFiles = 0;
        $fileResults = [];

        foreach ($files as $file) {
            $fileResults[] = $this->fileAnalyzer->analyze(
                $file->getRealPath(),
                $sourcePath,
                $projectType
            );

            $processedFiles++;
            if ($progressCallback) {
                $progressCallback($processedFiles, $totalFiles, $file->getRelativePathname());
            }
        }

        return $fileResults;
    }

    /**
     * @param array<FileResult> $fileResults
     */
    private function calculateSummary(array $fileResults, ProjectTypeInterface $projectType): array
    {
        $metrics = $this->aggregateMetrics($fileResults);
        $thresholds = $projectType->getRecommendedThresholds();

        return [
            'projectType' => $projectType->getName(),
            'projectTypeLabel' => $projectType->getLabel(),
            'totalFiles' => count($fileResults),
            'totalClasses' => $metrics['classes'],
            'totalMethods' => $metrics['methods'],
            'totalLoc' => $metrics['loc'],
            'totalLloc' => $metrics['lloc'],
            'totalCloc' => $metrics['cloc'],
            'commentRatio' => $metrics['loc'] > 0 ? round($metrics['cloc'] / $metrics['loc'] * 100, 2) : 0,
            'averageMi' => $this->average($metrics['miValues']),
            'averageCcn' => $this->average($metrics['ccnValues']),
            'maxCcn' => !empty($metrics['ccnValues']) ? max($metrics['ccnValues']) : 0,
            'averageLcom' => $this->average($metrics['lcomValues'], 4),
            'errors' => $metrics['errors'],
            'thresholds' => $thresholds,
            'violations' => $this->countViolations($metrics, $thresholds),
            'ratings' => $this->calculateRatings($metrics),
        ];
    }

    private function aggregateMetrics(array $fileResults): array
    {
        $metrics = [
            'loc' => 0, 'lloc' => 0, 'cloc' => 0,
            'classes' => 0, 'methods' => 0, 'errors' => 0,
            'miValues' => [], 'ccnValues' => [], 'lcomValues' => [],
        ];

        foreach ($fileResults as $file) {
            if ($file->hasErrors) {
                $metrics['errors']++;
                continue;
            }

            $metrics['loc'] += $file->loc['loc'] ?? 0;
            $metrics['lloc'] += $file->loc['lloc'] ?? 0;
            $metrics['cloc'] += $file->loc['cloc'] ?? 0;

            foreach ($file->classes as $class) {
                $metrics['classes']++;
                $metrics['methods'] += $class->methodCount;
                $metrics['miValues'][] = $class->mi;
                $metrics['lcomValues'][] = $class->lcom;

                foreach ($class->methods as $method) {
                    $metrics['ccnValues'][] = $method->ccn;
                }
            }

            if (empty($file->classes) && $file->mi > 0) {
                $metrics['miValues'][] = $file->mi;
            }
        }

        return $metrics;
    }

    private function countViolations(array $metrics, array $thresholds): array
    {
        return [
            'ccn' => count(array_filter($metrics['ccnValues'], fn($v) => $v > $thresholds['ccn'])),
            'lcom' => count(array_filter($metrics['lcomValues'], fn($v) => $v > $thresholds['lcom'])),
            'mi' => count(array_filter($metrics['miValues'], fn($v) => $v < $thresholds['mi'])),
        ];
    }

    private function calculateRatings(array $metrics): array
    {
        return [
            'mi' => $this->rateMi($this->average($metrics['miValues'])),
            'ccn' => $this->rateCcn($this->average($metrics['ccnValues'])),
            'lcom' => $this->rateLcom($this->average($metrics['lcomValues'], 4)),
        ];
    }

    private function average(array $values, int $precision = 2): float
    {
        return !empty($values) ? round(array_sum($values) / count($values), $precision) : 0;
    }

    private function rateMi(float $value): string
    {
        return match (true) {
            $value >= 85 => 'A',
            $value >= 65 => 'B',
            $value >= 40 => 'C',
            $value >= 20 => 'D',
            default => 'F',
        };
    }

    private function rateCcn(float $value): string
    {
        return match (true) {
            $value <= 4 => 'A',
            $value <= 7 => 'B',
            $value <= 10 => 'C',
            $value <= 15 => 'D',
            default => 'F',
        };
    }

    private function rateLcom(float $value): string
    {
        return match (true) {
            $value <= 0.2 => 'A',
            $value <= 0.4 => 'B',
            $value <= 0.6 => 'C',
            $value <= 0.8 => 'D',
            default => 'F',
        };
    }
}
