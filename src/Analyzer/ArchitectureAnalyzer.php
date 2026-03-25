<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer;

use PhpQuality\Analyzer\Architecture\LayerDetector;
use PhpQuality\Analyzer\Architecture\SolidAnalyzer;
use PhpQuality\Analyzer\ProjectType\ProjectTypeInterface;
use PhpQuality\Analyzer\Result\ArchitectureResult;
use PhpQuality\Analyzer\Result\FileResult;
use PhpQuality\Analyzer\Result\LayerViolation;

/**
 * Main orchestrator for architecture analysis.
 *
 * Coordinates:
 * - Dependency graph building
 * - Layer detection and assignment
 * - Layer violation detection
 * - SOLID violation detection
 * - Circular dependency detection
 * - Architecture score calculation
 */
class ArchitectureAnalyzer
{
    public function __construct(
        private readonly LayerDetector $layerDetector,
        private readonly SolidAnalyzer $solidAnalyzer,
    ) {}

    /**
     * Analyze architecture from file results
     *
     * @param array<FileResult> $fileResults
     */
    public function analyze(array $fileResults, ?ProjectTypeInterface $projectType = null): ArchitectureResult
    {
        // 1. Build dependency graph
        $dependencyGraph = $this->buildDependencyGraph($fileResults);

        // 2. Detect layers for all classes
        $classNames = array_keys($dependencyGraph['nodes']);
        $layerAssignments = $this->layerDetector->detectLayers($classNames, $projectType);

        // 3. Detect layer violations
        $layerViolations = $this->detectLayerViolations($dependencyGraph, $layerAssignments, $fileResults);

        // 4. Detect SOLID violations
        $solidViolations = $this->solidAnalyzer->analyze($fileResults);

        // 5. Detect circular dependencies
        $circularDependencies = $this->detectCircularDependencies($dependencyGraph);

        // 6. Calculate layer statistics
        $layerStats = $this->layerDetector->getLayerStats($layerAssignments);

        // 7. Calculate architecture score
        $score = $this->calculateScore(
            count($layerViolations),
            count($solidViolations),
            count($circularDependencies),
            count($classNames)
        );

        return new ArchitectureResult(
            dependencyGraph: $dependencyGraph,
            layerAssignments: $layerAssignments,
            layerViolations: $layerViolations,
            solidViolations: $solidViolations,
            circularDependencies: $circularDependencies,
            layerStats: $layerStats,
            score: $score,
            rating: $this->getScoreRating($score),
        );
    }

    /**
     * Build dependency graph from file results
     */
    private function buildDependencyGraph(array $fileResults): array
    {
        $nodes = [];
        $edges = [];

        foreach ($fileResults as $fileResult) {
            if ($fileResult->hasErrors) {
                continue;
            }

            $namespace = $fileResult->dependencies['namespace'] ?? null;

            // Add class nodes
            foreach ($fileResult->dependencies['classDefinitions'] ?? [] as $name => $info) {
                $fqn = $info['fqn'] ?? ($namespace ? $namespace . '\\' . $name : $name);
                $nodes[$fqn] = [
                    'name' => $name,
                    'fqn' => $fqn,
                    'type' => 'class',
                    'file' => $fileResult->relativePath,
                    'extends' => $info['extends'],
                    'implements' => $info['implements'],
                ];
            }

            // Add interface nodes
            foreach ($fileResult->dependencies['interfaceDefinitions'] ?? [] as $name => $info) {
                $fqn = $info['fqn'] ?? ($namespace ? $namespace . '\\' . $name : $name);
                $nodes[$fqn] = [
                    'name' => $name,
                    'fqn' => $fqn,
                    'type' => 'interface',
                    'file' => $fileResult->relativePath,
                    'methods' => $info['methods'],
                ];
            }

            // Add edges from dependencies
            foreach ($fileResult->dependencies['dependencies'] ?? [] as $dep) {
                $context = $dep['context'] ?? null;
                if ($context === null || $context === 'file') {
                    continue;
                }

                // Find source FQN
                $sourceFqn = null;
                foreach ($fileResult->dependencies['classDefinitions'] ?? [] as $name => $info) {
                    if ($name === $context) {
                        $sourceFqn = $info['fqn'] ?? ($namespace ? $namespace . '\\' . $name : $name);
                        break;
                    }
                }

                if ($sourceFqn === null) {
                    continue;
                }

                $targetFqn = $dep['fqn'];
                $edgeKey = $sourceFqn . '->' . $targetFqn;

                if (!isset($edges[$edgeKey])) {
                    $edges[$edgeKey] = [
                        'from' => $sourceFqn,
                        'to' => $targetFqn,
                        'types' => [],
                        'lines' => [],
                    ];
                }

                $edges[$edgeKey]['types'][] = $dep['type'];
                $edges[$edgeKey]['lines'][] = $dep['line'];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => array_values($edges),
        ];
    }

    /**
     * Detect layer violations (forbidden dependencies between layers)
     */
    private function detectLayerViolations(array $dependencyGraph, array $layerAssignments, array $fileResults): array
    {
        $violations = [];
        $filePathMap = $this->buildFilePathMap($fileResults);

        foreach ($dependencyGraph['edges'] as $edge) {
            $fromFqn = $edge['from'];
            $toFqn = $edge['to'];

            $fromLayer = $layerAssignments[$fromFqn] ?? LayerDetector::LAYER_OTHER;
            $toLayer = $layerAssignments[$toFqn] ?? LayerDetector::LAYER_OTHER;

            // Skip if both are "Other" or same layer
            if ($fromLayer === $toLayer) {
                continue;
            }

            // Check if dependency is allowed
            if (!$this->layerDetector->isDependencyAllowed($fromLayer, $toLayer)) {
                $line = $edge['lines'][0] ?? 0;
                $depType = $edge['types'][0] ?? 'dependency';
                $filePath = $filePathMap[$fromFqn] ?? '';

                $violations[] = new LayerViolation(
                    sourceClass: $fromFqn,
                    sourceLayer: $fromLayer,
                    targetClass: $toFqn,
                    targetLayer: $toLayer,
                    dependencyType: $depType,
                    line: $line,
                    filePath: $filePath,
                    severity: $this->getViolationSeverity($fromLayer, $toLayer),
                );
            }
        }

        return $violations;
    }

    /**
     * Build a map of FQN to file path
     */
    private function buildFilePathMap(array $fileResults): array
    {
        $map = [];
        foreach ($fileResults as $fileResult) {
            $namespace = $fileResult->dependencies['namespace'] ?? null;
            foreach ($fileResult->dependencies['classDefinitions'] ?? [] as $name => $info) {
                $fqn = $info['fqn'] ?? ($namespace ? $namespace . '\\' . $name : $name);
                $map[$fqn] = $fileResult->path;
            }
        }
        return $map;
    }

    /**
     * Detect circular dependencies using Tarjan's algorithm
     */
    private function detectCircularDependencies(array $dependencyGraph): array
    {
        $graph = [];

        // Build adjacency list
        foreach ($dependencyGraph['edges'] as $edge) {
            $from = $edge['from'];
            $to = $edge['to'];

            if (!isset($graph[$from])) {
                $graph[$from] = [];
            }
            $graph[$from][] = $to;
        }

        // Find strongly connected components
        $sccs = $this->tarjanSCC($graph);

        // Filter to only cycles (SCCs with more than 1 node or self-references)
        $cycles = [];
        foreach ($sccs as $scc) {
            if (count($scc) > 1) {
                $cycles[] = $scc;
            } elseif (count($scc) === 1) {
                $node = $scc[0];
                if (isset($graph[$node]) && in_array($node, $graph[$node], true)) {
                    $cycles[] = $scc;
                }
            }
        }

        return $cycles;
    }

    /**
     * Tarjan's algorithm for finding strongly connected components
     */
    private function tarjanSCC(array $graph): array
    {
        $index = 0;
        $stack = [];
        $onStack = [];
        $indices = [];
        $lowlinks = [];
        $sccs = [];

        $strongConnect = function (string $v) use (&$graph, &$index, &$stack, &$onStack, &$indices, &$lowlinks, &$sccs, &$strongConnect): void {
            $indices[$v] = $index;
            $lowlinks[$v] = $index;
            $index++;
            $stack[] = $v;
            $onStack[$v] = true;

            foreach ($graph[$v] ?? [] as $w) {
                if (!isset($indices[$w])) {
                    $strongConnect($w);
                    $lowlinks[$v] = min($lowlinks[$v], $lowlinks[$w]);
                } elseif ($onStack[$w] ?? false) {
                    $lowlinks[$v] = min($lowlinks[$v], $indices[$w]);
                }
            }

            if ($lowlinks[$v] === $indices[$v]) {
                $scc = [];
                do {
                    $w = array_pop($stack);
                    $onStack[$w] = false;
                    $scc[] = $w;
                } while ($w !== $v);
                $sccs[] = $scc;
            }
        };

        foreach (array_keys($graph) as $v) {
            if (!isset($indices[$v])) {
                $strongConnect($v);
            }
        }

        // Also check nodes that might only be targets
        foreach ($graph as $edges) {
            foreach ($edges as $v) {
                if (!isset($indices[$v])) {
                    $strongConnect($v);
                }
            }
        }

        return $sccs;
    }

    /**
     * Get violation severity based on layers involved
     */
    private function getViolationSeverity(string $fromLayer, string $toLayer): string
    {
        // Domain depending on anything other than Other is critical
        if ($fromLayer === LayerDetector::LAYER_DOMAIN) {
            return 'error';
        }

        // Infrastructure depending on Controller/Application is bad
        if ($fromLayer === LayerDetector::LAYER_INFRASTRUCTURE &&
            in_array($toLayer, [LayerDetector::LAYER_CONTROLLER, LayerDetector::LAYER_APPLICATION])) {
            return 'error';
        }

        return 'warning';
    }

    /**
     * Calculate architecture score (0-100)
     */
    private function calculateScore(int $layerViolations, int $solidViolations, int $circularDeps, int $totalClasses): float
    {
        if ($totalClasses === 0) {
            return 100.0;
        }

        $score = 100.0;

        // Deduct for layer violations (more severe)
        $score -= min(40, $layerViolations * 5);

        // Deduct for SOLID violations
        $score -= min(30, $solidViolations * 2);

        // Deduct for circular dependencies (very severe)
        $score -= min(30, $circularDeps * 10);

        return max(0, $score);
    }

    /**
     * Get rating from score
     */
    private function getScoreRating(float $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 50 => 'C',
            $score >= 30 => 'D',
            default => 'F',
        };
    }
}
