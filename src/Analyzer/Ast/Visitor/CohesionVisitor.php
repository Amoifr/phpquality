<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Identifier;

/**
 * Visitor to calculate Lack of Cohesion of Methods (LCOM)
 *
 * LCOM = 1 - (sum of method-property usage) / (M * A)
 * where:
 * - M = number of methods
 * - A = number of attributes (properties)
 *
 * LCOM ranges from 0 to 1:
 * - 0 = perfectly cohesive (all methods use all properties)
 * - 1 = no cohesion (methods don't share properties)
 */
class CohesionVisitor extends AbstractMetricVisitor
{
    private ?string $currentClass = null;
    private ?string $currentMethod = null;
    private array $classProperties = [];
    private array $methodPropertyUsage = [];
    private array $classResults = [];

    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentClass = null;
        $this->currentMethod = null;
        $this->classProperties = [];
        $this->methodPropertyUsage = [];
        $this->classResults = [];
        return null;
    }

    public function enterNode(Node $node): ?int
    {
        // Track current class
        if ($node instanceof Stmt\Class_) {
            $this->currentClass = $node->name?->toString() ?? 'Anonymous';
            $this->classProperties[$this->currentClass] = [];
            $this->methodPropertyUsage[$this->currentClass] = [];
        }

        // Collect class properties
        if ($node instanceof Stmt\Property && $this->currentClass !== null) {
            foreach ($node->props as $prop) {
                $propName = $prop->name->toString();
                $this->classProperties[$this->currentClass][$propName] = true;
            }
        }

        // Track current method
        if ($node instanceof Stmt\ClassMethod && $this->currentClass !== null) {
            $this->currentMethod = $node->name->toString();

            // Skip constructor, getters, setters for some variants
            // For now, include all methods
            $this->methodPropertyUsage[$this->currentClass][$this->currentMethod] = [];
        }

        // Track property usage within methods
        if ($this->currentClass !== null && $this->currentMethod !== null) {
            $this->trackPropertyUsage($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Stmt\ClassMethod) {
            $this->currentMethod = null;
        }

        if ($node instanceof Stmt\Class_) {
            $this->calculateClassLCOM();
            $this->currentClass = null;
        }

        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $this->results = [
            'classes' => $this->classResults,
            'summary' => $this->calculateSummary(),
        ];
        return null;
    }

    private function trackPropertyUsage(Node $node): void
    {
        // $this->property
        if ($node instanceof Expr\PropertyFetch) {
            if ($node->var instanceof Expr\Variable
                && $node->var->name === 'this'
                && $node->name instanceof Identifier
            ) {
                $propName = $node->name->toString();
                $this->methodPropertyUsage[$this->currentClass][$this->currentMethod][$propName] = true;
            }
        }

        // self::$property (static)
        if ($node instanceof Expr\StaticPropertyFetch) {
            if ($node->class instanceof Node\Name
                && in_array($node->class->toString(), ['self', 'static'], true)
                && $node->name instanceof Node\VarLikeIdentifier
            ) {
                $propName = $node->name->toString();
                $this->methodPropertyUsage[$this->currentClass][$this->currentMethod][$propName] = true;
            }
        }
    }

    private function calculateClassLCOM(): void
    {
        $className = $this->currentClass;
        $properties = $this->classProperties[$className] ?? [];
        $methods = $this->methodPropertyUsage[$className] ?? [];

        $M = count($methods);
        $A = count($properties);

        // Special cases
        if ($M === 0 || $A === 0) {
            $this->classResults[$className] = [
                'lcom' => 0,
                'methods' => $M,
                'attributes' => $A,
                'rating' => 'A',
                'description' => $M === 0 ? 'No methods' : 'No properties',
            ];
            return;
        }

        // Calculate sum of property usage across all methods
        $sumUsage = 0;
        foreach ($methods as $methodName => $usedProperties) {
            // Count only properties that are actually class properties
            $validUsage = array_intersect_key($usedProperties, $properties);
            $sumUsage += count($validUsage);
        }

        // LCOM = 1 - (sumUsage / (M * A))
        $lcom = 1 - ($sumUsage / ($M * $A));
        $lcom = max(0, min(1, $lcom)); // Clamp to [0, 1]

        $this->classResults[$className] = [
            'lcom' => round($lcom, 4),
            'methods' => $M,
            'attributes' => $A,
            'avgPropsPerMethod' => round($sumUsage / $M, 2),
            'rating' => $this->getRating($lcom),
            'methodDetails' => $this->getMethodDetails($methods, $properties),
        ];
    }

    private function getMethodDetails(array $methods, array $properties): array
    {
        $details = [];
        foreach ($methods as $methodName => $usedProperties) {
            $validUsage = array_intersect_key($usedProperties, $properties);
            $details[$methodName] = [
                'usedProperties' => array_keys($validUsage),
                'count' => count($validUsage),
                'ratio' => count($properties) > 0
                    ? round(count($validUsage) / count($properties), 2)
                    : 0,
            ];
        }
        return $details;
    }

    private function getRating(float $lcom): string
    {
        return match (true) {
            $lcom <= 0.2 => 'A',  // Excellent cohesion
            $lcom <= 0.4 => 'B',  // Good cohesion
            $lcom <= 0.6 => 'C',  // Moderate cohesion
            $lcom <= 0.8 => 'D',  // Low cohesion
            default => 'F',       // Very low cohesion
        };
    }

    private function calculateSummary(): array
    {
        if (empty($this->classResults)) {
            return [
                'totalClasses' => 0,
                'averageLcom' => 0,
                'maxLcom' => 0,
            ];
        }

        $lcomValues = array_column($this->classResults, 'lcom');

        return [
            'totalClasses' => count($this->classResults),
            'averageLcom' => round(array_sum($lcomValues) / count($lcomValues), 4),
            'maxLcom' => max($lcomValues),
            'minLcom' => min($lcomValues),
        ];
    }

    public function getClassLCOM(string $className): ?array
    {
        return $this->classResults[$className] ?? null;
    }

    public function getAverageLCOM(): float
    {
        return $this->results['summary']['averageLcom'] ?? 0;
    }
}
