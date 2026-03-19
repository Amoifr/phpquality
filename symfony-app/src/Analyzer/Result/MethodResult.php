<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

class MethodResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly int $ccn,
        public readonly string $ccnRating,
        public readonly float $mi,
        public readonly string $miRating,
        public readonly int $loc,
        public readonly array $halstead = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'class' => $this->className,
            'startLine' => $this->startLine,
            'endLine' => $this->endLine,
            'ccn' => $this->ccn,
            'ccnRating' => $this->ccnRating,
            'mi' => $this->mi,
            'miRating' => $this->miRating,
            'loc' => $this->loc,
            'halstead' => $this->halstead,
        ];
    }
}
