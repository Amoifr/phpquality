<?php

declare(strict_types=1);

namespace PhpQuality\Config;

readonly class ThresholdsConfig
{
    public function __construct(
        public ?int $ccn = null,
        public ?float $lcom = null,
        public ?int $mi = null,
    ) {}

    /**
     * Merge configured thresholds with framework defaults.
     * Configured values take priority over framework defaults.
     *
     * @param array{ccn: int, lcom: float, mi: int} $frameworkThresholds
     * @return array{ccn: int, lcom: float, mi: int}
     */
    public function merge(array $frameworkThresholds): array
    {
        return [
            'ccn' => $this->ccn ?? $frameworkThresholds['ccn'],
            'lcom' => $this->lcom ?? $frameworkThresholds['lcom'],
            'mi' => $this->mi ?? $frameworkThresholds['mi'],
        ];
    }

    /**
     * Check if any threshold is configured.
     */
    public function hasOverrides(): bool
    {
        return $this->ccn !== null || $this->lcom !== null || $this->mi !== null;
    }

    /**
     * Create from configuration array.
     *
     * @param array{ccn?: int|null, lcom?: float|null, mi?: int|null} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            ccn: $config['ccn'] ?? null,
            lcom: $config['lcom'] ?? null,
            mi: $config['mi'] ?? null,
        );
    }
}
