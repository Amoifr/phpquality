<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Architecture;

use PhpQuality\Analyzer\Result\FileResult;
use PhpQuality\Analyzer\Result\SolidViolation;

/**
 * Detects SOLID principle violations in code.
 *
 * Principles checked:
 * - SRP: Single Responsibility (God classes)
 * - OCP: Open/Closed (excessive switch/instanceof)
 * - ISP: Interface Segregation (fat interfaces)
 * - DIP: Dependency Inversion (concrete dependencies)
 */
class SolidAnalyzer
{
    // Thresholds for SRP detection
    private const SRP_MAX_METHODS = 20;
    private const SRP_MAX_LCOM = 0.7;
    private const SRP_MAX_DEPENDENCIES = 15;
    private const SRP_MAX_LOC = 500;

    // Thresholds for ISP detection
    private const ISP_MAX_INTERFACE_METHODS = 5;

    // Thresholds for DIP detection
    private const DIP_MIN_ABSTRACTION_RATIO = 0.5;

    /**
     * Analyze files for SOLID violations
     *
     * @param array<FileResult> $fileResults
     * @return array<SolidViolation>
     */
    public function analyze(array $fileResults): array
    {
        $violations = [];

        foreach ($fileResults as $fileResult) {
            if ($fileResult->hasErrors) {
                continue;
            }

            // Check classes for SRP, DIP
            foreach ($fileResult->classes as $class) {
                $violations = array_merge(
                    $violations,
                    $this->detectSrpViolations($class, $fileResult)
                );

                $violations = array_merge(
                    $violations,
                    $this->detectDipViolations($class, $fileResult)
                );
            }

            // Check interfaces for ISP
            $violations = array_merge(
                $violations,
                $this->detectIspViolations($fileResult)
            );
        }

        return $violations;
    }

    /**
     * Detect Single Responsibility Principle violations (God classes)
     *
     * A class likely violates SRP if:
     * - High LCOM (methods don't share properties)
     * - Many methods
     * - Many dependencies
     * - Large size (LOC)
     */
    private function detectSrpViolations($class, FileResult $fileResult): array
    {
        $violations = [];
        $score = 0;
        $details = [];

        // Check LCOM
        if ($class->lcom > self::SRP_MAX_LCOM) {
            $score += 30;
            $details['lcom'] = round($class->lcom, 2);
        }

        // Check method count
        if ($class->methodCount > self::SRP_MAX_METHODS) {
            $score += 25;
            $details['methodCount'] = $class->methodCount;
        }

        // Check LOC
        if ($class->totalLoc > self::SRP_MAX_LOC) {
            $score += 20;
            $details['loc'] = $class->totalLoc;
        }

        // Check dependency count
        $depCount = $this->getClassDependencyCount($class, $fileResult);
        if ($depCount > self::SRP_MAX_DEPENDENCIES) {
            $score += 25;
            $details['dependencyCount'] = $depCount;
        }

        // If score is high enough, it's a violation
        if ($score >= 50) {
            $severity = $score >= 75 ? SolidViolation::SEVERITY_ERROR : SolidViolation::SEVERITY_WARNING;
            $message = $this->buildSrpMessage($class, $details);

            $violations[] = new SolidViolation(
                principle: SolidViolation::SRP,
                className: $class->getFullyQualifiedName(),
                filePath: $class->filePath,
                message: $message,
                severity: $severity,
                details: array_merge($details, ['score' => $score])
            );
        }

        return $violations;
    }

    /**
     * Detect Interface Segregation Principle violations (fat interfaces)
     */
    private function detectIspViolations(FileResult $fileResult): array
    {
        $violations = [];
        $interfaces = $fileResult->dependencies['interfaceDefinitions'] ?? [];

        foreach ($interfaces as $name => $info) {
            $methodCount = $info['methods'] ?? 0;

            if ($methodCount > self::ISP_MAX_INTERFACE_METHODS) {
                $violations[] = new SolidViolation(
                    principle: SolidViolation::ISP,
                    className: $info['fqn'] ?? $name,
                    filePath: $fileResult->path,
                    message: sprintf(
                        'Interface %s has %d methods (max recommended: %d). Consider splitting into smaller, more focused interfaces.',
                        $name,
                        $methodCount,
                        self::ISP_MAX_INTERFACE_METHODS
                    ),
                    severity: $methodCount > 10 ? SolidViolation::SEVERITY_ERROR : SolidViolation::SEVERITY_WARNING,
                    details: ['methodCount' => $methodCount]
                );
            }
        }

        return $violations;
    }

    /**
     * Detect Dependency Inversion Principle violations
     *
     * Checks the ratio of abstract vs concrete dependencies.
     * High-level modules should depend on abstractions.
     */
    private function detectDipViolations($class, FileResult $fileResult): array
    {
        $violations = [];
        $dependencies = $fileResult->dependencies['dependencies'] ?? [];

        $abstractCount = 0;
        $concreteCount = 0;

        foreach ($dependencies as $dep) {
            $context = $dep['context'] ?? '';

            // Only check dependencies from this class
            if ($context !== $class->name) {
                continue;
            }

            $fqn = $dep['fqn'] ?? '';
            $type = $dep['type'] ?? '';

            // Skip use statements, only check actual usage
            if ($type === 'use') {
                continue;
            }

            // Check if dependency is likely an interface/abstract
            if ($this->isLikelyAbstraction($fqn)) {
                $abstractCount++;
            } else {
                $concreteCount++;
            }
        }

        $total = $abstractCount + $concreteCount;
        if ($total >= 5) { // Only check if there are enough dependencies
            $ratio = $total > 0 ? $abstractCount / $total : 0;

            if ($ratio < self::DIP_MIN_ABSTRACTION_RATIO) {
                $violations[] = new SolidViolation(
                    principle: SolidViolation::DIP,
                    className: $class->getFullyQualifiedName(),
                    filePath: $class->filePath,
                    message: sprintf(
                        'Class %s has low abstraction ratio (%.0f%%). %d of %d dependencies are to concrete classes. Consider depending on interfaces instead.',
                        $class->name,
                        $ratio * 100,
                        $concreteCount,
                        $total
                    ),
                    severity: $ratio < 0.3 ? SolidViolation::SEVERITY_ERROR : SolidViolation::SEVERITY_WARNING,
                    details: [
                        'abstractCount' => $abstractCount,
                        'concreteCount' => $concreteCount,
                        'ratio' => round($ratio, 2),
                    ]
                );
            }
        }

        return $violations;
    }

    /**
     * Get the number of unique dependencies for a class
     */
    private function getClassDependencyCount($class, FileResult $fileResult): int
    {
        $dependencies = $fileResult->dependencies['dependencies'] ?? [];
        $uniqueDeps = [];

        foreach ($dependencies as $dep) {
            $context = $dep['context'] ?? '';
            if ($context === $class->name) {
                $uniqueDeps[$dep['fqn']] = true;
            }
        }

        return count($uniqueDeps);
    }

    /**
     * Check if a class name is likely an abstraction (interface or abstract class)
     */
    private function isLikelyAbstraction(string $fqn): bool
    {
        $parts = explode('\\', $fqn);
        $className = end($parts);

        // Common patterns for interfaces
        if (str_ends_with($className, 'Interface')) {
            return true;
        }
        if (str_starts_with($className, 'I') && ctype_upper($className[1] ?? '')) {
            return true; // IUserRepository pattern
        }

        // Common patterns for abstract classes
        if (str_starts_with($className, 'Abstract')) {
            return true;
        }

        // Check namespace for interface indicators
        if (str_contains($fqn, '\\Contract\\') || str_contains($fqn, '\\Contracts\\')) {
            return true;
        }

        // Symfony/PSR interfaces
        $knownInterfaces = [
            'Interface', 'Aware', 'Handler', 'Factory', 'Repository',
            'Service', 'Provider', 'Manager', 'Builder', 'Processor'
        ];

        foreach ($knownInterfaces as $suffix) {
            if (str_ends_with($className, $suffix . 'Interface')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a descriptive message for SRP violation
     */
    private function buildSrpMessage($class, array $details): string
    {
        $reasons = [];

        if (isset($details['lcom'])) {
            $reasons[] = sprintf('low cohesion (LCOM: %.2f)', $details['lcom']);
        }
        if (isset($details['methodCount'])) {
            $reasons[] = sprintf('%d methods', $details['methodCount']);
        }
        if (isset($details['loc'])) {
            $reasons[] = sprintf('%d lines', $details['loc']);
        }
        if (isset($details['dependencyCount'])) {
            $reasons[] = sprintf('%d dependencies', $details['dependencyCount']);
        }

        return sprintf(
            'Class %s may have too many responsibilities: %s. Consider splitting into smaller, focused classes.',
            $class->name,
            implode(', ', $reasons)
        );
    }
}
