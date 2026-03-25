<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Ast\Visitor;

use PhpParser\NodeVisitorAbstract;

abstract class AbstractMetricVisitor extends NodeVisitorAbstract
{
    protected array $results = [];

    /**
     * Get collected results
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Reset the visitor state for a new analysis
     */
    public function reset(): void
    {
        $this->results = [];
    }
}
