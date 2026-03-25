<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Result;

use PhpQuality\Analyzer\Result\LayerViolation;
use PHPUnit\Framework\TestCase;

class LayerViolationTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $violation = new LayerViolation(
            sourceClass: 'App\\Domain\\Entity\\User',
            sourceLayer: 'Domain',
            targetClass: 'App\\Infrastructure\\Repository\\UserRepository',
            targetLayer: 'Infrastructure',
            dependencyType: 'use',
            line: 15,
            filePath: '/path/to/User.php',
            severity: 'error'
        );

        $this->assertSame('App\\Domain\\Entity\\User', $violation->sourceClass);
        $this->assertSame('Domain', $violation->sourceLayer);
        $this->assertSame('App\\Infrastructure\\Repository\\UserRepository', $violation->targetClass);
        $this->assertSame('Infrastructure', $violation->targetLayer);
        $this->assertSame('use', $violation->dependencyType);
        $this->assertSame(15, $violation->line);
        $this->assertSame('/path/to/User.php', $violation->filePath);
        $this->assertSame('error', $violation->severity);
    }

    public function testDefaultSeverityIsError(): void
    {
        $violation = new LayerViolation(
            sourceClass: 'SourceClass',
            sourceLayer: 'Domain',
            targetClass: 'TargetClass',
            targetLayer: 'Infrastructure',
            dependencyType: 'new',
            line: 10,
            filePath: '/path/file.php'
        );

        $this->assertSame('error', $violation->severity);
    }

    public function testGetMessage(): void
    {
        $violation = new LayerViolation(
            sourceClass: 'App\\Domain\\User',
            sourceLayer: 'Domain',
            targetClass: 'App\\Infrastructure\\UserRepo',
            targetLayer: 'Infrastructure',
            dependencyType: 'extends',
            line: 5,
            filePath: '/path/User.php'
        );

        $message = $violation->getMessage();

        $this->assertStringContainsString('App\\Domain\\User', $message);
        $this->assertStringContainsString('Domain', $message);
        $this->assertStringContainsString('App\\Infrastructure\\UserRepo', $message);
        $this->assertStringContainsString('Infrastructure', $message);
        $this->assertStringContainsString('extends', $message);
    }

    public function testToArray(): void
    {
        $violation = new LayerViolation(
            sourceClass: 'SourceClass',
            sourceLayer: 'Application',
            targetClass: 'TargetClass',
            targetLayer: 'Controller',
            dependencyType: 'implements',
            line: 20,
            filePath: '/path/source.php',
            severity: 'warning'
        );

        $array = $violation->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('sourceClass', $array);
        $this->assertArrayHasKey('sourceLayer', $array);
        $this->assertArrayHasKey('targetClass', $array);
        $this->assertArrayHasKey('targetLayer', $array);
        $this->assertArrayHasKey('dependencyType', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('filePath', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayHasKey('message', $array);

        $this->assertSame('SourceClass', $array['sourceClass']);
        $this->assertSame('warning', $array['severity']);
        $this->assertSame(20, $array['line']);
    }

    /**
     * @dataProvider dependencyTypeProvider
     */
    public function testDifferentDependencyTypes(string $type): void
    {
        $violation = new LayerViolation(
            sourceClass: 'Source',
            sourceLayer: 'Domain',
            targetClass: 'Target',
            targetLayer: 'Infrastructure',
            dependencyType: $type,
            line: 1,
            filePath: '/path/file.php'
        );

        $this->assertSame($type, $violation->dependencyType);
        $this->assertStringContainsString($type, $violation->getMessage());
    }

    public static function dependencyTypeProvider(): array
    {
        return [
            ['use'],
            ['new'],
            ['extends'],
            ['implements'],
            ['static'],
            ['instanceof'],
        ];
    }
}
