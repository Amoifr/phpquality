<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Result;

/**
 * Represents a SOLID principle violation.
 *
 * Detected violations:
 * - SRP: Single Responsibility Principle (God classes)
 * - OCP: Open/Closed Principle (many switch/instanceof)
 * - ISP: Interface Segregation Principle (fat interfaces)
 * - DIP: Dependency Inversion Principle (concrete dependencies)
 */
final class SolidViolation
{
    public const SRP = 'SRP';
    public const OCP = 'OCP';
    public const LSP = 'LSP';
    public const ISP = 'ISP';
    public const DIP = 'DIP';

    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_INFO = 'info';

    public function __construct(
        public readonly string $principle,        // 'SRP', 'OCP', 'LSP', 'ISP', 'DIP'
        public readonly string $className,
        public readonly string $filePath,
        public readonly string $message,
        public readonly string $severity = self::SEVERITY_WARNING,
        public readonly array $details = [],      // Specific metrics that triggered this
    ) {}

    public function getPrincipleName(): string
    {
        return match ($this->principle) {
            self::SRP => 'Single Responsibility Principle',
            self::OCP => 'Open/Closed Principle',
            self::LSP => 'Liskov Substitution Principle',
            self::ISP => 'Interface Segregation Principle',
            self::DIP => 'Dependency Inversion Principle',
            default => $this->principle,
        };
    }

    public function toArray(): array
    {
        return [
            'principle' => $this->principle,
            'principleName' => $this->getPrincipleName(),
            'className' => $this->className,
            'filePath' => $this->filePath,
            'message' => $this->message,
            'severity' => $this->severity,
            'details' => $this->details,
        ];
    }
}
