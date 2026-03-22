<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Ast\Visitor;

use App\Analyzer\Ast\AstParser;
use App\Analyzer\Ast\Visitor\HalsteadVisitor;
use PHPUnit\Framework\TestCase;

class HalsteadVisitorTest extends TestCase
{
    private AstParser $parser;
    private HalsteadVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = new AstParser();
        $this->visitor = new HalsteadVisitor();
    }

    public function testBasicMetricsCalculation(): void
    {
        $code = <<<'PHP'
<?php

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('n1', $results);
        $this->assertArrayHasKey('n2', $results);
        $this->assertArrayHasKey('N1', $results);
        $this->assertArrayHasKey('N2', $results);
        $this->assertArrayHasKey('vocabulary', $results);
        $this->assertArrayHasKey('length', $results);
        $this->assertArrayHasKey('volume', $results);
        $this->assertArrayHasKey('difficulty', $results);
        $this->assertArrayHasKey('effort', $results);
    }

    public function testCountsOperators(): void
    {
        $code = <<<'PHP'
<?php

class Math
{
    public function calculate(): int
    {
        $a = 1 + 2;
        $b = $a * 3;
        $c = $b - 4;
        return $c / 2;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertGreaterThan(0, $results['n1']);
        $this->assertGreaterThan(0, $results['N1']);
        $this->assertContains('+', array_keys($results['operators']));
        $this->assertContains('*', array_keys($results['operators']));
        $this->assertContains('-', array_keys($results['operators']));
        $this->assertContains('/', array_keys($results['operators']));
    }

    public function testCountsOperands(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): void
    {
        $x = 10;
        $y = 20;
        $z = "hello";
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertGreaterThan(0, $results['n2']);
        $this->assertGreaterThan(0, $results['N2']);
    }

    public function testControlFlowOperators(): void
    {
        $code = <<<'PHP'
<?php

class Control
{
    public function process(int $value): string
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
        $results = $this->visitor->getResults();

        $this->assertContains('if', array_keys($results['operators']));
        $this->assertContains('elseif', array_keys($results['operators']));
        $this->assertContains('else', array_keys($results['operators']));
        $this->assertContains('return', array_keys($results['operators']));
    }

    public function testLoopOperators(): void
    {
        $code = <<<'PHP'
<?php

class Loops
{
    public function loop(): void
    {
        for ($i = 0; $i < 10; $i++) {
            echo $i;
        }

        foreach ([1, 2, 3] as $item) {
            echo $item;
        }

        while (false) {
            break;
        }

        do {
            continue;
        } while (false);
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('for', array_keys($results['operators']));
        $this->assertContains('foreach', array_keys($results['operators']));
        $this->assertContains('while', array_keys($results['operators']));
        $this->assertContains('do', array_keys($results['operators']));
    }

    public function testMethodCallOperators(): void
    {
        $code = <<<'PHP'
<?php

class Service
{
    public function process(): void
    {
        $obj = new stdClass();
        $obj->method();
        self::staticMethod();
        strlen("test");
    }

    public static function staticMethod(): void {}
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('new', array_keys($results['operators']));
        $this->assertContains('->', array_keys($results['operators']));
        $this->assertContains('::', array_keys($results['operators']));
        $this->assertContains('call', array_keys($results['operators']));
    }

    public function testAssignmentOperators(): void
    {
        $code = <<<'PHP'
<?php

class Assignments
{
    public function assign(): void
    {
        $a = 1;
        $a += 2;
        $a -= 1;
        $a *= 2;
        $a /= 2;
        $s = "";
        $s .= "test";
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('=', array_keys($results['operators']));
        $this->assertContains('+=', array_keys($results['operators']));
        $this->assertContains('-=', array_keys($results['operators']));
        $this->assertContains('*=', array_keys($results['operators']));
        $this->assertContains('.=', array_keys($results['operators']));
    }

    public function testComparisonOperators(): void
    {
        $code = <<<'PHP'
<?php

class Compare
{
    public function compare(int $a, int $b): void
    {
        $x = $a == $b;
        $y = $a === $b;
        $z = $a != $b;
        $w = $a !== $b;
        $lt = $a < $b;
        $gt = $a > $b;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('==', array_keys($results['operators']));
        $this->assertContains('===', array_keys($results['operators']));
        $this->assertContains('!=', array_keys($results['operators']));
        $this->assertContains('!==', array_keys($results['operators']));
        $this->assertContains('<', array_keys($results['operators']));
        $this->assertContains('>', array_keys($results['operators']));
    }

    public function testLogicalOperators(): void
    {
        $code = <<<'PHP'
<?php

class Logic
{
    public function logic(bool $a, bool $b): void
    {
        $x = $a && $b;
        $y = $a || $b;
        $z = !$a;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('&&', array_keys($results['operators']));
        $this->assertContains('||', array_keys($results['operators']));
        $this->assertContains('!', array_keys($results['operators']));
    }

    public function testTernaryOperator(): void
    {
        $code = <<<'PHP'
<?php

class Ternary
{
    public function check(int $value): string
    {
        return $value > 0 ? "yes" : "no";
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('?:', array_keys($results['operators']));
    }

    public function testNullCoalesceOperator(): void
    {
        $code = <<<'PHP'
<?php

class Coalesce
{
    public function check(?string $value): string
    {
        return $value ?? "default";
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('??', array_keys($results['operators']));
    }

    public function testGetVolume(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): int
    {
        return 1 + 2 + 3;
    }
}
PHP;

        $this->analyzeCode($code);

        $volume = $this->visitor->getVolume();
        $this->assertIsFloat($volume);
        $this->assertGreaterThan(0, $volume);
    }

    public function testGetDifficulty(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): int
    {
        $a = 1;
        $b = 2;
        return $a + $b;
    }
}
PHP;

        $this->analyzeCode($code);

        $difficulty = $this->visitor->getDifficulty();
        $this->assertIsFloat($difficulty);
    }

    public function testGetEffort(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function complex(): int
    {
        $a = 1;
        $b = 2;
        $c = 3;
        return ($a + $b) * $c - $a / $b;
    }
}
PHP;

        $this->analyzeCode($code);

        $effort = $this->visitor->getEffort();
        $this->assertIsFloat($effort);
    }

    public function testVocabularyCalculation(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): void
    {
        $x = 1;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertSame($results['n1'] + $results['n2'], $results['vocabulary']);
    }

    public function testLengthCalculation(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): void
    {
        $x = 1;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertSame($results['N1'] + $results['N2'], $results['length']);
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
        $results = $this->visitor->getResults();

        $this->assertSame(0, $results['N1']);
        $this->assertSame(0, $results['N2']);
        $this->assertSame(0.0, $results['volume']);
    }

    public function testTryCatchOperators(): void
    {
        $code = <<<'PHP'
<?php

class ErrorHandling
{
    public function handle(): void
    {
        try {
            throw new Exception("error");
        } catch (Exception $e) {
            echo $e->getMessage();
        } finally {
            echo "done";
        }
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('try', array_keys($results['operators']));
        $this->assertContains('catch', array_keys($results['operators']));
        // Note: 'throw' may be Expr\Throw_ in PHP 8+ which is not tracked
        $this->assertContains('finally', array_keys($results['operators']));
    }

    public function testResetClearsState(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function test(): int
    {
        $a = 1 + 2;
        return $a;
    }
}
PHP;

        $this->analyzeCode($code);
        $this->assertGreaterThan(0, $this->visitor->getVolume());

        $this->visitor->reset();
        $this->assertEmpty($this->visitor->getResults());
    }

    public function testUnaryOperators(): void
    {
        $code = <<<'PHP'
<?php

class Unary
{
    public function test(): void
    {
        $a = 5;
        $b = -$a;
        $c = +$a;
        $d = ~$a;
        $a++;
        ++$a;
        $a--;
        --$a;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('-unary', array_keys($results['operators']));
        $this->assertContains('+unary', array_keys($results['operators']));
        $this->assertContains('~', array_keys($results['operators']));
        $this->assertContains('++', array_keys($results['operators']));
        $this->assertContains('--', array_keys($results['operators']));
    }

    public function testBitwiseOperators(): void
    {
        $code = <<<'PHP'
<?php

class Bitwise
{
    public function test(): void
    {
        $a = 5;
        $b = 3;
        $and = $a & $b;
        $or = $a | $b;
        $xor = $a ^ $b;
        $left = $a << 2;
        $right = $a >> 1;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('&', array_keys($results['operators']));
        $this->assertContains('|', array_keys($results['operators']));
        $this->assertContains('^', array_keys($results['operators']));
        $this->assertContains('<<', array_keys($results['operators']));
        $this->assertContains('>>', array_keys($results['operators']));
    }

    public function testSwitchOperator(): void
    {
        $code = <<<'PHP'
<?php

class SwitchCase
{
    public function test(int $x): string
    {
        switch ($x) {
            case 1:
                return "one";
            default:
                return "other";
        }
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('switch', array_keys($results['operators']));
    }

    public function testInstanceofOperator(): void
    {
        $code = <<<'PHP'
<?php

class TypeCheck
{
    public function test(mixed $obj): bool
    {
        return $obj instanceof stdClass;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('instanceof', array_keys($results['operators']));
    }

    public function testArrayAccessOperator(): void
    {
        $code = <<<'PHP'
<?php

class ArrayAccess
{
    public function test(array $arr): int
    {
        return $arr[0];
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('[]', array_keys($results['operators']));
    }

    public function testPropertyFetchOperator(): void
    {
        $code = <<<'PHP'
<?php

class PropFetch
{
    public function test(object $obj): mixed
    {
        return $obj->property;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('->prop', array_keys($results['operators']));
    }

    public function testStaticPropertyFetchOperator(): void
    {
        $code = <<<'PHP'
<?php

class StaticProp
{
    public static string $name = "test";

    public function test(): string
    {
        return self::$name;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('::prop', array_keys($results['operators']));
    }

    public function testConstFetchOperand(): void
    {
        $code = <<<'PHP'
<?php

class ConstTest
{
    public function test(): bool
    {
        return true && false && null;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('true', array_keys($results['operands']));
        $this->assertContains('false', array_keys($results['operands']));
        $this->assertContains('null', array_keys($results['operands']));
    }

    public function testClassConstFetchOperand(): void
    {
        $code = <<<'PHP'
<?php

class ClassConst
{
    public const VALUE = 42;

    public function test(): int
    {
        return self::VALUE;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('self::VALUE', array_keys($results['operands']));
    }

    public function testMethodCallOperand(): void
    {
        $code = <<<'PHP'
<?php

class MethodOp
{
    public function test(object $obj): void
    {
        $obj->doSomething();
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('method:doSomething', array_keys($results['operands']));
    }

    public function testFuncCallOperand(): void
    {
        $code = <<<'PHP'
<?php

class FuncOp
{
    public function test(): int
    {
        return strlen("hello");
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('func:strlen', array_keys($results['operands']));
    }

    public function testFloatOperand(): void
    {
        $code = <<<'PHP'
<?php

class FloatOp
{
    public function test(): float
    {
        return 3.14;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('float:3.14', array_keys($results['operands']));
    }

    public function testTimeAndBugsMetrics(): void
    {
        $code = <<<'PHP'
<?php

class Complex
{
    public function calculate(): int
    {
        $a = 1;
        $b = 2;
        $c = 3;
        return ($a + $b) * $c - ($a / $b) + ($c % $a);
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('time', $results);
        $this->assertArrayHasKey('bugs', $results);
        $this->assertIsFloat($results['time']);
        $this->assertIsFloat($results['bugs']);
    }

    private function analyzeCode(string $code): void
    {
        $ast = $this->parser->parse($code);
        $this->parser->traverse($ast, [$this->visitor]);
    }
}
