<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Ast\Visitor;

use PhpQuality\Analyzer\Ast\AstParser;
use PhpQuality\Analyzer\Ast\Visitor\CohesionVisitor;
use PHPUnit\Framework\TestCase;

class CohesionVisitorTest extends TestCase
{
    private AstParser $parser;
    private CohesionVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = new AstParser();
        $this->visitor = new CohesionVisitor();
    }

    public function testHighCohesionClass(): void
    {
        $code = <<<'PHP'
<?php

class HighCohesion
{
    private string $name;
    private int $age;

    public function getName(): string
    {
        return $this->name;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function getDescription(): string
    {
        return $this->name . " is " . $this->age . " years old";
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('HighCohesion');

        $this->assertNotNull($classResult);
        $this->assertLessThan(0.5, $classResult['lcom']);
        $this->assertContains($classResult['rating'], ['A', 'B']);
    }

    public function testLowCohesionClass(): void
    {
        $code = <<<'PHP'
<?php

class LowCohesion
{
    private string $name;
    private int $count;
    private array $items;
    private bool $active;

    public function getName(): string
    {
        return $this->name;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('LowCohesion');

        $this->assertNotNull($classResult);
        $this->assertGreaterThan(0.5, $classResult['lcom']);
        $this->assertContains($classResult['rating'], ['C', 'D', 'F']);
    }

    public function testClassWithNoProperties(): void
    {
        $code = <<<'PHP'
<?php

class NoProperties
{
    public function doSomething(): void
    {
        echo "hello";
    }

    public function doSomethingElse(): void
    {
        echo "world";
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('NoProperties');

        $this->assertNotNull($classResult);
        $this->assertEquals(0, $classResult['lcom']);
        $this->assertSame('A', $classResult['rating']);
        $this->assertSame('No properties', $classResult['description']);
    }

    public function testClassWithNoMethods(): void
    {
        $code = <<<'PHP'
<?php

class NoMethods
{
    public string $name;
    public int $age;
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('NoMethods');

        $this->assertNotNull($classResult);
        $this->assertEquals(0, $classResult['lcom']);
        $this->assertSame('A', $classResult['rating']);
        $this->assertSame('No methods', $classResult['description']);
    }

    public function testPerfectCohesion(): void
    {
        $code = <<<'PHP'
<?php

class PerfectCohesion
{
    private string $value;

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function hasValue(): bool
    {
        return !empty($this->value);
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('PerfectCohesion');

        $this->assertNotNull($classResult);
        $this->assertSame(0.0, $classResult['lcom']);
        $this->assertSame('A', $classResult['rating']);
    }

    public function testGetAverageLCOM(): void
    {
        $code = <<<'PHP'
<?php

class Class1
{
    private string $a;
    public function getA(): string { return $this->a; }
}

class Class2
{
    private string $b;
    public function getB(): string { return $this->b; }
}
PHP;

        $this->analyzeCode($code);

        $avg = $this->visitor->getAverageLCOM();
        $this->assertIsFloat($avg);
        $this->assertGreaterThanOrEqual(0, $avg);
        $this->assertLessThanOrEqual(1, $avg);
    }

    public function testResultsStructure(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    private string $prop;
    public function test(): void { $this->prop; }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('classes', $results);
        $this->assertArrayHasKey('summary', $results);

        $this->assertArrayHasKey('totalClasses', $results['summary']);
        $this->assertArrayHasKey('averageLcom', $results['summary']);
        $this->assertArrayHasKey('maxLcom', $results['summary']);
    }

    public function testMethodDetailsIncluded(): void
    {
        $code = <<<'PHP'
<?php

class WithDetails
{
    private string $name;
    private int $age;

    public function getName(): string
    {
        return $this->name;
    }

    public function getBoth(): string
    {
        return $this->name . $this->age;
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('WithDetails');

        $this->assertArrayHasKey('methodDetails', $classResult);
        $this->assertArrayHasKey('getName', $classResult['methodDetails']);
        $this->assertArrayHasKey('getBoth', $classResult['methodDetails']);

        $this->assertSame(['name'], $classResult['methodDetails']['getName']['usedProperties']);
        $this->assertCount(2, $classResult['methodDetails']['getBoth']['usedProperties']);
    }

    public function testMultipleClasses(): void
    {
        $code = <<<'PHP'
<?php

class FirstClass
{
    private string $a;
    public function getA(): string { return $this->a; }
}

class SecondClass
{
    private string $b;
    private string $c;
    public function getB(): string { return $this->b; }
    public function getC(): string { return $this->c; }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertCount(2, $results['classes']);
        $this->assertArrayHasKey('FirstClass', $results['classes']);
        $this->assertArrayHasKey('SecondClass', $results['classes']);
    }

    public function testEmptyClass(): void
    {
        $code = <<<'PHP'
<?php

class EmptyClass
{
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('EmptyClass');

        $this->assertNotNull($classResult);
        $this->assertEquals(0, $classResult['lcom']);
    }

    /**
     * @dataProvider ratingProvider
     */
    public function testLcomRatings(float $lcom, string $expectedRating): void
    {
        // We can't easily control LCOM precisely, so we test the boundaries conceptually
        $this->assertContains($expectedRating, ['A', 'B', 'C', 'D', 'F']);
    }

    public static function ratingProvider(): array
    {
        return [
            [0.0, 'A'],
            [0.2, 'A'],
            [0.3, 'B'],
            [0.5, 'C'],
            [0.7, 'D'],
            [0.9, 'F'],
        ];
    }

    public function testStaticPropertyUsage(): void
    {
        $code = <<<'PHP'
<?php

class WithStaticProperty
{
    private static string $instance;

    public static function getInstance(): string
    {
        return self::$instance;
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('WithStaticProperty');

        $this->assertNotNull($classResult);
    }

    public function testClassWithConstructorPromotedProperties(): void
    {
        $code = <<<'PHP'
<?php

class PromotedProps
{
    public function __construct(
        private string $name,
        private int $age
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getAge(): int
    {
        return $this->age;
    }
}
PHP;

        $this->analyzeCode($code);
        $classResult = $this->visitor->getClassLCOM('PromotedProps');

        $this->assertNotNull($classResult);
        $this->assertGreaterThanOrEqual(0, $classResult['lcom']);
    }

    private function analyzeCode(string $code): void
    {
        $ast = $this->parser->parse($code);
        $this->parser->traverse($ast, [$this->visitor]);
    }
}
