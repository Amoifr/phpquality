<?php

declare(strict_types=1);

namespace App\Report;

use App\Analyzer\GitBlameAnalyzer;
use App\Analyzer\Result\ProjectResult;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class HtmlReportGenerator implements ReportGeneratorInterface
{
    private const SUPPORTED_LANGUAGES = [
        'en', 'fr', 'de', 'es', 'it', 'pt', 'nl', 'pl', 'ru', 'ja',
        'zh', 'ko', 'ar', 'cs', 'da', 'el', 'fi', 'he', 'hi', 'hu',
        'id', 'ro', 'sk', 'sv', 'th', 'tr', 'uk', 'vi', 'bg', 'hr',
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly Filesystem $filesystem,
        private readonly TranslatorInterface $translator,
        private readonly GitBlameAnalyzer $gitBlameAnalyzer,
    ) {}

    public function generate(ProjectResult $result, mixed $outputPath, string $lang = 'en'): void
    {
        if (!is_string($outputPath)) {
            throw new \InvalidArgumentException('Output path must be a string');
        }

        // Validate and set language
        if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
            $lang = 'en';
        }

        $this->filesystem->mkdir($outputPath);

        // Copy CSS file to output directory
        $cssSource = dirname(__DIR__, 2) . '/assets/css/report.css';
        if (file_exists($cssSource)) {
            $this->filesystem->copy($cssSource, $outputPath . '/report.css', true);
        }

        $chartData = $this->prepareChartData($result);
        $classes = $result->getAllClasses();
        $methods = $this->getAllMethods($result);
        $categories = $this->getUniqueCategories($classes);
        $halsteadSummary = $this->calculateHalsteadSummary($result);
        $topOperators = $this->getTopOperators($result);

        $commonData = [
            'project' => $result,
            'summary' => $result->summary,
            'chartData' => $chartData,
            'lang' => $lang,
        ];

        // Generate index.html
        $this->generatePage($outputPath, 'index.html', 'report/index.html.twig', array_merge($commonData, [
            'files' => $result->files,
            'classes' => $classes,
            'classesByCategory' => $result->getClassesByCategory(),
            'worstFiles' => $result->getWorstFiles('ccn', 10),
        ]), $lang);

        // Generate metrics.html (documentation)
        $this->generatePage($outputPath, 'metrics.html', 'report/metrics.html.twig', $commonData, $lang);

        // Generate ccn.html
        $this->generatePage($outputPath, 'ccn.html', 'report/ccn.html.twig', array_merge($commonData, [
            'methods' => $methods,
        ]), $lang);

        // Generate mi.html
        $this->generatePage($outputPath, 'mi.html', 'report/mi.html.twig', array_merge($commonData, [
            'classes' => $classes,
            'categories' => $categories,
        ]), $lang);

        // Generate lcom.html
        $cohesiveClasses = count(array_filter($classes, fn($c) => $c->lcom <= 0.4));
        $this->generatePage($outputPath, 'lcom.html', 'report/lcom.html.twig', array_merge($commonData, [
            'classes' => $classes,
            'cohesiveClasses' => $cohesiveClasses,
        ]), $lang);

        // Generate loc.html
        $largeFiles = array_filter($result->files, fn($f) => ($f->loc['loc'] ?? 0) > 300);
        $this->generatePage($outputPath, 'loc.html', 'report/loc.html.twig', array_merge($commonData, [
            'files' => $result->files,
            'largeFiles' => $largeFiles,
        ]), $lang);

        // Generate halstead.html
        $this->generatePage($outputPath, 'halstead.html', 'report/halstead.html.twig', array_merge($commonData, [
            'files' => $result->files,
            'halsteadSummary' => $halsteadSummary,
            'topOperators' => $topOperators,
        ]), $lang);

        // Generate dependencies.html
        if ($result->dependencies !== null && $result->dependencies->found) {
            $this->generatePage($outputPath, 'dependencies.html', 'report/dependencies.html.twig', array_merge($commonData, [
                'dependencies' => $result->dependencies,
            ]), $lang);
        }

        // Generate analysis.html (cross-dimension charts)
        $authorStats = $this->gitBlameAnalyzer->analyze($result);
        $this->generatePage($outputPath, 'analysis.html', 'report/analysis.html.twig', array_merge($commonData, [
            'analysisData' => $this->prepareAnalysisData($result),
            'treeData' => $this->prepareTreeData($result),
            'authorStats' => $authorStats,
        ]), $lang);
    }

    private function generatePage(string $outputPath, string $filename, string $template, array $data, string $lang): void
    {
        // Set locale for this page generation
        $this->translator->setLocale($lang);

        $html = $this->twig->render($template, $data);
        $this->filesystem->dumpFile($outputPath . '/' . $filename, $html);
    }

    private function getAllMethods(ProjectResult $result): array
    {
        $methods = [];
        foreach ($result->files as $file) {
            foreach ($file->classes as $class) {
                foreach ($class->methods as $method) {
                    $methods[] = $method;
                }
            }
        }
        // Sort by CCN descending
        usort($methods, fn($a, $b) => $b->ccn <=> $a->ccn);
        return $methods;
    }

    private function getUniqueCategories(array $classes): array
    {
        $categories = [];
        foreach ($classes as $class) {
            if ($class->category && !in_array($class->category, $categories, true)) {
                $categories[] = $class->category;
            }
        }
        sort($categories);
        return $categories;
    }

    private function calculateHalsteadSummary(ProjectResult $result): array
    {
        $totalVolume = 0;
        $totalEffort = 0;
        $totalBugs = 0;
        $difficulties = [];
        $n1 = 0;
        $n2 = 0;
        $N1 = 0;
        $N2 = 0;
        $vocabulary = 0;
        $length = 0;

        foreach ($result->files as $file) {
            if ($file->hasErrors || empty($file->halstead)) {
                continue;
            }
            $totalVolume += $file->halstead['volume'] ?? 0;
            $totalEffort += $file->halstead['effort'] ?? 0;
            $totalBugs += $file->halstead['bugs'] ?? 0;
            if (isset($file->halstead['difficulty'])) {
                $difficulties[] = $file->halstead['difficulty'];
            }
            $n1 += $file->halstead['n1'] ?? 0;
            $n2 += $file->halstead['n2'] ?? 0;
            $N1 += $file->halstead['N1'] ?? 0;
            $N2 += $file->halstead['N2'] ?? 0;
            $vocabulary += $file->halstead['vocabulary'] ?? 0;
            $length += $file->halstead['length'] ?? 0;
        }

        $avgDifficulty = !empty($difficulties) ? array_sum($difficulties) / count($difficulties) : 0;

        return [
            'totalVolume' => $totalVolume,
            'totalEffort' => $totalEffort,
            'totalBugs' => $totalBugs,
            'avgDifficulty' => $avgDifficulty,
            'n1' => $n1,
            'n2' => $n2,
            'N1' => $N1,
            'N2' => $N2,
            'vocabulary' => $vocabulary,
            'length' => $length,
        ];
    }

    private function getTopOperators(ProjectResult $result): array
    {
        $operatorCounts = [];

        foreach ($result->files as $file) {
            if ($file->hasErrors || empty($file->halstead['operators'])) {
                continue;
            }
            foreach ($file->halstead['operators'] as $operator => $count) {
                $operatorCounts[$operator] = ($operatorCounts[$operator] ?? 0) + $count;
            }
        }

        arsort($operatorCounts);
        $topOperators = [];
        $i = 0;
        foreach ($operatorCounts as $operator => $count) {
            if ($i++ >= 10) break;
            $topOperators[] = ['operator' => $operator, 'count' => $count];
        }

        return $topOperators;
    }

    private function prepareAnalysisData(ProjectResult $result): array
    {
        $classes = $result->getAllClasses();
        $data = [];

        // Build a map of file path to halstead metrics
        $halsteadByFile = [];
        foreach ($result->files as $file) {
            if (!$file->hasErrors && !empty($file->halstead)) {
                $halsteadByFile[$file->path] = $file->halstead;
            }
        }

        foreach ($classes as $class) {
            // Get file-level Halstead metrics for this class
            $halstead = $halsteadByFile[$class->filePath] ?? [];

            $data[] = [
                'name' => $class->name,
                'fqn' => $class->getFullyQualifiedName(),
                'loc' => $class->totalLoc,
                'maxCcn' => $class->maxCcn,
                'avgCcn' => $class->avgCcn,
                'mi' => $class->mi,
                'miRating' => $class->miRating,
                'lcom' => $class->lcom,
                'lcomRating' => $class->lcomRating,
                'methodCount' => $class->methodCount,
                'propertyCount' => $class->propertyCount,
                'ccnRating' => $this->getCcnRating($class->maxCcn),
                'halsteadVolume' => $halstead['volume'] ?? 0,
                'halsteadDifficulty' => $halstead['difficulty'] ?? 0,
                'halsteadBugs' => $halstead['bugs'] ?? 0,
            ];
        }

        return $data;
    }

    private function getCcnRating(int $maxCcn): string
    {
        return match (true) {
            $maxCcn <= 4 => 'A',
            $maxCcn <= 7 => 'B',
            $maxCcn <= 10 => 'C',
            $maxCcn <= 15 => 'D',
            default => 'F',
        };
    }

    private function getMiRating(float $mi): string
    {
        return match (true) {
            $mi >= 85 => 'A',
            $mi >= 65 => 'B',
            $mi >= 40 => 'C',
            $mi >= 20 => 'D',
            default => 'F',
        };
    }

    private function prepareTreeData(ProjectResult $result): array
    {
        $tree = [];

        foreach ($result->files as $file) {
            $relativePath = $file->relativePath;
            $parts = explode('/', $relativePath);
            $filename = array_pop($parts);

            // File metrics
            $fileData = [
                'name' => $filename,
                'type' => 'file',
                'path' => $relativePath,
                'loc' => $file->loc['loc'] ?? 0,
                'lloc' => $file->loc['lloc'] ?? 0,
                'cloc' => $file->loc['cloc'] ?? 0,
                'maxCcn' => $file->ccn['summary']['maxCcn'] ?? 0,
                'avgCcn' => $file->ccn['summary']['averageCcn'] ?? 0,
                'mi' => $file->mi,
                'miRating' => $file->miRating,
                'ccnRating' => $this->getCcnRating($file->ccn['summary']['maxCcn'] ?? 0),
                'classCount' => count($file->classes),
                'methodCount' => array_sum(array_map(fn($c) => $c->methodCount, $file->classes)),
                'hasErrors' => $file->hasErrors,
                'classes' => [],
            ];

            // Add classes as children
            foreach ($file->classes as $class) {
                $classData = [
                    'name' => $class->name,
                    'type' => 'class',
                    'loc' => $class->totalLoc,
                    'maxCcn' => $class->maxCcn,
                    'avgCcn' => $class->avgCcn,
                    'mi' => $class->mi,
                    'miRating' => $class->miRating,
                    'ccnRating' => $this->getCcnRating($class->maxCcn),
                    'lcom' => $class->lcom,
                    'lcomRating' => $class->lcomRating,
                    'methodCount' => $class->methodCount,
                    'propertyCount' => $class->propertyCount,
                    'methods' => [],
                ];

                // Add methods as children of class
                foreach ($class->methods as $method) {
                    $classData['methods'][] = [
                        'name' => $method->name,
                        'type' => 'method',
                        'loc' => $method->loc,
                        'ccn' => $method->ccn,
                        'ccnRating' => $method->ccnRating,
                        'mi' => $method->mi ?? 0,
                        'miRating' => $method->miRating ?? $this->getMiRating($method->mi ?? 100),
                    ];
                }

                $fileData['classes'][] = $classData;
            }

            // Build directory structure
            $current = &$tree;
            foreach ($parts as $dir) {
                if (!isset($current[$dir])) {
                    $current[$dir] = [
                        'name' => $dir,
                        'type' => 'directory',
                        'children' => [],
                        'files' => [],
                        // Aggregated metrics (will be calculated later)
                        'loc' => 0,
                        'maxCcn' => 0,
                        'avgMi' => 0,
                        'fileCount' => 0,
                        'classCount' => 0,
                    ];
                }
                $current = &$current[$dir]['children'];
            }

            // Add file to current directory level
            $current['__files'][] = $fileData;
        }

        // Convert to array format and calculate aggregated metrics
        return $this->buildTreeArray($tree);
    }

    private function buildTreeArray(array $tree): array
    {
        $result = [];

        foreach ($tree as $key => $node) {
            if ($key === '__files') {
                continue;
            }

            $item = [
                'name' => $node['name'],
                'type' => 'directory',
                'children' => [],
                'loc' => 0,
                'maxCcn' => 0,
                'totalMi' => 0,
                'miCount' => 0,
                'fileCount' => 0,
                'classCount' => 0,
            ];

            // Process subdirectories
            if (!empty($node['children'])) {
                $item['children'] = $this->buildTreeArray($node['children']);

                // Aggregate metrics from subdirectories
                foreach ($item['children'] as $child) {
                    $item['loc'] += $child['loc'];
                    $item['maxCcn'] = max($item['maxCcn'], $child['maxCcn']);
                    if ($child['type'] === 'directory') {
                        $item['totalMi'] += ($child['avgMi'] ?? 0) * ($child['miCount'] ?? 1);
                        $item['miCount'] += $child['miCount'] ?? 1;
                    } else {
                        $item['totalMi'] += $child['mi'] ?? 0;
                        $item['miCount']++;
                    }
                    $item['fileCount'] += $child['type'] === 'file' ? 1 : ($child['fileCount'] ?? 0);
                    $item['classCount'] += $child['classCount'] ?? 0;
                }
            }

            // Process files in this directory
            if (isset($node['children']['__files'])) {
                foreach ($node['children']['__files'] as $file) {
                    $item['children'][] = $file;
                    $item['loc'] += $file['loc'];
                    $item['maxCcn'] = max($item['maxCcn'], $file['maxCcn']);
                    $item['totalMi'] += $file['mi'];
                    $item['miCount']++;
                    $item['fileCount']++;
                    $item['classCount'] += $file['classCount'];
                }
            }

            // Calculate average MI
            $item['avgMi'] = $item['miCount'] > 0 ? $item['totalMi'] / $item['miCount'] : 0;
            $item['miRating'] = $this->getMiRating($item['avgMi']);
            $item['ccnRating'] = $this->getCcnRating($item['maxCcn']);

            // Clean up temporary fields
            unset($item['totalMi']);

            $result[] = $item;
        }

        // Add root-level files
        if (isset($tree['__files'])) {
            foreach ($tree['__files'] as $file) {
                $result[] = $file;
            }
        }

        // Sort: directories first, then files, alphabetically
        usort($result, function ($a, $b) {
            if ($a['type'] === 'directory' && $b['type'] !== 'directory') return -1;
            if ($a['type'] !== 'directory' && $b['type'] === 'directory') return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    private function prepareChartData(ProjectResult $result): array
    {
        $classes = $result->getAllClasses();

        // CCN distribution
        $ccnBuckets = [
            '1-4 (A)' => 0,
            '5-7 (B)' => 0,
            '8-10 (C)' => 0,
            '11-15 (D)' => 0,
            '16+ (F)' => 0,
        ];

        // MI distribution
        $miBuckets = [
            '85-100 (A)' => 0,
            '65-84 (B)' => 0,
            '40-64 (C)' => 0,
            '20-39 (D)' => 0,
            '0-19 (F)' => 0,
        ];

        // LCOM distribution
        $lcomBuckets = [
            '0-0.2 (A)' => 0,
            '0.2-0.4 (B)' => 0,
            '0.4-0.6 (C)' => 0,
            '0.6-0.8 (D)' => 0,
            '0.8-1.0 (F)' => 0,
        ];

        foreach ($classes as $class) {
            // CCN
            $maxCcn = $class->maxCcn;
            match (true) {
                $maxCcn <= 4 => $ccnBuckets['1-4 (A)']++,
                $maxCcn <= 7 => $ccnBuckets['5-7 (B)']++,
                $maxCcn <= 10 => $ccnBuckets['8-10 (C)']++,
                $maxCcn <= 15 => $ccnBuckets['11-15 (D)']++,
                default => $ccnBuckets['16+ (F)']++,
            };

            // MI
            $mi = $class->mi;
            match (true) {
                $mi >= 85 => $miBuckets['85-100 (A)']++,
                $mi >= 65 => $miBuckets['65-84 (B)']++,
                $mi >= 40 => $miBuckets['40-64 (C)']++,
                $mi >= 20 => $miBuckets['20-39 (D)']++,
                default => $miBuckets['0-19 (F)']++,
            };

            // LCOM
            $lcom = $class->lcom;
            match (true) {
                $lcom <= 0.2 => $lcomBuckets['0-0.2 (A)']++,
                $lcom <= 0.4 => $lcomBuckets['0.2-0.4 (B)']++,
                $lcom <= 0.6 => $lcomBuckets['0.4-0.6 (C)']++,
                $lcom <= 0.8 => $lcomBuckets['0.6-0.8 (D)']++,
                default => $lcomBuckets['0.8-1.0 (F)']++,
            };
        }

        // Top complex classes for scatter plot
        $scatterData = [];
        foreach ($classes as $class) {
            $scatterData[] = [
                'name' => $class->name,
                'ccn' => $class->maxCcn,
                'mi' => $class->mi,
                'lcom' => $class->lcom,
                'loc' => $class->totalLoc,
            ];
        }

        return [
            'ccnDistribution' => [
                'labels' => array_keys($ccnBuckets),
                'values' => array_values($ccnBuckets),
                'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
            ],
            'miDistribution' => [
                'labels' => array_keys($miBuckets),
                'values' => array_values($miBuckets),
                'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
            ],
            'lcomDistribution' => [
                'labels' => array_keys($lcomBuckets),
                'values' => array_values($lcomBuckets),
                'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
            ],
            'scatterData' => $scatterData,
        ];
    }
}
