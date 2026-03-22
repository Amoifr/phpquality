<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Result;

use App\Analyzer\Result\ClassResult;
use App\Analyzer\Result\MethodResult;
use PHPUnit\Framework\TestCase;

class ClassResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $methods = [
            new MethodResult('method1', 'TestClass', 10, 20, 2, 'A', 90.0, 'A', 11),
            new MethodResult('method2', 'TestClass', 22, 35, 5, 'B', 75.0, 'B', 14),
        ];

        $result = new ClassResult(
            name: 'TestClass',
            namespace: 'App\\Tests',
            filePath: '/path/to/TestClass.php',
            startLine: 5,
            endLine: 40,
            methods: $methods,
            lcom: 0.25,
            lcomRating: 'B',
            totalLoc: 36,
            methodCount: 2,
            propertyCount: 3,
            maxCcn: 5,
            avgCcn: 3.5,
            mi: 82.5,
            miRating: 'B',
            category: 'Service'
        );

        $this->assertSame('TestClass', $result->name);
        $this->assertSame('App\\Tests', $result->namespace);
        $this->assertSame('/path/to/TestClass.php', $result->filePath);
        $this->assertSame(5, $result->startLine);
        $this->assertSame(40, $result->endLine);
        $this->assertCount(2, $result->methods);
        $this->assertSame(0.25, $result->lcom);
        $this->assertSame('B', $result->lcomRating);
        $this->assertSame(36, $result->totalLoc);
        $this->assertSame(2, $result->methodCount);
        $this->assertSame(3, $result->propertyCount);
        $this->assertSame(5, $result->maxCcn);
        $this->assertSame(3.5, $result->avgCcn);
        $this->assertSame(82.5, $result->mi);
        $this->assertSame('B', $result->miRating);
        $this->assertSame('Service', $result->category);
    }

    public function testGetFullyQualifiedNameWithNamespace(): void
    {
        $result = new ClassResult(
            name: 'MyClass',
            namespace: 'App\\Domain\\Entity',
            filePath: '/path/to/file.php',
            startLine: 1,
            endLine: 50,
            methods: [],
            lcom: 0.0,
            lcomRating: 'A',
            totalLoc: 50,
            methodCount: 0,
            propertyCount: 0,
            maxCcn: 0,
            avgCcn: 0.0,
            mi: 100.0,
            miRating: 'A'
        );

        $this->assertSame('App\\Domain\\Entity\\MyClass', $result->getFullyQualifiedName());
    }

    public function testGetFullyQualifiedNameWithoutNamespace(): void
    {
        $result = new ClassResult(
            name: 'GlobalClass',
            namespace: '',
            filePath: '/path/to/file.php',
            startLine: 1,
            endLine: 20,
            methods: [],
            lcom: 0.0,
            lcomRating: 'A',
            totalLoc: 20,
            methodCount: 0,
            propertyCount: 0,
            maxCcn: 0,
            avgCcn: 0.0,
            mi: 100.0,
            miRating: 'A'
        );

        $this->assertSame('GlobalClass', $result->getFullyQualifiedName());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $method = new MethodResult('doSomething', 'TestClass', 10, 20, 3, 'A', 85.0, 'A', 11);

        $result = new ClassResult(
            name: 'TestClass',
            namespace: 'App',
            filePath: '/path/to/TestClass.php',
            startLine: 5,
            endLine: 25,
            methods: [$method],
            lcom: 0.15,
            lcomRating: 'A',
            totalLoc: 21,
            methodCount: 1,
            propertyCount: 2,
            maxCcn: 3,
            avgCcn: 3.0,
            mi: 85.0,
            miRating: 'A',
            category: 'Controller'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('namespace', $array);
        $this->assertArrayHasKey('fqn', $array);
        $this->assertArrayHasKey('filePath', $array);
        $this->assertArrayHasKey('methods', $array);
        $this->assertArrayHasKey('lcom', $array);
        $this->assertArrayHasKey('category', $array);

        $this->assertSame('App\\TestClass', $array['fqn']);
        $this->assertCount(1, $array['methods']);
        $this->assertSame('doSomething', $array['methods'][0]['name']);
    }

    public function testDefaultCategoryIsNull(): void
    {
        $result = new ClassResult(
            name: 'Test',
            namespace: 'App',
            filePath: '/path/to/file.php',
            startLine: 1,
            endLine: 10,
            methods: [],
            lcom: 0.0,
            lcomRating: 'A',
            totalLoc: 10,
            methodCount: 0,
            propertyCount: 0,
            maxCcn: 0,
            avgCcn: 0.0,
            mi: 100.0,
            miRating: 'A'
        );

        $this->assertNull($result->category);
    }

    /**
     * @dataProvider lcomRatingProvider
     */
    public function testLcomRatings(float $lcom, string $expectedRating): void
    {
        $result = new ClassResult(
            name: 'Test',
            namespace: 'App',
            filePath: '/path/to/file.php',
            startLine: 1,
            endLine: 10,
            methods: [],
            lcom: $lcom,
            lcomRating: $expectedRating,
            totalLoc: 10,
            methodCount: 0,
            propertyCount: 0,
            maxCcn: 0,
            avgCcn: 0.0,
            mi: 100.0,
            miRating: 'A'
        );

        $this->assertSame($expectedRating, $result->lcomRating);
    }

    public static function lcomRatingProvider(): array
    {
        return [
            'excellent cohesion' => [0.1, 'A'],
            'good cohesion' => [0.3, 'B'],
            'moderate cohesion' => [0.5, 'C'],
            'poor cohesion' => [0.7, 'D'],
            'very poor cohesion' => [0.9, 'F'],
        ];
    }
}
