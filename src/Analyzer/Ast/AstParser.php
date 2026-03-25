<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Ast;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\Error;

class AstParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Parse PHP code into an AST
     *
     * @return array<\PhpParser\Node\Stmt>
     * @throws \RuntimeException
     */
    public function parse(string $code): array
    {
        try {
            $ast = $this->parser->parse($code);
            return $ast ?? [];
        } catch (Error $e) {
            throw new \RuntimeException('Parse error: ' . $e->getMessage());
        }
    }

    /**
     * Parse a file into an AST
     *
     * @return array<\PhpParser\Node\Stmt>
     */
    public function parseFile(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException('Cannot read file: ' . $filePath);
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new \RuntimeException('Failed to read file: ' . $filePath);
        }

        return $this->parse($code);
    }

    /**
     * Traverse the AST with the given visitors
     *
     * @param array<\PhpParser\Node\Stmt> $ast
     * @param array<NodeVisitor> $visitors
     */
    public function traverse(array $ast, array $visitors): void
    {
        $traverser = new NodeTraverser();
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }
        $traverser->traverse($ast);
    }
}
