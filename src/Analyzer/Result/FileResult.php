<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Result;

class FileResult
{
    /**
     * @param array<ClassResult> $classes
     */
    /**
     * @param array<ClassResult> $classes
     * @param array $dependencies Dependency analysis results from DependencyVisitor
     */
    public function __construct(
        public readonly string $path,
        public readonly string $relativePath,
        public readonly array $classes,
        public readonly array $loc,
        public readonly array $ccn,
        public readonly array $halstead,
        public readonly array $lcom,
        public readonly float $mi,
        public readonly string $miRating,
        public readonly bool $hasErrors = false,
        public readonly ?string $error = null,
        public readonly array $dependencies = [],
    ) {}

    public function getTotalLoc(): int
    {
        return $this->loc['loc'] ?? 0;
    }

    public function getLogicalLoc(): int
    {
        return $this->loc['lloc'] ?? 0;
    }

    public function getCommentLines(): int
    {
        return $this->loc['cloc'] ?? 0;
    }

    public function getMaxCcn(): int
    {
        return $this->ccn['summary']['maxCcn'] ?? 0;
    }

    public function getAvgCcn(): float
    {
        return $this->ccn['summary']['averageCcn'] ?? 0;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'relativePath' => $this->relativePath,
            'classes' => array_map(fn($c) => $c->toArray(), $this->classes),
            'loc' => $this->loc,
            'ccn' => $this->ccn,
            'halstead' => $this->halstead,
            'lcom' => $this->lcom,
            'mi' => $this->mi,
            'miRating' => $this->miRating,
            'hasErrors' => $this->hasErrors,
            'error' => $this->error,
            'dependencies' => $this->dependencies,
        ];
    }

    /**
     * Get the namespace of this file (from dependency analysis)
     */
    public function getNamespace(): ?string
    {
        return $this->dependencies['namespace'] ?? null;
    }

    /**
     * Get the use statements in this file
     */
    public function getUseStatements(): array
    {
        return $this->dependencies['useStatements'] ?? [];
    }

    /**
     * Get all dependencies found in this file
     */
    public function getDependencies(): array
    {
        return $this->dependencies['dependencies'] ?? [];
    }

    /**
     * Get unique dependency count
     */
    public function getUniqueDependencyCount(): int
    {
        $deps = $this->getDependencies();
        $unique = [];
        foreach ($deps as $dep) {
            $unique[$dep['fqn']] = true;
        }
        return count($unique);
    }
}
