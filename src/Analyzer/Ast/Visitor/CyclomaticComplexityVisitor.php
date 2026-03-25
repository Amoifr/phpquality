<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Visitor to calculate Cyclomatic Complexity (McCabe)
 * CCN = Number of decision points + 1
 *
 * Decision points counted:
 * - if, elseif
 * - for, foreach, while, do-while
 * - case (in switch)
 * - catch
 * - ternary operator ?:
 * - null coalesce ??
 * - && and ||
 * - match arms
 */
class CyclomaticComplexityVisitor extends AbstractMetricVisitor
{
    private ?string $currentClass = null;
    private ?string $currentMethod = null;
    private int $complexity = 1;
    private array $methodComplexities = [];
    private array $classComplexities = [];

    /** @var array<class-string> Node classes that add 1 to complexity */
    private const DECISION_NODES = [
        Stmt\If_::class,
        Stmt\ElseIf_::class,
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\Catch_::class,
        Expr\Ternary::class,
        Expr\BinaryOp\Coalesce::class,
        Expr\BinaryOp\BooleanAnd::class,
        Expr\BinaryOp\BooleanOr::class,
        Expr\BinaryOp\LogicalAnd::class,
        Expr\BinaryOp\LogicalOr::class,
    ];

    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentClass = null;
        $this->currentMethod = null;
        $this->complexity = 1;
        $this->methodComplexities = [];
        $this->classComplexities = [];
        return null;
    }

    public function enterNode(Node $node): ?int
    {
        $this->trackClassAndMethod($node);
        $this->countDecisionPoint($node);
        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        $this->storeMethodComplexity($node);
        $this->leaveClass($node);
        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $this->results = [
            'methods' => $this->methodComplexities,
            'classes' => $this->classComplexities,
            'summary' => $this->calculateSummary(),
        ];
        return null;
    }

    private function trackClassAndMethod(Node $node): void
    {
        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_) {
            $this->currentClass = $node->name?->toString() ?? 'Anonymous';
            $this->classComplexities[$this->currentClass] = [
                'methods' => [],
                'totalCcn' => 0,
                'maxCcn' => 0,
            ];
        }

        if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_) {
            $this->currentMethod = $node->name->toString();
            $this->complexity = 1;
        }
    }

    private function countDecisionPoint(Node $node): void
    {
        if ($this->currentMethod === null) {
            return;
        }

        // Check against decision node types
        if (in_array($node::class, self::DECISION_NODES, true)) {
            $this->complexity++;
            return;
        }

        // Case with condition (not default)
        if ($node instanceof Stmt\Case_ && $node->cond !== null) {
            $this->complexity++;
            return;
        }

        // PHP 8 match expression - each arm adds complexity
        if ($node instanceof Expr\Match_) {
            foreach ($node->arms as $arm) {
                if ($arm->conds !== null) {
                    $this->complexity += count($arm->conds);
                }
            }
        }
    }

    private function storeMethodComplexity(Node $node): void
    {
        if (!($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_)) {
            return;
        }

        $methodName = $node->name->toString();
        $className = $this->currentClass ?? '__global__';

        $this->methodComplexities[] = [
            'class' => $className,
            'method' => $methodName,
            'ccn' => $this->complexity,
            'rating' => $this->getRating($this->complexity),
        ];

        if (isset($this->classComplexities[$className])) {
            $this->classComplexities[$className]['methods'][$methodName] = $this->complexity;
            $this->classComplexities[$className]['totalCcn'] += $this->complexity;
            $this->classComplexities[$className]['maxCcn'] = max(
                $this->classComplexities[$className]['maxCcn'],
                $this->complexity
            );
        }

        $this->currentMethod = null;
    }

    private function leaveClass(Node $node): void
    {
        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_) {
            $this->currentClass = null;
        }
    }

    private function getRating(int $ccn): string
    {
        return match (true) {
            $ccn <= 4 => 'A',
            $ccn <= 7 => 'B',
            $ccn <= 10 => 'C',
            $ccn <= 15 => 'D',
            default => 'F',
        };
    }

    private function calculateSummary(): array
    {
        if (empty($this->methodComplexities)) {
            return [
                'totalMethods' => 0,
                'averageCcn' => 0,
                'maxCcn' => 0,
                'totalCcn' => 0,
            ];
        }

        $ccnValues = array_column($this->methodComplexities, 'ccn');

        return [
            'totalMethods' => count($this->methodComplexities),
            'averageCcn' => round(array_sum($ccnValues) / count($ccnValues), 2),
            'maxCcn' => max($ccnValues),
            'totalCcn' => array_sum($ccnValues),
        ];
    }

    public function getMethodComplexities(): array
    {
        return $this->methodComplexities;
    }

    public function getAverageCcn(): float
    {
        return $this->results['summary']['averageCcn'] ?? 0;
    }

    public function getMaxCcn(): int
    {
        return $this->results['summary']['maxCcn'] ?? 0;
    }
}
