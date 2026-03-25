<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Metric;

class MetricResult
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
        public readonly string $unit = '',
        public readonly array $details = [],
        public readonly ?string $rating = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'unit' => $this->unit,
            'details' => $this->details,
            'rating' => $this->rating,
        ];
    }

    public function isGood(): bool
    {
        return in_array($this->rating, ['A', 'B'], true);
    }

    public function isWarning(): bool
    {
        return $this->rating === 'C';
    }

    public function isBad(): bool
    {
        return in_array($this->rating, ['D', 'F'], true);
    }

    public function getRatingColor(): string
    {
        return match ($this->rating) {
            'A' => '#22c55e', // green
            'B' => '#84cc16', // lime
            'C' => '#eab308', // yellow
            'D' => '#f97316', // orange
            'F' => '#ef4444', // red
            default => '#94a3b8', // gray
        };
    }
}
