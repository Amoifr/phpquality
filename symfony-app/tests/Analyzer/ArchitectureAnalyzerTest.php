<?php

declare(strict_types=1);

namespace App\Tests\Analyzer;

use App\Analyzer\Architecture\LayerDetector;
use App\Analyzer\Architecture\SolidAnalyzer;
use App\Analyzer\ArchitectureAnalyzer;
use App\Analyzer\Result\ArchitectureResult;
use App\Analyzer\Result\FileResult;
use PHPUnit\Framework\TestCase;

class ArchitectureAnalyzerTest extends TestCase
{
    private ArchitectureAnalyzer $analyzer;

    protected function setUp(): void
    {
        $layerDetector = new LayerDetector();
        $solidAnalyzer = new SolidAnalyzer();
        $this->analyzer = new ArchitectureAnalyzer($layerDetector, $solidAnalyzer);
    }

    public function testAnalyzeEmptyFileResults(): void
    {
        $result = $this->analyzer->analyze([]);

        $this->assertInstanceOf(ArchitectureResult::class, $result);
        $this->assertEmpty($result->dependencyGraph['nodes']);
        $this->assertEmpty($result->layerViolations);
        $this->assertSame(100.0, $result->score);
        $this->assertSame('A', $result->rating);
    }

    public function testAnalyzeBuildsDependencyGraph(): void
    {
        $fileResult = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Controller',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertArrayHasKey('nodes', $result->dependencyGraph);
        $this->assertArrayHasKey('App\Controller\UserController', $result->dependencyGraph['nodes']);
    }

    public function testAnalyzeAssignsLayers(): void
    {
        $fileResult = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Controller',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertArrayHasKey('App\Controller\UserController', $result->layerAssignments);
        $this->assertSame('Controller', $result->layerAssignments['App\Controller\UserController']);
    }

    public function testAnalyzeDetectsLayerViolations(): void
    {
        // Domain class
        $domainFile = $this->createFileResult([
            'classDefinitions' => [
                'User' => [
                    'fqn' => 'App\Domain\User',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Controller\UserController',
                    'type' => 'new',
                    'line' => 10,
                    'context' => 'User',
                ],
            ],
            'namespace' => 'App\Domain',
        ], '/Domain/User.php');

        // Controller class (so it's in the graph and has a layer)
        $controllerFile = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Controller',
        ], '/Controller/UserController.php');

        $result = $this->analyzer->analyze([$domainFile, $controllerFile]);

        // Domain depending on Controller is a violation
        $this->assertNotEmpty($result->layerViolations);
    }

    public function testAnalyzeCalculatesLayerStats(): void
    {
        $fileResult1 = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Controller',
        ]);

        $fileResult2 = $this->createFileResult([
            'classDefinitions' => [
                'User' => [
                    'fqn' => 'App\Domain\User',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Domain',
        ], '/User.php');

        $result = $this->analyzer->analyze([$fileResult1, $fileResult2]);

        $this->assertIsArray($result->layerStats);
    }

    public function testAnalyzeSkipsFilesWithErrors(): void
    {
        $fileResult = new FileResult(
            path: '/test.php',
            relativePath: 'test.php',
            classes: [],
            loc: [],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 0,
            miRating: 'F',
            dependencies: [],
            hasErrors: true,
            error: 'Parse error',
        );

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertEmpty($result->dependencyGraph['nodes']);
    }

    public function testAnalyzeHandlesInterfaceDefinitions(): void
    {
        $fileResult = $this->createFileResult([
            'interfaceDefinitions' => [
                'UserRepositoryInterface' => [
                    'fqn' => 'App\Repository\UserRepositoryInterface',
                    'methods' => 3,
                ],
            ],
            'classDefinitions' => [],
            'dependencies' => [],
            'namespace' => 'App\Repository',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertArrayHasKey('App\Repository\UserRepositoryInterface', $result->dependencyGraph['nodes']);
        $this->assertSame('interface', $result->dependencyGraph['nodes']['App\Repository\UserRepositoryInterface']['type']);
    }

    public function testAnalyzeBuildsEdges(): void
    {
        $fileResult = $this->createFileResult([
            'classDefinitions' => [
                'UserService' => [
                    'fqn' => 'App\Service\UserService',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Repository\UserRepository',
                    'type' => 'type_hint',
                    'line' => 10,
                    'context' => 'UserService',
                ],
            ],
            'namespace' => 'App\Service',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertNotEmpty($result->dependencyGraph['edges']);
        $edge = $result->dependencyGraph['edges'][0];
        $this->assertSame('App\Service\UserService', $edge['from']);
        $this->assertSame('App\Repository\UserRepository', $edge['to']);
    }

    public function testAnalyzeCalculatesScore(): void
    {
        $fileResult = $this->createFileResult([
            'classDefinitions' => [
                'UserService' => [
                    'fqn' => 'App\Service\UserService',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Service',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertIsFloat($result->score);
        $this->assertGreaterThanOrEqual(0, $result->score);
        $this->assertLessThanOrEqual(100, $result->score);
        $this->assertContains($result->rating, ['A', 'B', 'C', 'D', 'F']);
    }

    public function testAnalyzeWithMultipleClasses(): void
    {
        $fileResult = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
                'ProductController' => [
                    'fqn' => 'App\Controller\ProductController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Controller',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        $this->assertCount(2, $result->dependencyGraph['nodes']);
    }

    public function testAnalyzeDetectsCircularDependencies(): void
    {
        // ClassA depends on ClassB, ClassB depends on ClassA
        $fileResult1 = $this->createFileResult([
            'classDefinitions' => [
                'ClassA' => [
                    'fqn' => 'App\Service\ClassA',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Service\ClassB',
                    'type' => 'new',
                    'line' => 10,
                    'context' => 'ClassA',
                ],
            ],
            'namespace' => 'App\Service',
        ]);

        $fileResult2 = $this->createFileResult([
            'classDefinitions' => [
                'ClassB' => [
                    'fqn' => 'App\Service\ClassB',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Service\ClassA',
                    'type' => 'new',
                    'line' => 10,
                    'context' => 'ClassB',
                ],
            ],
            'namespace' => 'App\Service',
        ], '/ClassB.php');

        $result = $this->analyzer->analyze([$fileResult1, $fileResult2]);

        $this->assertNotEmpty($result->circularDependencies);
    }

    public function testScoreDecreasesWithViolations(): void
    {
        // Domain class depending on Controller (violation)
        $domainFile = $this->createFileResult([
            'classDefinitions' => [
                'User' => [
                    'fqn' => 'App\Domain\User',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Controller\UserController',
                    'type' => 'new',
                    'line' => 10,
                    'context' => 'User',
                ],
            ],
            'namespace' => 'App\Domain',
        ], '/Domain/User.php');

        // Controller class (so it's in the graph and has a layer)
        $controllerFile = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Controller',
        ], '/Controller/UserController.php');

        $result = $this->analyzer->analyze([$domainFile, $controllerFile]);

        $this->assertLessThan(100, $result->score);
    }

    public function testAnalyzeHandlesFileDependencyContext(): void
    {
        $fileResult = $this->createFileResult([
            'classDefinitions' => [
                'Test' => [
                    'fqn' => 'App\Test',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Other',
                    'type' => 'use',
                    'line' => 5,
                    'context' => 'file', // Should be skipped
                ],
            ],
            'namespace' => 'App',
        ]);

        $result = $this->analyzer->analyze([$fileResult]);

        // File context dependencies should not create edges
        $this->assertEmpty($result->dependencyGraph['edges']);
    }

    public function testPerfectArchitectureGetsHighScore(): void
    {
        // Clean layered architecture
        $controllerFile = $this->createFileResult([
            'classDefinitions' => [
                'UserController' => [
                    'fqn' => 'App\Controller\UserController',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Service\UserService',
                    'type' => 'type_hint',
                    'line' => 10,
                    'context' => 'UserController',
                ],
            ],
            'namespace' => 'App\Controller',
        ]);

        $serviceFile = $this->createFileResult([
            'classDefinitions' => [
                'UserService' => [
                    'fqn' => 'App\Service\UserService',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [
                [
                    'fqn' => 'App\Domain\User',
                    'type' => 'type_hint',
                    'line' => 10,
                    'context' => 'UserService',
                ],
            ],
            'namespace' => 'App\Service',
        ], '/Service.php');

        $domainFile = $this->createFileResult([
            'classDefinitions' => [
                'User' => [
                    'fqn' => 'App\Domain\User',
                    'extends' => null,
                    'implements' => [],
                ],
            ],
            'dependencies' => [],
            'namespace' => 'App\Domain',
        ], '/Domain/User.php');

        $result = $this->analyzer->analyze([$controllerFile, $serviceFile, $domainFile]);

        // Should have high score (no violations)
        $this->assertGreaterThanOrEqual(85, $result->score);
        $this->assertSame('A', $result->rating);
    }

    private function createFileResult(array $dependencies, string $path = '/test.php'): FileResult
    {
        return new FileResult(
            path: $path,
            relativePath: basename($path),
            classes: [],
            loc: [],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 80,
            miRating: 'B',
            dependencies: $dependencies,
        );
    }
}
