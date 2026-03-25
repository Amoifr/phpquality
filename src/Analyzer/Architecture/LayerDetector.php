<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\Architecture;

use PhpQuality\Analyzer\ProjectType\ProjectTypeInterface;

/**
 * Automatically detects architectural layers for classes.
 *
 * Uses namespace patterns and class name suffixes to assign layers:
 * - Controller: Presentation layer
 * - Application: Use cases, services, handlers
 * - Domain: Entities, value objects, domain services
 * - Infrastructure: Repositories, adapters, external services
 */
class LayerDetector
{
    public const LAYER_CONTROLLER = 'Controller';
    public const LAYER_APPLICATION = 'Application';
    public const LAYER_DOMAIN = 'Domain';
    public const LAYER_INFRASTRUCTURE = 'Infrastructure';
    public const LAYER_OTHER = 'Other';

    /**
     * Default patterns for layer detection.
     * Each layer has namespace keywords and class suffixes.
     */
    private const DEFAULT_PATTERNS = [
        self::LAYER_CONTROLLER => [
            'namespace' => ['Controller', 'Action', 'Http\\Controller', 'Api\\Controller', 'Web\\Controller'],
            'suffix' => ['Controller', 'Action'],
        ],
        self::LAYER_APPLICATION => [
            'namespace' => ['Application', 'Service', 'UseCase', 'Handler', 'Command', 'Query', 'Bus'],
            'suffix' => ['Service', 'Handler', 'UseCase', 'CommandHandler', 'QueryHandler'],
        ],
        self::LAYER_DOMAIN => [
            'namespace' => ['Domain', 'Entity', 'Model', 'ValueObject', 'Aggregate', 'DomainService'],
            'suffix' => ['Entity', 'ValueObject', 'Aggregate', 'DomainService', 'Specification', 'Policy'],
        ],
        self::LAYER_INFRASTRUCTURE => [
            'namespace' => ['Infrastructure', 'Repository', 'Persistence', 'Adapter', 'Gateway', 'Client'],
            'suffix' => ['Repository', 'Adapter', 'Gateway', 'Client', 'Provider', 'Mapper'],
        ],
    ];

    /**
     * Symfony-specific patterns (overrides defaults)
     */
    private const SYMFONY_PATTERNS = [
        self::LAYER_CONTROLLER => [
            'namespace' => ['Controller'],
            'suffix' => ['Controller'],
        ],
        self::LAYER_APPLICATION => [
            'namespace' => ['Service', 'Handler', 'MessageHandler', 'EventSubscriber', 'EventListener'],
            'suffix' => ['Service', 'Handler', 'Subscriber', 'Listener'],
        ],
        self::LAYER_DOMAIN => [
            'namespace' => ['Entity', 'Model', 'Domain'],
            'suffix' => ['Entity'],
        ],
        self::LAYER_INFRASTRUCTURE => [
            'namespace' => ['Repository', 'Doctrine', 'Messenger', 'Security'],
            'suffix' => ['Repository'],
        ],
    ];

    /**
     * Laravel-specific patterns
     */
    private const LARAVEL_PATTERNS = [
        self::LAYER_CONTROLLER => [
            'namespace' => ['Http\\Controllers'],
            'suffix' => ['Controller'],
        ],
        self::LAYER_APPLICATION => [
            'namespace' => ['Services', 'Actions', 'Jobs', 'Listeners', 'Events'],
            'suffix' => ['Service', 'Action', 'Job', 'Listener'],
        ],
        self::LAYER_DOMAIN => [
            'namespace' => ['Models', 'Entities', 'Domain'],
            'suffix' => ['Model'],
        ],
        self::LAYER_INFRASTRUCTURE => [
            'namespace' => ['Repositories', 'Providers'],
            'suffix' => ['Repository', 'Provider'],
        ],
    ];

    /**
     * Detect layer for a single class
     */
    public function detectLayer(string $fqn, ?ProjectTypeInterface $projectType = null): string
    {
        $patterns = $this->getPatternsForProjectType($projectType);

        // Check each layer's patterns
        foreach ($patterns as $layer => $layerPatterns) {
            if ($this->matchesLayer($fqn, $layerPatterns)) {
                return $layer;
            }
        }

        return self::LAYER_OTHER;
    }

    /**
     * Detect layers for multiple classes
     *
     * @param array<string> $classNames FQN list
     * @return array<string, string> FQN => Layer
     */
    public function detectLayers(array $classNames, ?ProjectTypeInterface $projectType = null): array
    {
        $assignments = [];
        foreach ($classNames as $fqn) {
            $assignments[$fqn] = $this->detectLayer($fqn, $projectType);
        }
        return $assignments;
    }

    /**
     * Get layer statistics
     */
    public function getLayerStats(array $layerAssignments): array
    {
        $stats = [
            self::LAYER_CONTROLLER => 0,
            self::LAYER_APPLICATION => 0,
            self::LAYER_DOMAIN => 0,
            self::LAYER_INFRASTRUCTURE => 0,
            self::LAYER_OTHER => 0,
        ];

        foreach ($layerAssignments as $layer) {
            if (isset($stats[$layer])) {
                $stats[$layer]++;
            } else {
                $stats[self::LAYER_OTHER]++;
            }
        }

        return $stats;
    }

    /**
     * Check if a FQN matches a layer's patterns
     */
    private function matchesLayer(string $fqn, array $patterns): bool
    {
        // Check namespace patterns
        foreach ($patterns['namespace'] ?? [] as $nsPattern) {
            if ($this->namespaceContains($fqn, $nsPattern)) {
                return true;
            }
        }

        // Check suffix patterns
        foreach ($patterns['suffix'] ?? [] as $suffix) {
            if ($this->classNameEndsWith($fqn, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if namespace contains a pattern
     */
    private function namespaceContains(string $fqn, string $pattern): bool
    {
        // Split FQN into parts
        $parts = explode('\\', $fqn);

        // Check if any namespace part matches
        foreach ($parts as $part) {
            if (strcasecmp($part, $pattern) === 0) {
                return true;
            }
        }

        // Check for partial namespace match (e.g., "Http\Controller")
        if (str_contains($pattern, '\\')) {
            return stripos($fqn, $pattern) !== false;
        }

        return false;
    }

    /**
     * Check if class name ends with a suffix
     */
    private function classNameEndsWith(string $fqn, string $suffix): bool
    {
        $parts = explode('\\', $fqn);
        $className = end($parts);
        return str_ends_with($className, $suffix);
    }

    /**
     * Get patterns for a specific project type
     */
    private function getPatternsForProjectType(?ProjectTypeInterface $projectType): array
    {
        if ($projectType === null) {
            return self::DEFAULT_PATTERNS;
        }

        $typeName = $projectType->getName();

        return match ($typeName) {
            'Symfony' => array_merge_recursive(self::DEFAULT_PATTERNS, self::SYMFONY_PATTERNS),
            'Laravel' => array_merge_recursive(self::DEFAULT_PATTERNS, self::LARAVEL_PATTERNS),
            default => self::DEFAULT_PATTERNS,
        };
    }

    /**
     * Get allowed dependencies for a layer
     */
    public function getAllowedDependencies(string $layer): array
    {
        return match ($layer) {
            self::LAYER_CONTROLLER => [self::LAYER_APPLICATION, self::LAYER_DOMAIN, self::LAYER_INFRASTRUCTURE, self::LAYER_OTHER],
            self::LAYER_APPLICATION => [self::LAYER_DOMAIN, self::LAYER_INFRASTRUCTURE, self::LAYER_OTHER],
            self::LAYER_INFRASTRUCTURE => [self::LAYER_DOMAIN, self::LAYER_OTHER],
            self::LAYER_DOMAIN => [self::LAYER_OTHER], // Domain should be pure, only allow Other (external libs)
            default => [self::LAYER_CONTROLLER, self::LAYER_APPLICATION, self::LAYER_DOMAIN, self::LAYER_INFRASTRUCTURE, self::LAYER_OTHER],
        };
    }

    /**
     * Check if a dependency from one layer to another is allowed
     */
    public function isDependencyAllowed(string $fromLayer, string $toLayer): bool
    {
        // Same layer is always allowed
        if ($fromLayer === $toLayer) {
            return true;
        }

        $allowed = $this->getAllowedDependencies($fromLayer);
        return in_array($toLayer, $allowed, true);
    }
}
