<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Result;

use PhpQuality\Analyzer\Result\ArchitectureResult;
use PhpQuality\Analyzer\Result\LayerViolation;
use PhpQuality\Analyzer\Result\SolidViolation;
use PHPUnit\Framework\TestCase;

class ArchitectureResultTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $result = new ArchitectureResult(
            dependencyGraph: ['nodes' => [], 'edges' => []],
            layerAssignments: ['App\\Controller\\UserController' => 'Controller'],
            layerViolations: [],
            solidViolations: [],
            circularDependencies: [],
            layerStats: ['Controller' => 1],
            score: 95.0,
            rating: 'A'
        );

        $this->assertSame(['nodes' => [], 'edges' => []], $result->dependencyGraph);
        $this->assertArrayHasKey('App\\Controller\\UserController', $result->layerAssignments);
        $this->assertEmpty($result->layerViolations);
        $this->assertEmpty($result->solidViolations);
        $this->assertSame(95.0, $result->score);
        $this->assertSame('A', $result->rating);
    }

    public function testGetLayerViolationCount(): void
    {
        $violations = [
            new LayerViolation('A', 'Domain', 'B', 'Infrastructure', 'use', 10, '/path/a.php'),
            new LayerViolation('C', 'Domain', 'D', 'Controller', 'new', 20, '/path/c.php'),
        ];

        $result = new ArchitectureResult(
            dependencyGraph: [],
            layerAssignments: [],
            layerViolations: $violations,
            solidViolations: [],
            circularDependencies: [],
            layerStats: [],
            score: 70.0,
            rating: 'B'
        );

        $this->assertSame(2, $result->getLayerViolationCount());
    }

    public function testGetSolidViolationCount(): void
    {
        $violations = [
            new SolidViolation(SolidViolation::SRP, 'GodClass', '/path/god.php', 'Too many methods'),
            new SolidViolation(SolidViolation::DIP, 'BadClass', '/path/bad.php', 'Depends on concrete'),
            new SolidViolation(SolidViolation::ISP, 'FatInterface', '/path/fat.php', 'Too many methods'),
        ];

        $result = new ArchitectureResult(
            dependencyGraph: [],
            layerAssignments: [],
            layerViolations: [],
            solidViolations: $violations,
            circularDependencies: [],
            layerStats: [],
            score: 60.0,
            rating: 'C'
        );

        $this->assertSame(3, $result->getSolidViolationCount());
    }

    public function testGetCircularDependencyCount(): void
    {
        $circular = [
            ['A', 'B', 'C', 'A'],
            ['X', 'Y', 'X'],
        ];

        $result = new ArchitectureResult(
            dependencyGraph: [],
            layerAssignments: [],
            layerViolations: [],
            solidViolations: [],
            circularDependencies: $circular,
            layerStats: [],
            score: 50.0,
            rating: 'C'
        );

        $this->assertSame(2, $result->getCircularDependencyCount());
    }

    public function testGetSolidViolationsByPrinciple(): void
    {
        $violations = [
            new SolidViolation(SolidViolation::SRP, 'Class1', '/path/1.php', 'Message 1'),
            new SolidViolation(SolidViolation::SRP, 'Class2', '/path/2.php', 'Message 2'),
            new SolidViolation(SolidViolation::DIP, 'Class3', '/path/3.php', 'Message 3'),
            new SolidViolation(SolidViolation::ISP, 'Class4', '/path/4.php', 'Message 4'),
        ];

        $result = new ArchitectureResult(
            dependencyGraph: [],
            layerAssignments: [],
            layerViolations: [],
            solidViolations: $violations,
            circularDependencies: [],
            layerStats: [],
            score: 40.0,
            rating: 'D'
        );

        $grouped = $result->getSolidViolationsByPrinciple();

        $this->assertCount(2, $grouped[SolidViolation::SRP]);
        $this->assertCount(1, $grouped[SolidViolation::DIP]);
        $this->assertCount(1, $grouped[SolidViolation::ISP]);
        $this->assertEmpty($grouped[SolidViolation::OCP]);
    }

    public function testGetLayerViolationsBySource(): void
    {
        $violations = [
            new LayerViolation('A', 'Domain', 'B', 'Infrastructure', 'use', 10, '/path/a.php'),
            new LayerViolation('C', 'Domain', 'D', 'Controller', 'new', 20, '/path/c.php'),
            new LayerViolation('E', 'Application', 'F', 'Controller', 'use', 30, '/path/e.php'),
        ];

        $result = new ArchitectureResult(
            dependencyGraph: [],
            layerAssignments: [],
            layerViolations: $violations,
            solidViolations: [],
            circularDependencies: [],
            layerStats: [],
            score: 60.0,
            rating: 'C'
        );

        $grouped = $result->getLayerViolationsBySource();

        $this->assertArrayHasKey('Domain', $grouped);
        $this->assertArrayHasKey('Application', $grouped);
        $this->assertCount(2, $grouped['Domain']);
        $this->assertCount(1, $grouped['Application']);
    }

    public function testGetClassesByLayer(): void
    {
        $result = new ArchitectureResult(
            dependencyGraph: [],
            layerAssignments: [
                'App\\Controller\\UserController' => 'Controller',
                'App\\Controller\\OrderController' => 'Controller',
                'App\\Service\\UserService' => 'Application',
                'App\\Entity\\User' => 'Domain',
            ],
            layerViolations: [],
            solidViolations: [],
            circularDependencies: [],
            layerStats: [],
            score: 90.0,
            rating: 'A'
        );

        $byLayer = $result->getClassesByLayer();

        $this->assertArrayHasKey('Controller', $byLayer);
        $this->assertArrayHasKey('Application', $byLayer);
        $this->assertArrayHasKey('Domain', $byLayer);
        $this->assertCount(2, $byLayer['Controller']);
        $this->assertCount(1, $byLayer['Application']);
        $this->assertCount(1, $byLayer['Domain']);
    }

    public function testGetDependencyMatrix(): void
    {
        $result = new ArchitectureResult(
            dependencyGraph: [
                'edges' => [
                    ['from' => 'App\\Controller\\UserController', 'to' => 'App\\Service\\UserService'],
                    ['from' => 'App\\Controller\\UserController', 'to' => 'App\\Entity\\User'],
                    ['from' => 'App\\Service\\UserService', 'to' => 'App\\Entity\\User'],
                ]
            ],
            layerAssignments: [
                'App\\Controller\\UserController' => 'Controller',
                'App\\Service\\UserService' => 'Application',
                'App\\Entity\\User' => 'Domain',
            ],
            layerViolations: [],
            solidViolations: [],
            circularDependencies: [],
            layerStats: [],
            score: 85.0,
            rating: 'A'
        );

        $matrix = $result->getDependencyMatrix();

        $this->assertArrayHasKey('Controller', $matrix);
        $this->assertArrayHasKey('Application', $matrix);
        $this->assertArrayHasKey('Domain', $matrix);

        $this->assertSame(1, $matrix['Controller']['Application']);
        $this->assertSame(1, $matrix['Controller']['Domain']);
        $this->assertSame(1, $matrix['Application']['Domain']);
    }

    public function testToArray(): void
    {
        $layerViolation = new LayerViolation('A', 'Domain', 'B', 'Infra', 'use', 5, '/a.php');
        $solidViolation = new SolidViolation(SolidViolation::SRP, 'God', '/god.php', 'Too big');

        $result = new ArchitectureResult(
            dependencyGraph: ['nodes' => ['A', 'B'], 'edges' => []],
            layerAssignments: ['A' => 'Domain', 'B' => 'Infra'],
            layerViolations: [$layerViolation],
            solidViolations: [$solidViolation],
            circularDependencies: [['A', 'B', 'A']],
            layerStats: ['Domain' => 1, 'Infra' => 1],
            score: 75.0,
            rating: 'B'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('dependencyGraph', $array);
        $this->assertArrayHasKey('layerAssignments', $array);
        $this->assertArrayHasKey('layerViolations', $array);
        $this->assertArrayHasKey('solidViolations', $array);
        $this->assertArrayHasKey('circularDependencies', $array);
        $this->assertArrayHasKey('layerStats', $array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('rating', $array);
        $this->assertArrayHasKey('summary', $array);

        $this->assertSame(1, $array['summary']['layerViolationCount']);
        $this->assertSame(1, $array['summary']['solidViolationCount']);
        $this->assertSame(1, $array['summary']['circularDependencyCount']);
    }
}
