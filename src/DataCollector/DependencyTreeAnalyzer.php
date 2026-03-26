<?php

declare(strict_types=1);

namespace PhpQuality\DataCollector;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class DependencyTreeAnalyzer
{
    private \PhpParser\Parser $parser;

    /** @var array<string, true> Already analyzed files to avoid cycles */
    private array $analyzedFiles = [];

    /** @var array<string> Files in dependency order */
    private array $dependencyTree = [];

    public function __construct(
        private readonly string $projectDir,
        private readonly array $excludePaths = [],
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Analyze a file and its dependencies recursively
     * @return array<string> Files in dependency order (root first)
     */
    public function analyze(string $rootFile): array
    {
        $this->analyzedFiles = [];
        $this->dependencyTree = [];

        $this->analyzeFile($rootFile);

        return $this->dependencyTree;
    }

    private function analyzeFile(string $filePath): void
    {
        // Skip if already analyzed
        if (isset($this->analyzedFiles[$filePath])) {
            return;
        }

        // Skip if excluded
        if ($this->isExcluded($filePath)) {
            return;
        }

        // Skip if not in project
        if (!str_starts_with($filePath, $this->projectDir)) {
            return;
        }

        $this->analyzedFiles[$filePath] = true;
        $this->dependencyTree[] = $filePath;

        // Parse file and extract dependencies
        $dependencies = $this->extractDependencies($filePath);

        // Recursively analyze dependencies
        foreach ($dependencies as $depFile) {
            $this->analyzeFile($depFile);
        }
    }

    /**
     * @return array<string> File paths of dependencies
     */
    private function extractDependencies(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return [];
        }

        try {
            $ast = $this->parser->parse($code);
            if ($ast === null) {
                return [];
            }

            $visitor = new class extends NodeVisitorAbstract {
                /** @var array<string> */
                public array $uses = [];

                public function enterNode(Node $node): ?int
                {
                    if ($node instanceof Node\Stmt\Use_) {
                        foreach ($node->uses as $use) {
                            $this->uses[] = $use->name->toString();
                        }
                    }
                    return null;
                }
            };

            $traverser = new NodeTraverser();
            $traverser->addVisitor($visitor);
            $traverser->traverse($ast);

            // Resolve class names to file paths
            return $this->resolveClassesToFiles($visitor->uses);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string> $classNames
     * @return array<string>
     */
    private function resolveClassesToFiles(array $classNames): array
    {
        $files = [];

        foreach ($classNames as $className) {
            $file = $this->resolveClassToFile($className);
            if ($file !== null && !isset($this->analyzedFiles[$file])) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function resolveClassToFile(string $className): ?string
    {
        // Try PSR-4 resolution: App\ namespace -> src/
        if (str_starts_with($className, 'App\\')) {
            $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($className, 4));
            $filePath = $this->projectDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath . '.php';

            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        // Try common namespaces
        $prefixes = [
            'App\\' => 'src/',
            'App\\Tests\\' => 'tests/',
        ];

        foreach ($prefixes as $prefix => $dir) {
            if (str_starts_with($className, $prefix)) {
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, substr($className, strlen($prefix)));
                $filePath = $this->projectDir . DIRECTORY_SEPARATOR . $dir . $relativePath . '.php';

                if (file_exists($filePath)) {
                    return $filePath;
                }
            }
        }

        return null;
    }

    private function isExcluded(string $filePath): bool
    {
        $relativePath = substr($filePath, strlen(rtrim($this->projectDir, DIRECTORY_SEPARATOR)) + 1);

        foreach ($this->excludePaths as $excludePath) {
            $excludePath = trim($excludePath, '/');
            if (str_starts_with($relativePath, $excludePath . DIRECTORY_SEPARATOR) ||
                str_starts_with($relativePath, $excludePath)) {
                return true;
            }
        }

        return false;
    }
}
