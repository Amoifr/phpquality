<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

/**
 * Contains the complete architecture analysis results.
 *
 * Includes:
 * - Dependency graph between classes
 * - Layer assignments for each class
 * - Layer violations (forbidden dependencies)
 * - SOLID principle violations
 * - Circular dependencies
 * - Overall architecture score
 */
final class ArchitectureResult
{
    /**
     * @param array $dependencyGraph  Graph of class dependencies
     * @param array $layerAssignments Map of FQN => Layer name
     * @param array<LayerViolation> $layerViolations
     * @param array<SolidViolation> $solidViolations
     * @param array $circularDependencies List of cycles [['A', 'B', 'C', 'A'], ...]
     * @param array $layerStats Statistics per layer
     */
    public function __construct(
        public readonly array $dependencyGraph,
        public readonly array $layerAssignments,
        public readonly array $layerViolations,
        public readonly array $solidViolations,
        public readonly array $circularDependencies,
        public readonly array $layerStats,
        public readonly float $score,
        public readonly string $rating,
    ) {}

    public function getLayerViolationCount(): int
    {
        return count($this->layerViolations);
    }

    public function getSolidViolationCount(): int
    {
        return count($this->solidViolations);
    }

    public function getCircularDependencyCount(): int
    {
        return count($this->circularDependencies);
    }

    /**
     * Get SOLID violations grouped by principle
     */
    public function getSolidViolationsByPrinciple(): array
    {
        $grouped = [
            SolidViolation::SRP => [],
            SolidViolation::OCP => [],
            SolidViolation::ISP => [],
            SolidViolation::DIP => [],
        ];

        foreach ($this->solidViolations as $violation) {
            $grouped[$violation->principle][] = $violation;
        }

        return $grouped;
    }

    /**
     * Get layer violations grouped by source layer
     */
    public function getLayerViolationsBySource(): array
    {
        $grouped = [];
        foreach ($this->layerViolations as $violation) {
            $grouped[$violation->sourceLayer][] = $violation;
        }
        return $grouped;
    }

    /**
     * Get classes per layer
     */
    public function getClassesByLayer(): array
    {
        $grouped = [];
        foreach ($this->layerAssignments as $fqn => $layer) {
            if (!isset($grouped[$layer])) {
                $grouped[$layer] = [];
            }
            $grouped[$layer][] = $fqn;
        }
        return $grouped;
    }

    /**
     * Get dependency matrix (layer to layer counts)
     */
    public function getDependencyMatrix(): array
    {
        $matrix = [];
        $layers = array_unique(array_values($this->layerAssignments));

        // Initialize matrix
        foreach ($layers as $from) {
            $matrix[$from] = [];
            foreach ($layers as $to) {
                $matrix[$from][$to] = 0;
            }
        }

        // Count dependencies
        foreach ($this->dependencyGraph['edges'] ?? [] as $edge) {
            $fromLayer = $this->layerAssignments[$edge['from']] ?? 'Other';
            $toLayer = $this->layerAssignments[$edge['to']] ?? 'Other';
            if (isset($matrix[$fromLayer][$toLayer])) {
                $matrix[$fromLayer][$toLayer]++;
            }
        }

        return $matrix;
    }

    public function toArray(): array
    {
        return [
            'dependencyGraph' => $this->dependencyGraph,
            'layerAssignments' => $this->layerAssignments,
            'layerViolations' => array_map(fn($v) => $v->toArray(), $this->layerViolations),
            'solidViolations' => array_map(fn($v) => $v->toArray(), $this->solidViolations),
            'circularDependencies' => $this->circularDependencies,
            'layerStats' => $this->layerStats,
            'score' => $this->score,
            'rating' => $this->rating,
            'summary' => [
                'layerViolationCount' => $this->getLayerViolationCount(),
                'solidViolationCount' => $this->getSolidViolationCount(),
                'circularDependencyCount' => $this->getCircularDependencyCount(),
            ],
        ];
    }
}
