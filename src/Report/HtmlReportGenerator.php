<?php

declare(strict_types=1);

namespace PhpQuality\Report;

use PhpQuality\Analyzer\GitBlameAnalyzer;
use PhpQuality\Analyzer\Result\ProjectResult;
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

    private bool $enableGitBlame = false;
    private ?string $projectName = null;

    public function __construct(
        private readonly Environment $twig,
        private readonly Filesystem $filesystem,
        private readonly TranslatorInterface $translator,
        private readonly GitBlameAnalyzer $gitBlameAnalyzer,
        private readonly string $resourcesPath,
    ) {}

    public function generate(ProjectResult $result, mixed $outputPath, string $lang = 'en', bool $enableGitBlame = false, ?string $projectName = null): void
    {
        if (!is_string($outputPath)) {
            throw new \InvalidArgumentException('Output path must be a string');
        }

        // Validate and set language
        if (!in_array($lang, self::SUPPORTED_LANGUAGES, true)) {
            $lang = 'en';
        }

        $this->enableGitBlame = $enableGitBlame;
        $this->projectName = $projectName;

        $this->filesystem->mkdir($outputPath);

        // Copy CSS file to output directory
        $cssSource = $this->resourcesPath . '/public/css/report.css';
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
            'projectName' => $this->projectName,
        ];

        // Generate index.html
        $this->generatePage($outputPath, 'index.html', '@PhpQuality/report/index.html.twig', array_merge($commonData, [
            'files' => $result->files,
            'classes' => $classes,
            'classesByCategory' => $result->getClassesByCategory(),
            'worstFiles' => $result->getWorstFiles('ccn', 10),
        ]), $lang);

        // Generate metrics.html (documentation)
        $this->generatePage($outputPath, 'metrics.html', '@PhpQuality/report/metrics.html.twig', $commonData, $lang);

        // Generate ccn.html
        $this->generatePage($outputPath, 'ccn.html', '@PhpQuality/report/ccn.html.twig', array_merge($commonData, [
            'methods' => $methods,
        ]), $lang);

        // Generate mi.html
        $this->generatePage($outputPath, 'mi.html', '@PhpQuality/report/mi.html.twig', array_merge($commonData, [
            'classes' => $classes,
            'categories' => $categories,
        ]), $lang);

        // Generate lcom.html
        $cohesiveClasses = count(array_filter($classes, fn($c) => $c->lcom <= 0.4));
        $this->generatePage($outputPath, 'lcom.html', '@PhpQuality/report/lcom.html.twig', array_merge($commonData, [
            'classes' => $classes,
            'cohesiveClasses' => $cohesiveClasses,
        ]), $lang);

        // Generate loc.html
        $largeFiles = array_filter($result->files, fn($f) => ($f->loc['loc'] ?? 0) > 300);
        $this->generatePage($outputPath, 'loc.html', '@PhpQuality/report/loc.html.twig', array_merge($commonData, [
            'files' => $result->files,
            'largeFiles' => $largeFiles,
        ]), $lang);

        // Generate halstead.html
        $this->generatePage($outputPath, 'halstead.html', '@PhpQuality/report/halstead.html.twig', array_merge($commonData, [
            'files' => $result->files,
            'halsteadSummary' => $halsteadSummary,
            'topOperators' => $topOperators,
        ]), $lang);

        // Generate dependencies.html
        if ($result->dependencies !== null && $result->dependencies->found) {
            $this->generatePage($outputPath, 'dependencies.html', '@PhpQuality/report/dependencies.html.twig', array_merge($commonData, [
                'dependencies' => $result->dependencies,
            ]), $lang);
        }

        // Generate analysis.html (cross-dimension charts)
        $authorStats = $this->enableGitBlame ? $this->gitBlameAnalyzer->analyze($result) : null;
        $this->generatePage($outputPath, 'analysis.html', '@PhpQuality/report/analysis.html.twig', array_merge($commonData, [
            'analysisData' => $this->prepareAnalysisData($result),
            'treeData' => $this->prepareTreeData($result),
            'authorStats' => $authorStats,
        ]), $lang);

        // Generate architecture.html
        if ($result->architecture !== null) {
            $this->generatePage($outputPath, 'architecture.html', '@PhpQuality/report/architecture.html.twig', array_merge($commonData, [
                'architecture' => $result->architecture,
            ]), $lang);
        }

        // Generate coverage.html (always generate, show message if no data)
        $this->generatePage($outputPath, 'coverage.html', '@PhpQuality/report/coverage.html.twig', array_merge($commonData, [
            'coverage' => $result->coverage,
            'coverageData' => ($result->coverage !== null && $result->coverage->found)
                ? $this->prepareCoverageData($result->coverage)
                : null,
        ]), $lang);

        // Generate recommendations.html
        $this->generatePage($outputPath, 'recommendations.html', '@PhpQuality/report/recommendations.html.twig', array_merge($commonData, [
            'recommendations' => $this->generateRecommendations($result),
        ]), $lang);
    }

    private function generateRecommendations(ProjectResult $result): array
    {
        $mustHave = [];
        $niceToHave = [];
        $ifTime = [];

        $classes = $result->getAllClasses();
        $thresholds = $result->summary['thresholds'] ?? [];

        // MUST HAVE: Critical complexity issues (CCN > 15)
        $criticalCcnMethods = [];
        foreach ($classes as $class) {
            foreach ($class->methods as $method) {
                if ($method->ccn > 15) {
                    $criticalCcnMethods[] = [
                        'name' => $class->name . '::' . $method->name . '()',
                        'value' => 'CCN: ' . $method->ccn,
                        'file' => $class->filePath,
                    ];
                }
            }
        }
        if (count($criticalCcnMethods) > 0) {
            $mustHave[] = [
                'category' => 'Complexity',
                'title' => 'Refactor methods with critical complexity',
                'description' => 'These methods have CCN > 15, making them extremely difficult to test and maintain. Split them into smaller, focused methods.',
                'impact' => 'high',
                'items' => $criticalCcnMethods,
            ];
        }

        // MUST HAVE: Unmaintainable code (MI < 20)
        $unmaintainableClasses = [];
        foreach ($classes as $class) {
            if ($class->mi < 20) {
                $unmaintainableClasses[] = [
                    'name' => $class->name,
                    'value' => 'MI: ' . round($class->mi, 1),
                    'file' => $class->filePath,
                ];
            }
        }
        if (count($unmaintainableClasses) > 0) {
            $mustHave[] = [
                'category' => 'Maintainability',
                'title' => 'Refactor unmaintainable classes',
                'description' => 'These classes have a Maintainability Index below 20, indicating they are extremely difficult to maintain. Consider major refactoring or rewriting.',
                'impact' => 'high',
                'items' => $unmaintainableClasses,
            ];
        }

        // MUST HAVE: Architecture layer violations
        if ($result->architecture !== null && count($result->architecture->layerViolations) > 0) {
            $violations = [];
            foreach (array_slice($result->architecture->layerViolations, 0, 20) as $v) {
                $violations[] = [
                    'name' => $v->sourceClass . ' → ' . $v->targetClass,
                    'value' => $v->sourceLayer . ' → ' . $v->targetLayer,
                ];
            }
            $mustHave[] = [
                'category' => 'Architecture',
                'title' => 'Fix layer dependency violations',
                'description' => 'These classes violate clean architecture principles by depending on forbidden layers. This creates tight coupling and makes the code harder to test.',
                'impact' => 'high',
                'items' => $violations,
            ];
        }

        // MUST HAVE: SOLID violations (SRP, DIP)
        if ($result->architecture !== null) {
            $srpViolations = [];
            $dipViolations = [];
            foreach ($result->architecture->solidViolations as $v) {
                if ($v->principle === 'SRP') {
                    $srpViolations[] = ['name' => $v->className, 'value' => $v->message];
                } elseif ($v->principle === 'DIP') {
                    $dipViolations[] = ['name' => $v->className, 'value' => $v->message];
                }
            }
            if (count($srpViolations) > 0) {
                $mustHave[] = [
                    'category' => 'SOLID',
                    'title' => 'Split classes violating Single Responsibility Principle',
                    'description' => 'These classes have too many responsibilities. Split them into smaller, focused classes with a single purpose.',
                    'impact' => 'high',
                    'items' => $srpViolations,
                ];
            }
            if (count($dipViolations) > 0) {
                $niceToHave[] = [
                    'category' => 'SOLID',
                    'title' => 'Introduce abstractions for Dependency Inversion',
                    'description' => 'These classes depend too heavily on concrete implementations. Consider introducing interfaces.',
                    'impact' => 'medium',
                    'items' => $dipViolations,
                ];
            }
        }

        // MUST HAVE: Low test coverage (if available)
        if ($result->coverage !== null && $result->coverage->found && $result->coverage->lineCoverage < 40) {
            $mustHave[] = [
                'category' => 'Testing',
                'title' => 'Increase test coverage urgently',
                'description' => sprintf('Current line coverage is only %.1f%%. Aim for at least 60%% coverage on critical business logic.', $result->coverage->lineCoverage),
                'impact' => 'high',
                'items' => [],
            ];
        }

        // NICE TO HAVE: High complexity methods (CCN 10-15)
        $highCcnMethods = [];
        foreach ($classes as $class) {
            foreach ($class->methods as $method) {
                if ($method->ccn > 10 && $method->ccn <= 15) {
                    $highCcnMethods[] = [
                        'name' => $class->name . '::' . $method->name . '()',
                        'value' => 'CCN: ' . $method->ccn,
                    ];
                }
            }
        }
        if (count($highCcnMethods) > 0) {
            $niceToHave[] = [
                'category' => 'Complexity',
                'title' => 'Simplify high complexity methods',
                'description' => 'These methods have CCN between 10-15. They would benefit from refactoring to improve testability.',
                'impact' => 'medium',
                'items' => $highCcnMethods,
            ];
        }

        // NICE TO HAVE: Poor maintainability (MI 20-40)
        $poorMiClasses = [];
        foreach ($classes as $class) {
            if ($class->mi >= 20 && $class->mi < 40) {
                $poorMiClasses[] = [
                    'name' => $class->name,
                    'value' => 'MI: ' . round($class->mi, 1),
                ];
            }
        }
        if (count($poorMiClasses) > 0) {
            $niceToHave[] = [
                'category' => 'Maintainability',
                'title' => 'Improve maintainability of struggling classes',
                'description' => 'These classes have MI between 20-40. Consider refactoring to improve code clarity.',
                'impact' => 'medium',
                'items' => $poorMiClasses,
            ];
        }

        // NICE TO HAVE: Low cohesion (LCOM > 0.7)
        $lowCohesionClasses = [];
        foreach ($classes as $class) {
            if ($class->lcom > 0.7) {
                $lowCohesionClasses[] = [
                    'name' => $class->name,
                    'value' => 'LCOM: ' . round($class->lcom, 2),
                ];
            }
        }
        if (count($lowCohesionClasses) > 0) {
            $niceToHave[] = [
                'category' => 'Cohesion',
                'title' => 'Split classes with low cohesion',
                'description' => 'These classes have LCOM > 0.7, indicating methods that don\'t share properties. Consider splitting into multiple focused classes.',
                'impact' => 'medium',
                'items' => $lowCohesionClasses,
            ];
        }

        // NICE TO HAVE: Circular dependencies
        if ($result->architecture !== null && count($result->architecture->circularDependencies) > 0) {
            $cycles = [];
            foreach (array_slice($result->architecture->circularDependencies, 0, 10) as $cycle) {
                $cycles[] = ['name' => implode(' → ', $cycle)];
            }
            $niceToHave[] = [
                'category' => 'Architecture',
                'title' => 'Break circular dependencies',
                'description' => 'These classes form dependency cycles. Introduce interfaces or restructure to break the cycles.',
                'impact' => 'medium',
                'items' => $cycles,
            ];
        }

        // NICE TO HAVE: Improve test coverage (40-60%)
        if ($result->coverage !== null && $result->coverage->found && $result->coverage->lineCoverage >= 40 && $result->coverage->lineCoverage < 60) {
            $lowCoverageFiles = [];
            foreach (array_slice($result->coverage->files, 0, 10) as $file) {
                if ($file['coverage'] < 50) {
                    $lowCoverageFiles[] = [
                        'name' => $file['name'],
                        'value' => $file['coverage'] . '%',
                    ];
                }
            }
            $niceToHave[] = [
                'category' => 'Testing',
                'title' => 'Improve test coverage on critical files',
                'description' => sprintf('Current coverage is %.1f%%. Focus on adding tests for these low-coverage files.', $result->coverage->lineCoverage),
                'impact' => 'medium',
                'items' => $lowCoverageFiles,
            ];
        }

        // IF TIME: Large files (>500 LOC)
        $largeFiles = [];
        foreach ($result->files as $file) {
            $loc = $file->loc['loc'] ?? 0;
            if ($loc > 500) {
                $largeFiles[] = [
                    'name' => $file->relativePath,
                    'value' => $loc . ' LOC',
                ];
            }
        }
        if (count($largeFiles) > 0) {
            $ifTime[] = [
                'category' => 'Size',
                'title' => 'Consider splitting large files',
                'description' => 'These files exceed 500 lines of code. While not critical, smaller files are easier to navigate and maintain.',
                'impact' => 'low',
                'items' => $largeFiles,
            ];
        }

        // IF TIME: Moderate complexity (CCN 7-10)
        $moderateCcnMethods = [];
        foreach ($classes as $class) {
            foreach ($class->methods as $method) {
                if ($method->ccn > 7 && $method->ccn <= 10) {
                    $moderateCcnMethods[] = [
                        'name' => $class->name . '::' . $method->name . '()',
                        'value' => 'CCN: ' . $method->ccn,
                    ];
                }
            }
        }
        if (count($moderateCcnMethods) > 0) {
            $ifTime[] = [
                'category' => 'Complexity',
                'title' => 'Review methods with moderate complexity',
                'description' => 'These methods have CCN between 7-10. They\'re manageable but could be simplified when time permits.',
                'impact' => 'low',
                'items' => array_slice($moderateCcnMethods, 0, 20),
            ];
        }

        // IF TIME: ISP violations
        if ($result->architecture !== null) {
            $ispViolations = [];
            foreach ($result->architecture->solidViolations as $v) {
                if ($v->principle === 'ISP') {
                    $ispViolations[] = ['name' => $v->className, 'value' => $v->message];
                }
            }
            if (count($ispViolations) > 0) {
                $ifTime[] = [
                    'category' => 'SOLID',
                    'title' => 'Split large interfaces (ISP)',
                    'description' => 'These interfaces have many methods. Consider splitting into smaller, focused interfaces.',
                    'impact' => 'low',
                    'items' => $ispViolations,
                ];
            }
        }

        // IF TIME: Improve comment ratio
        $commentRatio = $result->summary['commentRatio'] ?? 0;
        if ($commentRatio < 10) {
            $ifTime[] = [
                'category' => 'Documentation',
                'title' => 'Add documentation to critical code',
                'description' => sprintf('Comment ratio is only %.1f%%. Consider adding PHPDoc blocks to public methods and complex logic.', $commentRatio),
                'impact' => 'low',
                'items' => [],
            ];
        }

        return [
            'must_have' => $mustHave,
            'nice_to_have' => $niceToHave,
            'if_time' => $ifTime,
        ];
    }

    private function prepareCoverageData($coverage): array
    {
        // Prepare coverage distribution for charts
        $distribution = [
            '80-100% (A)' => 0,
            '60-79% (B)' => 0,
            '40-59% (C)' => 0,
            '20-39% (D)' => 0,
            '0-19% (F)' => 0,
        ];

        foreach ($coverage->files as $file) {
            $cov = $file['coverage'];
            match (true) {
                $cov >= 80 => $distribution['80-100% (A)']++,
                $cov >= 60 => $distribution['60-79% (B)']++,
                $cov >= 40 => $distribution['40-59% (C)']++,
                $cov >= 20 => $distribution['20-39% (D)']++,
                default => $distribution['0-19% (F)']++,
            };
        }

        return [
            'distribution' => [
                'labels' => array_keys($distribution),
                'values' => array_values($distribution),
                'colors' => ['#22c55e', '#84cc16', '#eab308', '#f97316', '#ef4444'],
            ],
        ];
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
