<?php

declare(strict_types=1);

namespace PhpQuality\DataCollector;

use PhpQuality\Analyzer\FileAnalyzer;
use PhpQuality\Analyzer\Result\FileResult;
use PhpQuality\Config\ThresholdsConfig;
use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PhpQualityDataCollector extends AbstractDataCollector
{
    private const DEFAULT_THRESHOLDS = [
        'ccn' => 10,
        'lcom' => 0.8,
        'mi' => 20,
    ];

    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer,
        private readonly ThresholdsConfig $thresholdsConfig,
        private readonly string $projectDir,
        private readonly array $excludePaths = ['vendor/', 'var/', 'cache/', 'tests/'],
    ) {}

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $includedFiles = get_included_files();
        $projectFiles = $this->filterProjectFiles($includedFiles);

        $results = [];
        $summary = [
            'totalFiles' => 0,
            'totalClasses' => 0,
            'totalMethods' => 0,
            'violations' => ['ccn' => 0, 'lcom' => 0, 'mi' => 0],
            'averageMi' => 0,
            'averageCcn' => 0,
            'averageLcom' => 0,
        ];

        $thresholds = $this->thresholdsConfig->merge(self::DEFAULT_THRESHOLDS);
        $miValues = [];
        $ccnValues = [];
        $lcomValues = [];

        foreach ($projectFiles as $filePath) {
            $fileResult = $this->fileAnalyzer->analyze($filePath, $this->projectDir);

            if ($fileResult->hasErrors) {
                continue;
            }

            $fileData = $this->extractFileData($fileResult, $thresholds);
            $results[] = $fileData;

            $summary['totalFiles']++;
            $summary['totalClasses'] += count($fileResult->classes);

            foreach ($fileResult->classes as $class) {
                $summary['totalMethods'] += $class->methodCount;
                $lcomValues[] = $class->lcom;
                $miValues[] = $class->mi;

                if ($class->lcom > $thresholds['lcom']) {
                    $summary['violations']['lcom']++;
                }
                if ($class->mi < $thresholds['mi']) {
                    $summary['violations']['mi']++;
                }

                foreach ($class->methods as $method) {
                    $ccnValues[] = $method->ccn;
                    if ($method->ccn > $thresholds['ccn']) {
                        $summary['violations']['ccn']++;
                    }
                }
            }
        }

        $summary['averageMi'] = !empty($miValues) ? round(array_sum($miValues) / count($miValues), 2) : 0;
        $summary['averageCcn'] = !empty($ccnValues) ? round(array_sum($ccnValues) / count($ccnValues), 2) : 0;
        $summary['averageLcom'] = !empty($lcomValues) ? round(array_sum($lcomValues) / count($lcomValues), 4) : 0;

        $this->data = [
            'files' => $results,
            'summary' => $summary,
            'thresholds' => $thresholds,
        ];
    }

    /**
     * @param array<string> $includedFiles
     * @return array<string>
     */
    private function filterProjectFiles(array $includedFiles): array
    {
        $projectDir = $this->projectDir . DIRECTORY_SEPARATOR;

        return array_filter($includedFiles, function (string $filePath) use ($projectDir): bool {
            // Must be under project directory
            if (!str_starts_with($filePath, $projectDir)) {
                return false;
            }

            // Check excluded paths
            foreach ($this->excludePaths as $excludePath) {
                if (str_contains($filePath, DIRECTORY_SEPARATOR . trim($excludePath, '/') . DIRECTORY_SEPARATOR) ||
                    str_contains($filePath, DIRECTORY_SEPARATOR . trim($excludePath, '/'))) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @param array{ccn: int, lcom: float, mi: int} $thresholds
     * @return array<string, mixed>
     */
    private function extractFileData(FileResult $fileResult, array $thresholds): array
    {
        $violations = [];
        $maxCcn = 0;
        $avgLcom = 0;
        $lcomValues = [];

        foreach ($fileResult->classes as $class) {
            $lcomValues[] = $class->lcom;

            if ($class->lcom > $thresholds['lcom']) {
                $violations[] = [
                    'type' => 'lcom',
                    'class' => $class->name,
                    'value' => $class->lcom,
                    'threshold' => $thresholds['lcom'],
                ];
            }

            if ($class->mi < $thresholds['mi']) {
                $violations[] = [
                    'type' => 'mi',
                    'class' => $class->name,
                    'value' => $class->mi,
                    'threshold' => $thresholds['mi'],
                ];
            }

            foreach ($class->methods as $method) {
                $maxCcn = max($maxCcn, $method->ccn);
                if ($method->ccn > $thresholds['ccn']) {
                    $violations[] = [
                        'type' => 'ccn',
                        'class' => $class->name,
                        'method' => $method->name,
                        'value' => $method->ccn,
                        'threshold' => $thresholds['ccn'],
                    ];
                }
            }
        }

        $avgLcom = !empty($lcomValues) ? array_sum($lcomValues) / count($lcomValues) : 0;

        return [
            'path' => $fileResult->relativePath,
            'mi' => $fileResult->mi,
            'miRating' => $fileResult->miRating,
            'maxCcn' => $maxCcn,
            'avgLcom' => round($avgLcom, 4),
            'classCount' => count($fileResult->classes),
            'violations' => $violations,
            'hasViolations' => !empty($violations),
        ];
    }

    public static function getTemplate(): ?string
    {
        return '@PhpQuality/data_collector/phpquality.html.twig';
    }

    public function getName(): string
    {
        return 'phpquality';
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getFiles(): array
    {
        return $this->data['files'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return $this->data['summary'] ?? [];
    }

    /**
     * @return array{ccn: int, lcom: float, mi: int}
     */
    public function getThresholds(): array
    {
        return $this->data['thresholds'] ?? self::DEFAULT_THRESHOLDS;
    }

    public function getTotalViolations(): int
    {
        $summary = $this->getSummary();
        $violations = $summary['violations'] ?? ['ccn' => 0, 'lcom' => 0, 'mi' => 0];

        return $violations['ccn'] + $violations['lcom'] + $violations['mi'];
    }
}
