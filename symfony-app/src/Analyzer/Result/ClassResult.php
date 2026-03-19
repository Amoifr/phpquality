<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

class ClassResult
{
    /**
     * @param array<MethodResult> $methods
     */
    public function __construct(
        public readonly string $name,
        public readonly string $namespace,
        public readonly string $filePath,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly array $methods,
        public readonly float $lcom,
        public readonly string $lcomRating,
        public readonly int $totalLoc,
        public readonly int $methodCount,
        public readonly int $propertyCount,
        public readonly int $maxCcn,
        public readonly float $avgCcn,
        public readonly float $mi,
        public readonly string $miRating,
        public readonly ?string $category = null,
    ) {}

    public function getFullyQualifiedName(): string
    {
        return $this->namespace ? $this->namespace . '\\' . $this->name : $this->name;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'namespace' => $this->namespace,
            'fqn' => $this->getFullyQualifiedName(),
            'filePath' => $this->filePath,
            'startLine' => $this->startLine,
            'endLine' => $this->endLine,
            'methods' => array_map(fn($m) => $m->toArray(), $this->methods),
            'lcom' => $this->lcom,
            'lcomRating' => $this->lcomRating,
            'totalLoc' => $this->totalLoc,
            'methodCount' => $this->methodCount,
            'propertyCount' => $this->propertyCount,
            'maxCcn' => $this->maxCcn,
            'avgCcn' => $this->avgCcn,
            'mi' => $this->mi,
            'miRating' => $this->miRating,
            'category' => $this->category,
        ];
    }
}
