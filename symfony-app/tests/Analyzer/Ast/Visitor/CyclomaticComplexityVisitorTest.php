<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Ast\Visitor;

use App\Analyzer\Ast\AstParser;
use App\Analyzer\Ast\Visitor\CyclomaticComplexityVisitor;
use PHPUnit\Framework\TestCase;

class CyclomaticComplexityVisitorTest extends TestCase
{
    private AstParser $parser;
    private CyclomaticComplexityVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = new AstParser();
        $this->visitor = new CyclomaticComplexityVisitor();
    }

    public function testSimpleMethodHasComplexityOne(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function simple(): void
    {
        echo "hello";
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertCount(1, $methods);
        $this->assertSame(1, $methods[0]['ccn']);
        $this->assertSame('A', $methods[0]['rating']);
    }

    public function testIfIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function check(int $value): string
    {
        if ($value > 0) {
            return "positive";
        }
        return "negative";
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testIfElseIfIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function check(int $value): string
    {
        if ($value > 0) {
            return "positive";
        } elseif ($value < 0) {
            return "negative";
        } else {
            return "zero";
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(3, $methods[0]['ccn']);
    }

    public function testForLoopIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function loop(): void
    {
        for ($i = 0; $i < 10; $i++) {
            echo $i;
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testForeachIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function loop(array $items): void
    {
        foreach ($items as $item) {
            echo $item;
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testWhileIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function loop(): void
    {
        $i = 0;
        while ($i < 10) {
            echo $i++;
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testDoWhileIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function loop(): void
    {
        $i = 0;
        do {
            echo $i++;
        } while ($i < 10);
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testCatchIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function process(): void
    {
        try {
            throw new Exception();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testTernaryIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function check(int $value): string
    {
        return $value > 0 ? "positive" : "negative";
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testNullCoalesceIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function getValue(?string $value): string
    {
        return $value ?? "default";
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testBooleanAndIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function check(int $a, int $b): bool
    {
        return $a > 0 && $b > 0;
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testBooleanOrIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function check(int $a, int $b): bool
    {
        return $a > 0 || $b > 0;
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(2, $methods[0]['ccn']);
    }

    public function testSwitchCaseIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function getStatus(int $code): string
    {
        switch ($code) {
            case 1:
                return "one";
            case 2:
                return "two";
            case 3:
                return "three";
            default:
                return "unknown";
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame(4, $methods[0]['ccn']); // 1 + 3 cases
    }

    public function testComplexMethodHighComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function complex(int $a, int $b, int $c): string
    {
        if ($a > 0) {
            if ($b > 0) {
                if ($c > 0) {
                    return "all positive";
                } else {
                    return "c negative";
                }
            } elseif ($b < 0) {
                return "b negative";
            }
        }

        for ($i = 0; $i < 10; $i++) {
            if ($i % 2 == 0) {
                continue;
            }
        }

        return $a > 0 && $b > 0 ? "both" : "none";
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertGreaterThan(5, $methods[0]['ccn']);
    }

    public function testGetAverageCcn(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function simple(): void {}

    public function withIf(): void
    {
        if (true) {}
    }
}
PHP;

        $this->analyzeCode($code);

        $avg = $this->visitor->getAverageCcn();
        $this->assertSame(1.5, $avg);
    }

    public function testGetMaxCcn(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function simple(): void {}

    public function complex(): void
    {
        if (true) {}
        if (true) {}
        if (true) {}
    }
}
PHP;

        $this->analyzeCode($code);

        $max = $this->visitor->getMaxCcn();
        $this->assertSame(4, $max);
    }

    public function testMultipleClasses(): void
    {
        $code = <<<'PHP'
<?php

class ClassA
{
    public function methodA(): void {}
}

class ClassB
{
    public function methodB(): void
    {
        if (true) {}
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertCount(2, $methods);
    }

    public function testResultsStructure(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): void {}
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('methods', $results);
        $this->assertArrayHasKey('classes', $results);
        $this->assertArrayHasKey('summary', $results);

        $this->assertArrayHasKey('totalMethods', $results['summary']);
        $this->assertArrayHasKey('averageCcn', $results['summary']);
        $this->assertArrayHasKey('maxCcn', $results['summary']);
        $this->assertArrayHasKey('totalCcn', $results['summary']);
    }

    /**
     * @dataProvider ratingProvider
     */
    public function testRatingThresholds(int $expectedCcn, string $expectedRating): void
    {
        // Build code with exact CCN
        $conditions = str_repeat("        if (true) {}\n", $expectedCcn - 1);
        $code = "<?php\nclass Test {\n    public function test(): void {\n$conditions    }\n}";

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertSame($expectedCcn, $methods[0]['ccn']);
        $this->assertSame($expectedRating, $methods[0]['rating']);
    }

    public static function ratingProvider(): array
    {
        return [
            'A rating (1)' => [1, 'A'],
            'A rating (4)' => [4, 'A'],
            'B rating (5)' => [5, 'B'],
            'B rating (7)' => [7, 'B'],
            'C rating (8)' => [8, 'C'],
            'C rating (10)' => [10, 'C'],
            'D rating (11)' => [11, 'D'],
            'D rating (15)' => [15, 'D'],
            'F rating (16)' => [16, 'F'],
        ];
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

        $methods = $this->visitor->getMethodComplexities();
        $this->assertEmpty($methods);
        $this->assertSame(0.0, $this->visitor->getAverageCcn());
        $this->assertSame(0, $this->visitor->getMaxCcn());
    }

    public function testResetClearsState(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function method(): void
    {
        if (true) {}
    }
}
PHP;

        $this->analyzeCode($code);
        $this->assertNotEmpty($this->visitor->getMethodComplexities());

        // Reset and analyze again
        $this->visitor->reset();

        // After reset, results should be empty until new traversal
        $this->assertEmpty($this->visitor->getResults());
    }

    public function testMatchExpressionIncreasesComplexity(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function getLabel(int $status): string
    {
        return match($status) {
            1 => "pending",
            2 => "active",
            3 => "closed",
            default => "unknown",
        };
    }
}
PHP;

        $this->analyzeCode($code);

        $methods = $this->visitor->getMethodComplexities();
        $this->assertGreaterThan(1, $methods[0]['ccn']);
    }

    public function testClassResultsIncluded(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    public function method1(): void {}
    public function method2(): void { if (true) {} }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('classes', $results);
        $this->assertArrayHasKey('TestClass', $results['classes']);
        $this->assertArrayHasKey('methods', $results['classes']['TestClass']);
        $this->assertCount(2, $results['classes']['TestClass']['methods']);
        $this->assertArrayHasKey('totalCcn', $results['classes']['TestClass']);
        $this->assertArrayHasKey('maxCcn', $results['classes']['TestClass']);
    }

    private function analyzeCode(string $code): void
    {
        $ast = $this->parser->parse($code);
        $this->parser->traverse($ast, [$this->visitor]);
    }
}
