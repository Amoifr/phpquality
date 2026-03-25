<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\ProjectType;

use PhpQuality\Analyzer\ProjectType\ProjectTypeDetector;
use PhpQuality\Analyzer\ProjectType\ProjectTypeInterface;
use PHPUnit\Framework\TestCase;

class ProjectTypeDetectorTest extends TestCase
{
    public function testConstructorRegistersProjectTypes(): void
    {
        $type1 = $this->createMockProjectType('symfony', 'Symfony', 80);
        $type2 = $this->createMockProjectType('laravel', 'Laravel', 0);

        $detector = new ProjectTypeDetector([$type1, $type2]);

        $this->assertCount(2, $detector->getAvailableTypes());
        $this->assertContains('symfony', $detector->getTypeNames());
        $this->assertContains('laravel', $detector->getTypeNames());
    }

    public function testGetProjectTypeReturnsCorrectType(): void
    {
        $symfonyType = $this->createMockProjectType('symfony', 'Symfony', 0);
        $laravelType = $this->createMockProjectType('laravel', 'Laravel', 0);

        $detector = new ProjectTypeDetector([$symfonyType, $laravelType]);

        $result = $detector->getProjectType('symfony');

        $this->assertSame($symfonyType, $result);
    }

    public function testGetProjectTypeThrowsExceptionForUnknownType(): void
    {
        $type = $this->createMockProjectType('php', 'PHP', 0);
        $detector = new ProjectTypeDetector([$type]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown project type "unknown"');

        $detector->getProjectType('unknown');
    }

    public function testDetectReturnsHighestScoringType(): void
    {
        $type1 = $this->createMockProjectType('symfony', 'Symfony', 90);
        $type2 = $this->createMockProjectType('laravel', 'Laravel', 70);
        $type3 = $this->createMockProjectType('php', 'PHP', 50);

        $detector = new ProjectTypeDetector([$type1, $type2, $type3]);

        $result = $detector->detect('/some/path');

        $this->assertSame('symfony', $result->getName());
    }

    public function testDetectReturnsPhpTypeWhenNoMatchFound(): void
    {
        $type1 = $this->createMockProjectType('symfony', 'Symfony', 0);
        $type2 = $this->createMockProjectType('php', 'PHP', 0);

        $detector = new ProjectTypeDetector([$type1, $type2]);

        $result = $detector->detect('/some/path');

        $this->assertSame('php', $result->getName());
    }

    public function testDetectIgnoresZeroScores(): void
    {
        $type1 = $this->createMockProjectType('symfony', 'Symfony', 0);
        $type2 = $this->createMockProjectType('laravel', 'Laravel', 60);
        $type3 = $this->createMockProjectType('php', 'PHP', 0);

        $detector = new ProjectTypeDetector([$type1, $type2, $type3]);

        $result = $detector->detect('/some/path');

        $this->assertSame('laravel', $result->getName());
    }

    public function testGetAvailableTypesReturnsAllTypes(): void
    {
        $type1 = $this->createMockProjectType('symfony', 'Symfony', 0);
        $type2 = $this->createMockProjectType('laravel', 'Laravel', 0);

        $detector = new ProjectTypeDetector([$type1, $type2]);

        $types = $detector->getAvailableTypes();

        $this->assertCount(2, $types);
        $this->assertArrayHasKey('symfony', $types);
        $this->assertArrayHasKey('laravel', $types);
    }

    public function testGetTypeNamesReturnsAllNames(): void
    {
        $type1 = $this->createMockProjectType('symfony', 'Symfony', 0);
        $type2 = $this->createMockProjectType('php', 'PHP', 0);

        $detector = new ProjectTypeDetector([$type1, $type2]);

        $names = $detector->getTypeNames();

        $this->assertCount(2, $names);
        $this->assertContains('symfony', $names);
        $this->assertContains('php', $names);
    }

    public function testEmptyProjectTypesListDefaultsToPHP(): void
    {
        $phpType = $this->createMockProjectType('php', 'PHP', 0);
        $detector = new ProjectTypeDetector([$phpType]);

        $result = $detector->detect('/some/path');

        $this->assertSame('php', $result->getName());
    }

    private function createMockProjectType(string $name, string $label, int $score): ProjectTypeInterface
    {
        $mock = $this->createMock(ProjectTypeInterface::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('getLabel')->willReturn($label);
        $mock->method('detect')->willReturn($score);
        $mock->method('getExcludedPaths')->willReturn([]);
        $mock->method('getArchitecturalPatterns')->willReturn([]);
        $mock->method('getRecommendedThresholds')->willReturn(['ccn' => 10, 'lcom' => 0.8, 'mi' => 20]);
        $mock->method('getClassCategories')->willReturn([]);
        $mock->method('getDescription')->willReturn('Description for ' . $name);

        return $mock;
    }
}
