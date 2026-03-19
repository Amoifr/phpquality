<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

class FileResult
{
    /**
     * @param array<ClassResult> $classes
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
        ];
    }
}
