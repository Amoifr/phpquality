<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer;

use PhpQuality\Analyzer\Result\CoverageResult;

/**
 * Analyzes test coverage from Clover XML format (PHPUnit coverage output)
 */
class CoverageAnalyzer
{
    /**
     * Analyze coverage from a Clover XML file
     */
    public function analyze(string $coveragePath): CoverageResult
    {
        if (!file_exists($coveragePath)) {
            return new CoverageResult(found: false);
        }

        $xml = @simplexml_load_file($coveragePath);
        if ($xml === false) {
            return new CoverageResult(found: false);
        }

        // Try to parse as Clover format
        if (isset($xml->project)) {
            return $this->parseCloverFormat($xml);
        }

        // Try to parse as PHPUnit coverage-xml format
        if ($xml->getName() === 'phpunit') {
            return $this->parsePhpunitFormat($xml);
        }

        return new CoverageResult(found: false);
    }

    /**
     * Analyze coverage by searching for common coverage file locations
     * If no coverage file exists, try to generate one using PHPUnit
     */
    public function analyzeFromProject(string $projectPath, bool $autoGenerate = true): CoverageResult
    {
        $commonPaths = [
            $projectPath . '/coverage.xml',
            $projectPath . '/clover.xml',
            $projectPath . '/build/logs/clover.xml',
            $projectPath . '/build/coverage/clover.xml',
            $projectPath . '/coverage/clover.xml',
            $projectPath . '/var/coverage/clover.xml',
            $projectPath . '/.phpunit.cache/clover.xml',
        ];

        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                $result = $this->analyze($path);
                if ($result->found) {
                    return $result;
                }
            }
        }

        // Try to generate coverage if PHPUnit is available
        if ($autoGenerate) {
            $generatedPath = $this->generateCoverage($projectPath);
            if ($generatedPath !== null) {
                return $this->analyze($generatedPath);
            }
        }

        return new CoverageResult(found: false);
    }

    /**
     * Try to generate coverage using PHPUnit
     */
    private function generateCoverage(string $projectPath): ?string
    {
        $phpunitPaths = [
            $projectPath . '/vendor/bin/phpunit',
            $projectPath . '/bin/phpunit',
        ];

        $phpunitBin = null;
        foreach ($phpunitPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $phpunitBin = $path;
                break;
            }
        }

        if ($phpunitBin === null) {
            return null;
        }

        // Check for phpunit.xml or phpunit.xml.dist
        $hasConfig = file_exists($projectPath . '/phpunit.xml')
            || file_exists($projectPath . '/phpunit.xml.dist');

        if (!$hasConfig) {
            return null;
        }

        $coverageFile = $projectPath . '/coverage.xml';

        // Try with XDEBUG_MODE first, then with pcov
        $commands = [
            sprintf('cd %s && XDEBUG_MODE=coverage %s --coverage-clover %s 2>/dev/null',
                escapeshellarg($projectPath),
                escapeshellarg($phpunitBin),
                escapeshellarg($coverageFile)
            ),
            sprintf('cd %s && php -d pcov.enabled=1 %s --coverage-clover %s 2>/dev/null',
                escapeshellarg($projectPath),
                escapeshellarg($phpunitBin),
                escapeshellarg($coverageFile)
            ),
        ];

        foreach ($commands as $command) {
            exec($command, $output, $returnCode);
            if (file_exists($coverageFile) && filesize($coverageFile) > 0) {
                return $coverageFile;
            }
        }

        return null;
    }

    private function parseCloverFormat(\SimpleXMLElement $xml): CoverageResult
    {
        $project = $xml->project;
        $metrics = $project->metrics ?? null;

        if (!$metrics) {
            return new CoverageResult(found: false);
        }

        $totalLines = (int) ($metrics['statements'] ?? 0);
        $coveredLines = (int) ($metrics['coveredstatements'] ?? 0);
        $totalMethods = (int) ($metrics['methods'] ?? 0);
        $coveredMethods = (int) ($metrics['coveredmethods'] ?? 0);
        $totalClasses = (int) ($metrics['classes'] ?? 0);

        // PHPUnit Clover doesn't include coveredclasses, calculate it ourselves
        $coveredClasses = (int) ($metrics['coveredclasses'] ?? 0);
        if ($coveredClasses === 0 && $totalClasses > 0) {
            $coveredClasses = $this->countFullyCoveredClasses($project);
        }

        $lineCoverage = $totalLines > 0 ? round(($coveredLines / $totalLines) * 100, 2) : 0;
        $methodCoverage = $totalMethods > 0 ? round(($coveredMethods / $totalMethods) * 100, 2) : 0;
        $classCoverage = $totalClasses > 0 ? round(($coveredClasses / $totalClasses) * 100, 2) : 0;

        $files = $this->extractFileCoverage($project);
        $packages = $this->extractPackageCoverage($project);

        $generatedAt = (string) ($project['timestamp'] ?? null);
        if ($generatedAt) {
            $generatedAt = date('Y-m-d H:i:s', (int) $generatedAt);
        }

        return new CoverageResult(
            found: true,
            lineCoverage: $lineCoverage,
            methodCoverage: $methodCoverage,
            classCoverage: $classCoverage,
            coveredLines: $coveredLines,
            totalLines: $totalLines,
            coveredMethods: $coveredMethods,
            totalMethods: $totalMethods,
            coveredClasses: $coveredClasses,
            totalClasses: $totalClasses,
            files: $files,
            packages: $packages,
            rating: $this->calculateRating($lineCoverage),
            generatedAt: $generatedAt ?: null,
        );
    }

    private function parsePhpunitFormat(\SimpleXMLElement $xml): CoverageResult
    {
        // PHPUnit XML format parsing
        $directory = $xml->directory ?? null;

        if (!$directory) {
            return new CoverageResult(found: false);
        }

        $totals = $directory->totals ?? null;
        if (!$totals) {
            return new CoverageResult(found: false);
        }

        $lines = $totals->lines ?? null;
        $methods = $totals->methods ?? null;
        $classes = $totals->classes ?? null;

        $lineCoverage = (float) ($lines['percent'] ?? 0);
        $methodCoverage = (float) ($methods['percent'] ?? 0);
        $classCoverage = (float) ($classes['percent'] ?? 0);

        return new CoverageResult(
            found: true,
            lineCoverage: $lineCoverage,
            methodCoverage: $methodCoverage,
            classCoverage: $classCoverage,
            coveredLines: 0,
            totalLines: 0,
            coveredMethods: 0,
            totalMethods: 0,
            coveredClasses: 0,
            totalClasses: 0,
            files: [],
            packages: [],
            rating: $this->calculateRating($lineCoverage),
            generatedAt: null,
        );
    }

    private function extractFileCoverage(\SimpleXMLElement $project): array
    {
        $files = [];

        // Direct file elements
        foreach ($project->file ?? [] as $file) {
            $fileData = $this->parseFileElement($file);
            if ($fileData) {
                $files[] = $fileData;
            }
        }

        // Files inside packages
        foreach ($project->package ?? [] as $package) {
            foreach ($package->file ?? [] as $file) {
                $fileData = $this->parseFileElement($file);
                if ($fileData) {
                    $fileData['package'] = (string) ($package['name'] ?? 'default');
                    $files[] = $fileData;
                }
            }
        }

        // Sort by coverage (ascending - worst first)
        usort($files, fn($a, $b) => $a['coverage'] <=> $b['coverage']);

        return $files;
    }

    private function parseFileElement(\SimpleXMLElement $file): ?array
    {
        $metrics = $file->metrics ?? null;
        if (!$metrics) {
            return null;
        }

        $totalLines = (int) ($metrics['statements'] ?? 0);
        $coveredLines = (int) ($metrics['coveredstatements'] ?? 0);
        $totalMethods = (int) ($metrics['methods'] ?? 0);
        $coveredMethods = (int) ($metrics['coveredmethods'] ?? 0);

        if ($totalLines === 0) {
            return null;
        }

        $coverage = round(($coveredLines / $totalLines) * 100, 2);
        $path = (string) ($file['name'] ?? '');

        // Extract classes and their coverage
        $classes = [];
        foreach ($file->class ?? [] as $class) {
            $classMetrics = $class->metrics ?? null;
            if ($classMetrics) {
                $classTotal = (int) ($classMetrics['statements'] ?? 0);
                $classCovered = (int) ($classMetrics['coveredstatements'] ?? 0);
                $classCoverage = $classTotal > 0 ? round(($classCovered / $classTotal) * 100, 2) : 0;

                $classes[] = [
                    'name' => (string) ($class['name'] ?? 'Unknown'),
                    'namespace' => (string) ($class['namespace'] ?? ''),
                    'coverage' => $classCoverage,
                    'coveredLines' => $classCovered,
                    'totalLines' => $classTotal,
                    'methods' => (int) ($classMetrics['methods'] ?? 0),
                    'coveredMethods' => (int) ($classMetrics['coveredmethods'] ?? 0),
                    'rating' => $this->calculateRating($classCoverage),
                ];
            }
        }

        // Extract uncovered lines
        $uncoveredLines = [];
        foreach ($file->line ?? [] as $line) {
            $count = (int) ($line['count'] ?? 0);
            if ($count === 0 && ((string) ($line['type'] ?? '')) === 'stmt') {
                $uncoveredLines[] = (int) ($line['num'] ?? 0);
            }
        }

        return [
            'path' => $path,
            'name' => basename($path),
            'coverage' => $coverage,
            'coveredLines' => $coveredLines,
            'totalLines' => $totalLines,
            'coveredMethods' => $coveredMethods,
            'totalMethods' => $totalMethods,
            'rating' => $this->calculateRating($coverage),
            'classes' => $classes,
            'uncoveredLines' => array_slice($uncoveredLines, 0, 50), // Limit to first 50
            'package' => '',
        ];
    }

    private function extractPackageCoverage(\SimpleXMLElement $project): array
    {
        $packages = [];

        foreach ($project->package ?? [] as $package) {
            $metrics = $package->metrics ?? null;
            if (!$metrics) {
                continue;
            }

            $totalLines = (int) ($metrics['statements'] ?? 0);
            $coveredLines = (int) ($metrics['coveredstatements'] ?? 0);
            $coverage = $totalLines > 0 ? round(($coveredLines / $totalLines) * 100, 2) : 0;

            $packages[] = [
                'name' => (string) ($package['name'] ?? 'default'),
                'coverage' => $coverage,
                'coveredLines' => $coveredLines,
                'totalLines' => $totalLines,
                'files' => (int) ($metrics['files'] ?? 0),
                'classes' => (int) ($metrics['classes'] ?? 0),
                'rating' => $this->calculateRating($coverage),
            ];
        }

        // Sort by coverage (ascending - worst first)
        usort($packages, fn($a, $b) => $a['coverage'] <=> $b['coverage']);

        return $packages;
    }

    /**
     * Count classes where all methods are covered (100% method coverage)
     */
    private function countFullyCoveredClasses(\SimpleXMLElement $project): int
    {
        $fullyConvered = 0;

        // Check direct file elements
        foreach ($project->file ?? [] as $file) {
            $fullyConvered += $this->countFullyCoveredClassesInFile($file);
        }

        // Check files inside packages
        foreach ($project->package ?? [] as $package) {
            foreach ($package->file ?? [] as $file) {
                $fullyConvered += $this->countFullyCoveredClassesInFile($file);
            }
        }

        return $fullyConvered;
    }

    private function countFullyCoveredClassesInFile(\SimpleXMLElement $file): int
    {
        $count = 0;

        foreach ($file->class ?? [] as $class) {
            $classMetrics = $class->metrics ?? null;
            if (!$classMetrics) {
                continue;
            }

            $totalMethods = (int) ($classMetrics['methods'] ?? 0);
            $coveredMethods = (int) ($classMetrics['coveredmethods'] ?? 0);

            // A class is "covered" if all its methods are covered
            if ($totalMethods > 0 && $totalMethods === $coveredMethods) {
                $count++;
            }
        }

        return $count;
    }

    private function calculateRating(float $coverage): string
    {
        return match (true) {
            $coverage >= 80 => 'A',
            $coverage >= 60 => 'B',
            $coverage >= 40 => 'C',
            $coverage >= 20 => 'D',
            default => 'F',
        };
    }
}