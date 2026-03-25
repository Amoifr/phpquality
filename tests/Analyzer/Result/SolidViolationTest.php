<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Result;

use PhpQuality\Analyzer\Result\SolidViolation;
use PHPUnit\Framework\TestCase;

class SolidViolationTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $violation = new SolidViolation(
            principle: SolidViolation::SRP,
            className: 'App\\Service\\GodClass',
            filePath: '/path/to/GodClass.php',
            message: 'Class has too many responsibilities',
            severity: SolidViolation::SEVERITY_ERROR,
            details: ['methodCount' => 50, 'lcom' => 0.9]
        );

        $this->assertSame(SolidViolation::SRP, $violation->principle);
        $this->assertSame('App\\Service\\GodClass', $violation->className);
        $this->assertSame('/path/to/GodClass.php', $violation->filePath);
        $this->assertSame('Class has too many responsibilities', $violation->message);
        $this->assertSame(SolidViolation::SEVERITY_ERROR, $violation->severity);
        $this->assertSame(['methodCount' => 50, 'lcom' => 0.9], $violation->details);
    }

    public function testDefaultSeverityIsWarning(): void
    {
        $violation = new SolidViolation(
            principle: SolidViolation::OCP,
            className: 'TestClass',
            filePath: '/path/file.php',
            message: 'Test message'
        );

        $this->assertSame(SolidViolation::SEVERITY_WARNING, $violation->severity);
    }

    public function testDefaultDetailsIsEmptyArray(): void
    {
        $violation = new SolidViolation(
            principle: SolidViolation::DIP,
            className: 'TestClass',
            filePath: '/path/file.php',
            message: 'Test message'
        );

        $this->assertSame([], $violation->details);
    }

    /**
     * @dataProvider principleNameProvider
     */
    public function testGetPrincipleName(string $principle, string $expectedName): void
    {
        $violation = new SolidViolation(
            principle: $principle,
            className: 'TestClass',
            filePath: '/path/file.php',
            message: 'Test'
        );

        $this->assertSame($expectedName, $violation->getPrincipleName());
    }

    public static function principleNameProvider(): array
    {
        return [
            [SolidViolation::SRP, 'Single Responsibility Principle'],
            [SolidViolation::OCP, 'Open/Closed Principle'],
            [SolidViolation::LSP, 'Liskov Substitution Principle'],
            [SolidViolation::ISP, 'Interface Segregation Principle'],
            [SolidViolation::DIP, 'Dependency Inversion Principle'],
            ['UNKNOWN', 'UNKNOWN'],
        ];
    }

    public function testToArray(): void
    {
        $violation = new SolidViolation(
            principle: SolidViolation::ISP,
            className: 'App\\Contract\\BigInterface',
            filePath: '/path/BigInterface.php',
            message: 'Interface has too many methods',
            severity: SolidViolation::SEVERITY_WARNING,
            details: ['methodCount' => 25]
        );

        $array = $violation->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('principle', $array);
        $this->assertArrayHasKey('principleName', $array);
        $this->assertArrayHasKey('className', $array);
        $this->assertArrayHasKey('filePath', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('severity', $array);
        $this->assertArrayHasKey('details', $array);

        $this->assertSame(SolidViolation::ISP, $array['principle']);
        $this->assertSame('Interface Segregation Principle', $array['principleName']);
        $this->assertSame('App\\Contract\\BigInterface', $array['className']);
    }

    public function testConstants(): void
    {
        $this->assertSame('SRP', SolidViolation::SRP);
        $this->assertSame('OCP', SolidViolation::OCP);
        $this->assertSame('LSP', SolidViolation::LSP);
        $this->assertSame('ISP', SolidViolation::ISP);
        $this->assertSame('DIP', SolidViolation::DIP);

        $this->assertSame('error', SolidViolation::SEVERITY_ERROR);
        $this->assertSame('warning', SolidViolation::SEVERITY_WARNING);
        $this->assertSame('info', SolidViolation::SEVERITY_INFO);
    }
}
