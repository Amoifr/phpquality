<?php

declare(strict_types=1);

namespace App\Analyzer\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * Visitor to calculate Lines of Code metrics:
 * - LOC: Total lines of code
 * - CLOC: Comment lines of code
 * - LLOC: Logical lines of code (executable statements)
 */
class LinesOfCodeVisitor extends AbstractMetricVisitor
{
    private int $lloc = 0;
    private array $statementLines = [];
    private string $sourceCode = '';

    /** @var array<class-string> Statement types that count as logical lines */
    private const LOGICAL_STATEMENTS = [
        Stmt\Expression::class,
        Stmt\Return_::class,
        Stmt\If_::class,
        Stmt\For_::class,
        Stmt\Foreach_::class,
        Stmt\While_::class,
        Stmt\Do_::class,
        Stmt\Switch_::class,
        Stmt\Case_::class,
        Stmt\Break_::class,
        Stmt\Continue_::class,
        Stmt\Throw_::class,
        Stmt\TryCatch::class,
        Stmt\Echo_::class,
        Stmt\Unset_::class,
        Stmt\Global_::class,
        Stmt\Static_::class,
    ];

    public function setSourceCode(string $code): void
    {
        $this->sourceCode = $code;
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->lloc = 0;
        $this->statementLines = [];
        return null;
    }

    public function enterNode(Node $node): ?int
    {
        if (in_array($node::class, self::LOGICAL_STATEMENTS, true)) {
            $this->lloc++;
            $line = $node->getStartLine();
            if ($line > 0) {
                $this->statementLines[$line] = true;
            }
        }
        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $this->results = $this->analyzeSourceCode();
        return null;
    }

    private function analyzeSourceCode(): array
    {
        $lines = explode("\n", $this->sourceCode);
        $loc = count($lines);
        $stats = $this->countLineTypes($lines);

        return [
            'loc' => $loc,
            'cloc' => $stats['comments'],
            'lloc' => $this->lloc,
            'blankLines' => $stats['blank'],
            'codeLines' => $loc - $stats['comments'] - $stats['blank'],
            'commentRatio' => $loc > 0 ? round($stats['comments'] / $loc * 100, 2) : 0,
        ];
    }

    private function countLineTypes(array $lines): array
    {
        $comments = 0;
        $blank = 0;
        $inMultilineComment = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $blank++;
                continue;
            }

            if ($inMultilineComment) {
                $comments++;
                $inMultilineComment = !str_contains($trimmed, '*/');
                continue;
            }

            if ($this->isCommentLine($trimmed, $inMultilineComment)) {
                $comments++;
                $inMultilineComment = str_starts_with($trimmed, '/*') && !str_contains($trimmed, '*/');
            }
        }

        return ['comments' => $comments, 'blank' => $blank];
    }

    private function isCommentLine(string $line, bool &$inMultiline): bool
    {
        return str_starts_with($line, '//')
            || str_starts_with($line, '#')
            || str_starts_with($line, '/*')
            || str_starts_with($line, '*')
            || str_starts_with($line, '/**');
    }

    public function getLOC(): int
    {
        return $this->results['loc'] ?? 0;
    }

    public function getCLOC(): int
    {
        return $this->results['cloc'] ?? 0;
    }

    public function getLLOC(): int
    {
        return $this->results['lloc'] ?? 0;
    }
}
