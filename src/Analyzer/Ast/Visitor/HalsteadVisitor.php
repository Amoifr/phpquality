<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

/**
 * Visitor to calculate Halstead complexity metrics
 *
 * Halstead metrics:
 * - n1: Number of distinct operators
 * - n2: Number of distinct operands
 * - N1: Total number of operators
 * - N2: Total number of operands
 * - Vocabulary: n = n1 + n2
 * - Length: N = N1 + N2
 * - Volume: V = N * log2(n)
 * - Difficulty: D = (n1/2) * (N2/n2)
 * - Effort: E = D * V
 */
class HalsteadVisitor extends AbstractMetricVisitor
{
    private array $operators = [];
    private array $operands = [];
    private int $totalOperators = 0;
    private int $totalOperands = 0;

    /** @var array<class-string, string> Node class to operator symbol mapping */
    private const OPERATOR_MAP = [
        // Unary operators
        Expr\UnaryMinus::class => '-unary',
        Expr\UnaryPlus::class => '+unary',
        Expr\BooleanNot::class => '!',
        Expr\BitwiseNot::class => '~',
        Expr\PreInc::class => '++',
        Expr\PostInc::class => '++',
        Expr\PreDec::class => '--',
        Expr\PostDec::class => '--',
        // Control flow
        Stmt\If_::class => 'if',
        Stmt\Else_::class => 'else',
        Stmt\ElseIf_::class => 'elseif',
        Stmt\For_::class => 'for',
        Stmt\Foreach_::class => 'foreach',
        Stmt\While_::class => 'while',
        Stmt\Do_::class => 'do',
        Stmt\Switch_::class => 'switch',
        Stmt\Return_::class => 'return',
        Stmt\Throw_::class => 'throw',
        Stmt\TryCatch::class => 'try',
        Stmt\Catch_::class => 'catch',
        Stmt\Finally_::class => 'finally',
        // Calls and access
        Expr\FuncCall::class => 'call',
        Expr\MethodCall::class => '->',
        Expr\StaticCall::class => '::',
        Expr\New_::class => 'new',
        Expr\PropertyFetch::class => '->prop',
        Expr\StaticPropertyFetch::class => '::prop',
        Expr\ArrayDimFetch::class => '[]',
        Expr\Ternary::class => '?:',
        Expr\Instanceof_::class => 'instanceof',
        Expr\Assign::class => '=',
    ];

    /** @var array<class-string, string> BinaryOp class to symbol mapping */
    private const BINARY_OP_MAP = [
        Expr\BinaryOp\Plus::class => '+',
        Expr\BinaryOp\Minus::class => '-',
        Expr\BinaryOp\Mul::class => '*',
        Expr\BinaryOp\Div::class => '/',
        Expr\BinaryOp\Mod::class => '%',
        Expr\BinaryOp\Pow::class => '**',
        Expr\BinaryOp\Concat::class => '.',
        Expr\BinaryOp\Equal::class => '==',
        Expr\BinaryOp\NotEqual::class => '!=',
        Expr\BinaryOp\Identical::class => '===',
        Expr\BinaryOp\NotIdentical::class => '!==',
        Expr\BinaryOp\Smaller::class => '<',
        Expr\BinaryOp\SmallerOrEqual::class => '<=',
        Expr\BinaryOp\Greater::class => '>',
        Expr\BinaryOp\GreaterOrEqual::class => '>=',
        Expr\BinaryOp\Spaceship::class => '<=>',
        Expr\BinaryOp\BooleanAnd::class => '&&',
        Expr\BinaryOp\BooleanOr::class => '||',
        Expr\BinaryOp\LogicalAnd::class => 'and',
        Expr\BinaryOp\LogicalOr::class => 'or',
        Expr\BinaryOp\LogicalXor::class => 'xor',
        Expr\BinaryOp\BitwiseAnd::class => '&',
        Expr\BinaryOp\BitwiseOr::class => '|',
        Expr\BinaryOp\BitwiseXor::class => '^',
        Expr\BinaryOp\ShiftLeft::class => '<<',
        Expr\BinaryOp\ShiftRight::class => '>>',
        Expr\BinaryOp\Coalesce::class => '??',
    ];

    /** @var array<class-string, string> AssignOp class to symbol mapping */
    private const ASSIGN_OP_MAP = [
        Expr\AssignOp\Plus::class => '+=',
        Expr\AssignOp\Minus::class => '-=',
        Expr\AssignOp\Mul::class => '*=',
        Expr\AssignOp\Div::class => '/=',
        Expr\AssignOp\Mod::class => '%=',
        Expr\AssignOp\Concat::class => '.=',
        Expr\AssignOp\BitwiseAnd::class => '&=',
        Expr\AssignOp\BitwiseOr::class => '|=',
        Expr\AssignOp\BitwiseXor::class => '^=',
        Expr\AssignOp\ShiftLeft::class => '<<=',
        Expr\AssignOp\ShiftRight::class => '>>=',
        Expr\AssignOp\Coalesce::class => '??=',
    ];

    public function beforeTraverse(array $nodes): ?array
    {
        $this->operators = [];
        $this->operands = [];
        $this->totalOperators = 0;
        $this->totalOperands = 0;
        return null;
    }

    public function enterNode(Node $node): ?int
    {
        $this->collectOperators($node);
        $this->collectOperands($node);
        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $this->results = $this->calculateMetrics();
        return null;
    }

    private function collectOperators(Node $node): void
    {
        $nodeClass = $node::class;

        // Check direct operator mapping
        if (isset(self::OPERATOR_MAP[$nodeClass])) {
            $this->addOperator(self::OPERATOR_MAP[$nodeClass]);
            return;
        }

        // Check binary operators
        if ($node instanceof Expr\BinaryOp) {
            $symbol = self::BINARY_OP_MAP[$nodeClass] ?? 'binary_op';
            $this->addOperator($symbol);
            return;
        }

        // Check assignment operators
        if ($node instanceof Expr\AssignOp) {
            $symbol = self::ASSIGN_OP_MAP[$nodeClass] ?? 'assign_op';
            $this->addOperator($symbol);
        }
    }

    private function collectOperands(Node $node): void
    {
        if ($node instanceof Expr\Variable) {
            $name = is_string($node->name) ? '$' . $node->name : '$dynamic';
            $this->addOperand($name);
            return;
        }

        if ($node instanceof Expr\ConstFetch) {
            $this->addOperand($node->name->toString());
            return;
        }

        if ($node instanceof Expr\ClassConstFetch) {
            $className = $node->class instanceof Node\Name ? $node->class->toString() : 'dynamic';
            $constName = $node->name instanceof Node\Identifier ? $node->name->toString() : 'dynamic';
            $this->addOperand($className . '::' . $constName);
            return;
        }

        if ($node instanceof Scalar\String_) {
            $this->addOperand('string:' . substr($node->value, 0, 20));
            return;
        }

        if ($node instanceof Scalar\Int_) {
            $this->addOperand('int:' . $node->value);
            return;
        }

        if ($node instanceof Scalar\Float_) {
            $this->addOperand('float:' . $node->value);
            return;
        }

        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name) {
            $this->addOperand('func:' . $node->name->toString());
            return;
        }

        if ($node instanceof Expr\MethodCall && $node->name instanceof Node\Identifier) {
            $this->addOperand('method:' . $node->name->toString());
        }
    }

    private function addOperator(string $operator): void
    {
        $this->operators[$operator] = ($this->operators[$operator] ?? 0) + 1;
        $this->totalOperators++;
    }

    private function addOperand(string $operand): void
    {
        $this->operands[$operand] = ($this->operands[$operand] ?? 0) + 1;
        $this->totalOperands++;
    }

    private function calculateMetrics(): array
    {
        $n1 = count($this->operators);
        $n2 = count($this->operands);
        $N1 = $this->totalOperators;
        $N2 = $this->totalOperands;

        $vocabulary = $n1 + $n2;
        $length = $N1 + $N2;

        $volume = ($vocabulary > 0 && $length > 0) ? $length * log($vocabulary, 2) : 0;
        $difficulty = ($n2 > 0) ? ($n1 / 2) * ($N2 / $n2) : 0;
        $effort = $difficulty * $volume;
        $time = $effort / 18;
        $bugs = $volume / 3000;

        return [
            'n1' => $n1,
            'n2' => $n2,
            'N1' => $N1,
            'N2' => $N2,
            'vocabulary' => $vocabulary,
            'length' => $length,
            'volume' => round($volume, 2),
            'difficulty' => round($difficulty, 2),
            'effort' => round($effort, 2),
            'time' => round($time, 2),
            'bugs' => round($bugs, 4),
            'operators' => $this->operators,
            'operands' => $this->operands,
        ];
    }

    public function getVolume(): float
    {
        return $this->results['volume'] ?? 0;
    }

    public function getDifficulty(): float
    {
        return $this->results['difficulty'] ?? 0;
    }

    public function getEffort(): float
    {
        return $this->results['effort'] ?? 0;
    }
}
