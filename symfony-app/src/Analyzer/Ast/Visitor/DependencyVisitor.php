<?php

declare(strict_types=1);

namespace App\Analyzer\Ast\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\Node\IntersectionType;

/**
 * Visitor to extract dependency information from PHP files.
 *
 * Tracks:
 * - use statements (imports)
 * - Class instantiations (new ClassName)
 * - Static calls (ClassName::method())
 * - Type hints (parameters, return types, properties)
 * - extends and implements clauses
 * - Trait usage
 */
class DependencyVisitor extends AbstractMetricVisitor
{
    private ?string $currentNamespace = null;
    private ?string $currentClass = null;

    /** @var array<string, string> Alias => FQN */
    private array $useStatements = [];

    /** @var array<string, array{type: string, line: int, context: string}> */
    private array $dependencies = [];

    /** @var array<string, array{line: int, extends: ?string, implements: array, traits: array}> */
    private array $classDefinitions = [];

    /** @var array<string, array{line: int, extends: array, methods: int}> */
    private array $interfaceDefinitions = [];

    /** @var array<string, int> */
    private array $traitDefinitions = [];

    public function beforeTraverse(array $nodes): ?array
    {
        $this->currentNamespace = null;
        $this->currentClass = null;
        $this->useStatements = [];
        $this->dependencies = [];
        $this->classDefinitions = [];
        $this->interfaceDefinitions = [];
        $this->traitDefinitions = [];
        return null;
    }

    public function enterNode(Node $node): ?int
    {
        // Track namespace
        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = $node->name?->toString();
        }

        // Track use statements
        if ($node instanceof Stmt\Use_) {
            foreach ($node->uses as $use) {
                $fqn = $use->name->toString();
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $this->useStatements[$alias] = $fqn;
                $this->addDependency($fqn, 'use', $node->getStartLine());
            }
        }

        // Track grouped use statements
        if ($node instanceof Stmt\GroupUse) {
            $prefix = $node->prefix->toString();
            foreach ($node->uses as $use) {
                $fqn = $prefix . '\\' . $use->name->toString();
                $alias = $use->alias?->toString() ?? $use->name->getLast();
                $this->useStatements[$alias] = $fqn;
                $this->addDependency($fqn, 'use', $node->getStartLine());
            }
        }

        // Track class definitions
        if ($node instanceof Stmt\Class_) {
            $className = $node->name?->toString() ?? 'Anonymous';
            $this->currentClass = $className;

            $extends = null;
            if ($node->extends) {
                $extends = $this->resolveClassName($node->extends);
                $this->addDependency($extends, 'extends', $node->getStartLine(), $className);
            }

            $implements = [];
            foreach ($node->implements as $interface) {
                $interfaceName = $this->resolveClassName($interface);
                $implements[] = $interfaceName;
                $this->addDependency($interfaceName, 'implements', $node->getStartLine(), $className);
            }

            $this->classDefinitions[$className] = [
                'line' => $node->getStartLine(),
                'extends' => $extends,
                'implements' => $implements,
                'traits' => [],
                'fqn' => $this->currentNamespace ? $this->currentNamespace . '\\' . $className : $className,
            ];
        }

        // Track interface definitions
        if ($node instanceof Stmt\Interface_) {
            $interfaceName = $node->name->toString();

            $extends = [];
            foreach ($node->extends as $parent) {
                $parentName = $this->resolveClassName($parent);
                $extends[] = $parentName;
                $this->addDependency($parentName, 'extends', $node->getStartLine(), $interfaceName);
            }

            $this->interfaceDefinitions[$interfaceName] = [
                'line' => $node->getStartLine(),
                'extends' => $extends,
                'methods' => count($node->getMethods()),
                'fqn' => $this->currentNamespace ? $this->currentNamespace . '\\' . $interfaceName : $interfaceName,
            ];
        }

        // Track trait definitions
        if ($node instanceof Stmt\Trait_) {
            $traitName = $node->name->toString();
            $this->traitDefinitions[$traitName] = $node->getStartLine();
        }

        // Track trait usage in classes
        if ($node instanceof Stmt\TraitUse && $this->currentClass !== null) {
            foreach ($node->traits as $trait) {
                $traitName = $this->resolveClassName($trait);
                $this->classDefinitions[$this->currentClass]['traits'][] = $traitName;
                $this->addDependency($traitName, 'trait', $node->getStartLine(), $this->currentClass);
            }
        }

        // Track class instantiations (new ClassName)
        if ($node instanceof Expr\New_) {
            if ($node->class instanceof Name) {
                $className = $this->resolveClassName($node->class);
                if ($className !== 'self' && $className !== 'static' && $className !== 'parent') {
                    $this->addDependency($className, 'new', $node->getStartLine(), $this->currentClass);
                }
            }
        }

        // Track static calls (ClassName::method())
        if ($node instanceof Expr\StaticCall) {
            if ($node->class instanceof Name) {
                $className = $this->resolveClassName($node->class);
                if ($className !== 'self' && $className !== 'static' && $className !== 'parent') {
                    $this->addDependency($className, 'static_call', $node->getStartLine(), $this->currentClass);
                }
            }
        }

        // Track static property access
        if ($node instanceof Expr\StaticPropertyFetch) {
            if ($node->class instanceof Name) {
                $className = $this->resolveClassName($node->class);
                if ($className !== 'self' && $className !== 'static' && $className !== 'parent') {
                    $this->addDependency($className, 'static_property', $node->getStartLine(), $this->currentClass);
                }
            }
        }

        // Track class constant access
        if ($node instanceof Expr\ClassConstFetch) {
            if ($node->class instanceof Name) {
                $className = $this->resolveClassName($node->class);
                if ($className !== 'self' && $className !== 'static' && $className !== 'parent') {
                    $this->addDependency($className, 'const', $node->getStartLine(), $this->currentClass);
                }
            }
        }

        // Track type hints in method parameters
        if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_) {
            foreach ($node->params as $param) {
                $this->extractTypeHints($param->type, $node->getStartLine(), 'param');
            }
            // Return type
            $this->extractTypeHints($node->returnType, $node->getStartLine(), 'return');
        }

        // Track property types
        if ($node instanceof Stmt\Property) {
            $this->extractTypeHints($node->type, $node->getStartLine(), 'property');
        }

        // Track instanceof checks
        if ($node instanceof Expr\Instanceof_) {
            if ($node->class instanceof Name) {
                $className = $this->resolveClassName($node->class);
                $this->addDependency($className, 'instanceof', $node->getStartLine(), $this->currentClass);
            }
        }

        // Track catch blocks (exception types)
        if ($node instanceof Stmt\Catch_) {
            foreach ($node->types as $type) {
                $className = $this->resolveClassName($type);
                $this->addDependency($className, 'catch', $node->getStartLine(), $this->currentClass);
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Interface_ || $node instanceof Stmt\Trait_) {
            $this->currentClass = null;
        }
        return null;
    }

    public function afterTraverse(array $nodes): ?array
    {
        $this->results = [
            'namespace' => $this->currentNamespace,
            'useStatements' => $this->useStatements,
            'dependencies' => $this->dependencies,
            'classDefinitions' => $this->classDefinitions,
            'interfaceDefinitions' => $this->interfaceDefinitions,
            'traitDefinitions' => $this->traitDefinitions,
        ];
        return null;
    }

    /**
     * Resolve a class name to its fully qualified name
     */
    private function resolveClassName(Name $name): string
    {
        $nameStr = $name->toString();

        // Already fully qualified
        if ($name->isFullyQualified()) {
            return ltrim($nameStr, '\\');
        }

        // Check if it's an alias from use statements
        $parts = explode('\\', $nameStr);
        $firstPart = $parts[0];

        if (isset($this->useStatements[$firstPart])) {
            if (count($parts) === 1) {
                return $this->useStatements[$firstPart];
            }
            // Partial use (use App\Entity; then Entity\User)
            array_shift($parts);
            return $this->useStatements[$firstPart] . '\\' . implode('\\', $parts);
        }

        // Same namespace
        if ($this->currentNamespace) {
            return $this->currentNamespace . '\\' . $nameStr;
        }

        return $nameStr;
    }

    /**
     * Extract type hints from a type node (handles nullable, union, intersection types)
     */
    private function extractTypeHints(?Node $type, int $line, string $context): void
    {
        if ($type === null) {
            return;
        }

        if ($type instanceof Name) {
            $className = $this->resolveClassName($type);
            if (!$this->isBuiltinType($className)) {
                $this->addDependency($className, 'type_hint', $line, $this->currentClass);
            }
        } elseif ($type instanceof NullableType) {
            $this->extractTypeHints($type->type, $line, $context);
        } elseif ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $subType) {
                $this->extractTypeHints($subType, $line, $context);
            }
        } elseif ($type instanceof Identifier) {
            // Built-in type like int, string, bool - ignore
        }
    }

    /**
     * Check if a type name is a PHP built-in type
     */
    private function isBuiltinType(string $type): bool
    {
        $builtins = [
            'int', 'integer', 'float', 'double', 'string', 'bool', 'boolean',
            'array', 'object', 'callable', 'iterable', 'void', 'null', 'mixed',
            'never', 'true', 'false', 'self', 'static', 'parent',
        ];
        return in_array(strtolower($type), $builtins, true);
    }

    /**
     * Add a dependency to the tracking list
     */
    private function addDependency(string $fqn, string $type, int $line, ?string $context = null): void
    {
        // Skip vendor classes for now (can be configured later)
        // For now, track everything

        $key = $fqn . ':' . $type . ':' . $line;
        if (!isset($this->dependencies[$key])) {
            $this->dependencies[$key] = [
                'fqn' => $fqn,
                'type' => $type,
                'line' => $line,
                'context' => $context ?? $this->currentClass ?? 'file',
            ];
        }
    }

    /**
     * Get dependencies grouped by FQN
     */
    public function getDependenciesByClass(): array
    {
        $grouped = [];
        foreach ($this->dependencies as $dep) {
            $fqn = $dep['fqn'];
            if (!isset($grouped[$fqn])) {
                $grouped[$fqn] = [
                    'fqn' => $fqn,
                    'types' => [],
                    'count' => 0,
                ];
            }
            $grouped[$fqn]['types'][] = $dep['type'];
            $grouped[$fqn]['count']++;
        }
        return $grouped;
    }

    /**
     * Get unique dependency count (for metrics)
     */
    public function getUniqueDependencyCount(): int
    {
        $unique = [];
        foreach ($this->dependencies as $dep) {
            $unique[$dep['fqn']] = true;
        }
        return count($unique);
    }
}
