<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Architecture;

use PhpQuality\Analyzer\Architecture\SolidAnalyzer;
use PhpQuality\Analyzer\Result\FileResult;
use PhpQuality\Analyzer\Result\ClassResult;
use PhpQuality\Analyzer\Result\MethodResult;
use PhpQuality\Analyzer\Result\SolidViolation;
use PHPUnit\Framework\TestCase;

class SolidAnalyzerTest extends TestCase
{
    private SolidAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new SolidAnalyzer();
    }

    public function testAnalyzeReturnsEmptyArrayForEmptyInput(): void
    {
        $violations = $this->analyzer->analyze([]);

        $this->assertIsArray($violations);
        $this->assertEmpty($violations);
    }

    public function testAnalyzeSkipsFilesWithErrors(): void
    {
        $file = new FileResult(
            path: '/path/to/file.php',
            relativePath: 'file.php',
            classes: [],
            loc: ['loc' => 100],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 80.0,
            miRating: 'B',
            hasErrors: true,
            error: 'Parse error'
        );

        $violations = $this->analyzer->analyze([$file]);

        $this->assertEmpty($violations);
    }

    public function testDetectSrpViolationHighMethodCount(): void
    {
        $methods = [];
        for ($i = 0; $i < 25; $i++) {
            $methods[] = new MethodResult(
                "method$i", 'GodClass', $i * 10, $i * 10 + 9, 1, 'A', 90.0, 'A', 10
            );
        }

        $class = new ClassResult(
            name: 'GodClass',
            namespace: 'App',
            filePath: '/path/GodClass.php',
            startLine: 1,
            endLine: 600,
            methods: $methods,
            lcom: 0.8,
            lcomRating: 'D',
            totalLoc: 550,
            methodCount: 25,
            propertyCount: 10,
            maxCcn: 5,
            avgCcn: 3.0,
            mi: 50.0,
            miRating: 'C'
        );

        $file = new FileResult(
            path: '/path/GodClass.php',
            relativePath: 'GodClass.php',
            classes: [$class],
            loc: ['loc' => 550],
            ccn: ['summary' => ['maxCcn' => 5]],
            halstead: [],
            lcom: [],
            mi: 50.0,
            miRating: 'C',
            dependencies: ['dependencies' => []]
        );

        $violations = $this->analyzer->analyze([$file]);

        $srpViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::SRP);
        $this->assertNotEmpty($srpViolations);
    }

    public function testDetectSrpViolationHighLcom(): void
    {
        $methods = [];
        for ($i = 0; $i < 22; $i++) {
            $methods[] = new MethodResult(
                "method$i", 'LowCohesionClass', $i * 5, $i * 5 + 4, 1, 'A', 90.0, 'A', 5
            );
        }

        $class = new ClassResult(
            name: 'LowCohesionClass',
            namespace: 'App',
            filePath: '/path/LowCohesion.php',
            startLine: 1,
            endLine: 600,
            methods: $methods,
            lcom: 0.95,
            lcomRating: 'F',
            totalLoc: 550,
            methodCount: 22,
            propertyCount: 15,
            maxCcn: 3,
            avgCcn: 2.0,
            mi: 60.0,
            miRating: 'C'
        );

        $file = new FileResult(
            path: '/path/LowCohesion.php',
            relativePath: 'LowCohesion.php',
            classes: [$class],
            loc: ['loc' => 550],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 60.0,
            miRating: 'C',
            dependencies: ['dependencies' => []]
        );

        $violations = $this->analyzer->analyze([$file]);

        $srpViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::SRP);
        $this->assertNotEmpty($srpViolations);
    }

    public function testNoSrpViolationForSmallClass(): void
    {
        $methods = [
            new MethodResult('method1', 'SmallClass', 5, 15, 2, 'A', 90.0, 'A', 10),
            new MethodResult('method2', 'SmallClass', 17, 27, 1, 'A', 95.0, 'A', 10),
        ];

        $class = new ClassResult(
            name: 'SmallClass',
            namespace: 'App',
            filePath: '/path/Small.php',
            startLine: 1,
            endLine: 30,
            methods: $methods,
            lcom: 0.1,
            lcomRating: 'A',
            totalLoc: 30,
            methodCount: 2,
            propertyCount: 2,
            maxCcn: 2,
            avgCcn: 1.5,
            mi: 95.0,
            miRating: 'A'
        );

        $file = new FileResult(
            path: '/path/Small.php',
            relativePath: 'Small.php',
            classes: [$class],
            loc: ['loc' => 30],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 95.0,
            miRating: 'A',
            dependencies: ['dependencies' => []]
        );

        $violations = $this->analyzer->analyze([$file]);

        $srpViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::SRP);
        $this->assertEmpty($srpViolations);
    }

    public function testDetectIspViolationFatInterface(): void
    {
        $file = new FileResult(
            path: '/path/FatInterface.php',
            relativePath: 'FatInterface.php',
            classes: [],
            loc: ['loc' => 50],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 80.0,
            miRating: 'B',
            dependencies: [
                'interfaceDefinitions' => [
                    'FatInterface' => [
                        'fqn' => 'App\\Contract\\FatInterface',
                        'methods' => 12,
                    ],
                ],
            ]
        );

        $violations = $this->analyzer->analyze([$file]);

        $ispViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::ISP);
        $this->assertNotEmpty($ispViolations);

        $firstViolation = reset($ispViolations);
        $this->assertStringContainsString('12 methods', $firstViolation->message);
    }

    public function testNoIspViolationForSmallInterface(): void
    {
        $file = new FileResult(
            path: '/path/SmallInterface.php',
            relativePath: 'SmallInterface.php',
            classes: [],
            loc: ['loc' => 20],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 95.0,
            miRating: 'A',
            dependencies: [
                'interfaceDefinitions' => [
                    'SmallInterface' => [
                        'fqn' => 'App\\Contract\\SmallInterface',
                        'methods' => 3,
                    ],
                ],
            ]
        );

        $violations = $this->analyzer->analyze([$file]);

        $ispViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::ISP);
        $this->assertEmpty($ispViolations);
    }

    public function testViolationSeverityLevels(): void
    {
        // Create a very bad class that should trigger ERROR severity
        $methods = [];
        for ($i = 0; $i < 30; $i++) {
            $methods[] = new MethodResult(
                "method$i", 'TerribleClass', $i * 20, $i * 20 + 19, 3, 'A', 70.0, 'B', 20
            );
        }

        $class = new ClassResult(
            name: 'TerribleClass',
            namespace: 'App',
            filePath: '/path/Terrible.php',
            startLine: 1,
            endLine: 800,
            methods: $methods,
            lcom: 0.95,
            lcomRating: 'F',
            totalLoc: 750,
            methodCount: 30,
            propertyCount: 20,
            maxCcn: 15,
            avgCcn: 5.0,
            mi: 30.0,
            miRating: 'D'
        );

        $file = new FileResult(
            path: '/path/Terrible.php',
            relativePath: 'Terrible.php',
            classes: [$class],
            loc: ['loc' => 750],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 30.0,
            miRating: 'D',
            dependencies: ['dependencies' => []]
        );

        $violations = $this->analyzer->analyze([$file]);

        $srpViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::SRP);
        $this->assertNotEmpty($srpViolations);

        $firstViolation = reset($srpViolations);
        $this->assertSame(SolidViolation::SEVERITY_ERROR, $firstViolation->severity);
    }

    public function testAnalyzeMultipleFiles(): void
    {
        $class1 = $this->createGodClass('GodClass1');
        $class2 = $this->createGodClass('GodClass2');

        $file1 = new FileResult(
            path: '/path/God1.php',
            relativePath: 'God1.php',
            classes: [$class1],
            loc: ['loc' => 600],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 40.0,
            miRating: 'C',
            dependencies: ['dependencies' => []]
        );

        $file2 = new FileResult(
            path: '/path/God2.php',
            relativePath: 'God2.php',
            classes: [$class2],
            loc: ['loc' => 600],
            ccn: [],
            halstead: [],
            lcom: [],
            mi: 40.0,
            miRating: 'C',
            dependencies: ['dependencies' => []]
        );

        $violations = $this->analyzer->analyze([$file1, $file2]);

        $srpViolations = array_filter($violations, fn($v) => $v->principle === SolidViolation::SRP);
        $this->assertGreaterThanOrEqual(2, count($srpViolations));
    }

    private function createGodClass(string $name): ClassResult
    {
        $methods = [];
        for ($i = 0; $i < 25; $i++) {
            $methods[] = new MethodResult(
                "method$i", $name, $i * 10, $i * 10 + 9, 2, 'A', 80.0, 'B', 10
            );
        }

        return new ClassResult(
            name: $name,
            namespace: 'App',
            filePath: "/path/$name.php",
            startLine: 1,
            endLine: 600,
            methods: $methods,
            lcom: 0.85,
            lcomRating: 'F',
            totalLoc: 550,
            methodCount: 25,
            propertyCount: 15,
            maxCcn: 8,
            avgCcn: 4.0,
            mi: 45.0,
            miRating: 'C'
        );
    }
}
