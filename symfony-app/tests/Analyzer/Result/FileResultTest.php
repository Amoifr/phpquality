<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Result;

use App\Analyzer\Result\FileResult;
use App\Analyzer\Result\ClassResult;
use App\Analyzer\Result\MethodResult;
use PHPUnit\Framework\TestCase;

class FileResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $result = $this->createFileResult();

        $this->assertSame('/path/to/TestFile.php', $result->path);
        $this->assertSame('TestFile.php', $result->relativePath);
        $this->assertCount(1, $result->classes);
        $this->assertSame(100, $result->loc['loc']);
        $this->assertSame(85.5, $result->mi);
        $this->assertSame('A', $result->miRating);
        $this->assertFalse($result->hasErrors);
        $this->assertNull($result->error);
    }

    public function testGetTotalLoc(): void
    {
        $result = $this->createFileResult();

        $this->assertSame(100, $result->getTotalLoc());
    }

    public function testGetLogicalLoc(): void
    {
        $result = $this->createFileResult();

        $this->assertSame(75, $result->getLogicalLoc());
    }

    public function testGetCommentLines(): void
    {
        $result = $this->createFileResult();

        $this->assertSame(15, $result->getCommentLines());
    }

    public function testGetMaxCcn(): void
    {
        $result = $this->createFileResult();

        $this->assertSame(8, $result->getMaxCcn());
    }

    public function testGetAvgCcn(): void
    {
        $result = $this->createFileResult();

        $this->assertSame(3.5, $result->getAvgCcn());
    }

    public function testGetNamespace(): void
    {
        $result = $this->createFileResult();

        $this->assertSame('App\\Tests', $result->getNamespace());
    }

    public function testGetUseStatements(): void
    {
        $result = $this->createFileResult();

        $statements = $result->getUseStatements();
        $this->assertCount(2, $statements);
        $this->assertContains('App\\Service\\TestService', $statements);
    }

    public function testGetDependencies(): void
    {
        $result = $this->createFileResult();

        $dependencies = $result->getDependencies();
        $this->assertCount(2, $dependencies);
    }

    public function testGetUniqueDependencyCount(): void
    {
        $result = $this->createFileResult();

        $this->assertSame(2, $result->getUniqueDependencyCount());
    }

    public function testFileWithErrors(): void
    {
        $result = new FileResult(
            path: '/path/to/BrokenFile.php',
            relativePath: 'BrokenFile.php',
            classes: [],
            loc: [],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 0.0,
            miRating: 'F',
            hasErrors: true,
            error: 'Syntax error on line 10'
        );

        $this->assertTrue($result->hasErrors);
        $this->assertSame('Syntax error on line 10', $result->error);
        $this->assertSame(0, $result->getTotalLoc());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $result = $this->createFileResult();

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('path', $array);
        $this->assertArrayHasKey('relativePath', $array);
        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('loc', $array);
        $this->assertArrayHasKey('ccn', $array);
        $this->assertArrayHasKey('halstead', $array);
        $this->assertArrayHasKey('lcom', $array);
        $this->assertArrayHasKey('mi', $array);
        $this->assertArrayHasKey('miRating', $array);
        $this->assertArrayHasKey('hasErrors', $array);
        $this->assertArrayHasKey('error', $array);
        $this->assertArrayHasKey('dependencies', $array);

        $this->assertCount(1, $array['classes']);
        $this->assertSame('TestClass', $array['classes'][0]['name']);
    }

    public function testEmptyDependencies(): void
    {
        $result = new FileResult(
            path: '/path/to/Simple.php',
            relativePath: 'Simple.php',
            classes: [],
            loc: ['loc' => 10, 'lloc' => 8, 'cloc' => 2],
            ccn: ['summary' => ['maxCcn' => 1, 'averageCcn' => 1.0]],
            halstead: [],
            lcom: [],
            mi: 100.0,
            miRating: 'A',
            dependencies: []
        );

        $this->assertNull($result->getNamespace());
        $this->assertSame([], $result->getUseStatements());
        $this->assertSame([], $result->getDependencies());
        $this->assertSame(0, $result->getUniqueDependencyCount());
    }

    private function createFileResult(): FileResult
    {
        $method = new MethodResult(
            name: 'testMethod',
            className: 'TestClass',
            startLine: 15,
            endLine: 25,
            ccn: 3,
            ccnRating: 'A',
            mi: 85.0,
            miRating: 'A',
            loc: 11
        );

        $class = new ClassResult(
            name: 'TestClass',
            namespace: 'App\\Tests',
            filePath: '/path/to/TestFile.php',
            startLine: 10,
            endLine: 50,
            methods: [$method],
            lcom: 0.2,
            lcomRating: 'A',
            totalLoc: 41,
            methodCount: 1,
            propertyCount: 2,
            maxCcn: 3,
            avgCcn: 3.0,
            mi: 85.0,
            miRating: 'A'
        );

        return new FileResult(
            path: '/path/to/TestFile.php',
            relativePath: 'TestFile.php',
            classes: [$class],
            loc: ['loc' => 100, 'lloc' => 75, 'cloc' => 15],
            ccn: ['summary' => ['maxCcn' => 8, 'averageCcn' => 3.5]],
            halstead: ['volume' => 500, 'difficulty' => 10],
            lcom: ['average' => 0.2],
            mi: 85.5,
            miRating: 'A',
            dependencies: [
                'namespace' => 'App\\Tests',
                'useStatements' => [
                    'App\\Service\\TestService',
                    'App\\Entity\\User',
                ],
                'dependencies' => [
                    ['fqn' => 'App\\Service\\TestService', 'type' => 'use'],
                    ['fqn' => 'App\\Entity\\User', 'type' => 'use'],
                ],
            ]
        );
    }
}
