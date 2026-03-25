<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer;

use PhpQuality\Analyzer\Ast\AstParser;
use PhpQuality\Analyzer\Ast\Visitor\LinesOfCodeVisitor;
use PhpQuality\Analyzer\Ast\Visitor\CyclomaticComplexityVisitor;
use PhpQuality\Analyzer\Ast\Visitor\HalsteadVisitor;
use PhpQuality\Analyzer\Ast\Visitor\CohesionVisitor;
use PhpQuality\Analyzer\Ast\Visitor\DependencyVisitor;
use PhpQuality\Analyzer\Metric\MaintainabilityIndex;
use PhpQuality\Analyzer\Result\FileResult;
use PhpQuality\Analyzer\Result\ClassResult;
use PhpQuality\Analyzer\Result\MethodResult;
use PhpQuality\Analyzer\ProjectType\ProjectTypeInterface;

class FileAnalyzer
{
    public function __construct(
        private readonly AstParser $parser,
        private readonly MaintainabilityIndex $miCalculator,
    ) {}

    public function analyze(string $filePath, string $basePath, ?ProjectTypeInterface $projectType = null): FileResult
    {
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $filePath);

        try {
            $code = file_get_contents($filePath);
            if ($code === false) {
                throw new \RuntimeException('Cannot read file');
            }

            $ast = $this->parser->parse($code);

            // Create visitors
            $locVisitor = new LinesOfCodeVisitor();
            $locVisitor->setSourceCode($code);
            $ccnVisitor = new CyclomaticComplexityVisitor();
            $halsteadVisitor = new HalsteadVisitor();
            $cohesionVisitor = new CohesionVisitor();
            $dependencyVisitor = new DependencyVisitor();

            // Traverse AST
            $this->parser->traverse($ast, [
                $locVisitor,
                $ccnVisitor,
                $halsteadVisitor,
                $cohesionVisitor,
                $dependencyVisitor,
            ]);

            // Get results
            $locResults = $locVisitor->getResults();
            $ccnResults = $ccnVisitor->getResults();
            $halsteadResults = $halsteadVisitor->getResults();
            $lcomResults = $cohesionVisitor->getResults();
            $dependencyResults = $dependencyVisitor->getResults();

            // Calculate MI
            $miResult = $this->miCalculator->calculate([
                'halstead' => $halsteadResults,
                'ccn' => $ccnResults,
                'loc' => $locResults,
            ]);

            // Build class results
            $classResults = $this->buildClassResults(
                $ccnResults,
                $lcomResults,
                $halsteadResults,
                $locResults,
                $filePath,
                $projectType
            );

            return new FileResult(
                path: $filePath,
                relativePath: $relativePath,
                classes: $classResults,
                loc: $locResults,
                ccn: $ccnResults,
                halstead: $halsteadResults,
                lcom: $lcomResults,
                mi: $miResult->value,
                miRating: $miResult->rating,
                dependencies: $dependencyResults,
            );
        } catch (\Throwable $e) {
            return new FileResult(
                path: $filePath,
                relativePath: $relativePath,
                classes: [],
                loc: [],
                ccn: [],
                halstead: [],
                lcom: [],
                mi: 0,
                miRating: 'F',
                hasErrors: true,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * @return array<ClassResult>
     */
    private function buildClassResults(
        array $ccnResults,
        array $lcomResults,
        array $halsteadResults,
        array $locResults,
        string $filePath,
        ?ProjectTypeInterface $projectType
    ): array {
        $classes = [];
        $classCategories = $projectType?->getClassCategories() ?? [];

        // Group methods by class
        $methodsByClass = [];
        foreach ($ccnResults['methods'] ?? [] as $method) {
            $className = $method['class'] ?? '__global__';
            $methodsByClass[$className][] = $method;
        }

        // Build class results
        foreach ($lcomResults['classes'] ?? [] as $className => $lcomData) {
            $methods = $methodsByClass[$className] ?? [];
            $ccnValues = array_column($methods, 'ccn');

            $maxCcn = !empty($ccnValues) ? max($ccnValues) : 0;
            $avgCcn = !empty($ccnValues) ? array_sum($ccnValues) / count($ccnValues) : 0;

            // Calculate class MI
            $classVolume = $halsteadResults['volume'] ?? 1;
            $classLloc = $locResults['lloc'] ?? 1;
            $classMi = $this->miCalculator->calculateForUnit($classVolume, $avgCcn, $classLloc);
            $classMiRating = $this->getMiRating($classMi);

            // Determine category
            $category = $this->determineCategory($className, $classCategories);

            // Build method results
            $methodResults = [];
            foreach ($methods as $method) {
                $methodMi = $this->miCalculator->calculateForUnit(
                    $classVolume / max(count($methods), 1),
                    $method['ccn'],
                    max(1, (int)($classLloc / max(count($methods), 1)))
                );

                $methodResults[] = new MethodResult(
                    name: $method['method'],
                    className: $className,
                    startLine: 0, // Would need AST info
                    endLine: 0,
                    ccn: $method['ccn'],
                    ccnRating: $method['rating'],
                    mi: round($methodMi, 2),
                    miRating: $this->getMiRating($methodMi),
                    loc: 0,
                );
            }

            $classes[] = new ClassResult(
                name: $className,
                namespace: '', // Would need AST info
                filePath: $filePath,
                startLine: 0,
                endLine: 0,
                methods: $methodResults,
                lcom: $lcomData['lcom'],
                lcomRating: $lcomData['rating'],
                totalLoc: $locResults['loc'] ?? 0,
                methodCount: $lcomData['methods'],
                propertyCount: $lcomData['attributes'],
                maxCcn: $maxCcn,
                avgCcn: round($avgCcn, 2),
                mi: round($classMi, 2),
                miRating: $classMiRating,
                category: $category,
            );
        }

        return $classes;
    }

    private function determineCategory(string $className, array $patterns): ?string
    {
        foreach ($patterns as $pattern => $category) {
            if (preg_match('/' . $pattern . '/', $className)) {
                return $category;
            }
        }
        return null;
    }

    private function getMiRating(float $mi): string
    {
        return match (true) {
            $mi >= 85 => 'A',
            $mi >= 65 => 'B',
            $mi >= 40 => 'C',
            $mi >= 20 => 'D',
            default => 'F',
        };
    }
}
