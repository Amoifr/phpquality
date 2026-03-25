<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Result;

/**
 * Represents a layer dependency violation.
 *
 * A violation occurs when a class in one layer depends on a class
 * in a layer it shouldn't depend on (e.g., Domain depending on Infrastructure).
 */
final class LayerViolation
{
    public function __construct(
        public readonly string $sourceClass,
        public readonly string $sourceLayer,
        public readonly string $targetClass,
        public readonly string $targetLayer,
        public readonly string $dependencyType,   // 'use', 'new', 'extends', 'implements', etc.
        public readonly int $line,
        public readonly string $filePath,
        public readonly string $severity = 'error',  // 'error', 'warning'
    ) {}

    public function getMessage(): string
    {
        return sprintf(
            '%s (%s) should not depend on %s (%s) via %s',
            $this->sourceClass,
            $this->sourceLayer,
            $this->targetClass,
            $this->targetLayer,
            $this->dependencyType
        );
    }

    public function toArray(): array
    {
        return [
            'sourceClass' => $this->sourceClass,
            'sourceLayer' => $this->sourceLayer,
            'targetClass' => $this->targetClass,
            'targetLayer' => $this->targetLayer,
            'dependencyType' => $this->dependencyType,
            'line' => $this->line,
            'filePath' => $this->filePath,
            'severity' => $this->severity,
            'message' => $this->getMessage(),
        ];
    }
}
