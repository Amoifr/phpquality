<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Ast\Visitor;

use App\Analyzer\Ast\AstParser;
use App\Analyzer\Ast\Visitor\LinesOfCodeVisitor;
use PHPUnit\Framework\TestCase;

class LinesOfCodeVisitorTest extends TestCase
{
    private AstParser $parser;
    private LinesOfCodeVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = new AstParser();
        $this->visitor = new LinesOfCodeVisitor();
    }

    public function testBasicLOCCounting(): void
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

        $this->assertGreaterThan(0, $this->visitor->getLOC());
    }

    public function testCountsCommentLines(): void
    {
        $code = <<<'PHP'
<?php

// This is a comment
class Calculator
{
    /**
     * Add two numbers
     * @param int $a First number
     * @param int $b Second number
     */
    public function add(int $a, int $b): int
    {
        return $a + $b; // inline comment
    }
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThan(0, $this->visitor->getCLOC());
    }

    public function testCountsLogicalLines(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function process(): void
    {
        $a = 1;
        $b = 2;
        $c = $a + $b;
        echo $c;
        return;
    }
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThan(0, $this->visitor->getLLOC());
    }

    public function testCountsIfStatements(): void
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

        $this->assertGreaterThanOrEqual(2, $this->visitor->getLLOC());
    }

    public function testCountsLoops(): void
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

        foreach ([1, 2, 3] as $item) {
            echo $item;
        }

        while (false) {
            break;
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThanOrEqual(3, $this->visitor->getLLOC());
    }

    public function testGetResultsStructure(): void
    {
        $code = <<<'PHP'
<?php

// Comment
class Test
{
    public function test(): void
    {
        echo "hello";
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertIsArray($results);
        $this->assertArrayHasKey('loc', $results);
        $this->assertArrayHasKey('cloc', $results);
        $this->assertArrayHasKey('lloc', $results);
        $this->assertArrayHasKey('blankLines', $results);
        $this->assertArrayHasKey('codeLines', $results);
        $this->assertArrayHasKey('commentRatio', $results);
    }

    public function testBlankLinesCount(): void
    {
        $code = <<<'PHP'
<?php

class Test
{

    public function test(): void
    {

        echo "hello";

    }

}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertGreaterThan(0, $results['blankLines']);
    }

    public function testCommentRatio(): void
    {
        $code = <<<'PHP'
<?php
// Comment 1
// Comment 2
// Comment 3
// Comment 4
// Comment 5
class Test {}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertGreaterThan(0, $results['commentRatio']);
    }

    public function testMultilineComments(): void
    {
        $code = <<<'PHP'
<?php

/*
 * This is a multiline comment
 * that spans several lines
 * and should all be counted
 */
class Test
{
    public function test(): void {}
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThanOrEqual(4, $this->visitor->getCLOC());
    }

    public function testHashComments(): void
    {
        $code = <<<'PHP'
<?php

# This is a hash comment
class Test
{
    public function test(): void {}
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThanOrEqual(1, $this->visitor->getCLOC());
    }

    public function testTryCatchCounting(): void
    {
        $code = <<<'PHP'
<?php

class Test
{
    public function process(): void
    {
        try {
            throw new Exception("error");
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThanOrEqual(2, $this->visitor->getLLOC());
    }

    public function testSwitchCaseCounting(): void
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
            default:
                return "unknown";
        }
    }
}
PHP;

        $this->analyzeCode($code);

        $this->assertGreaterThanOrEqual(4, $this->visitor->getLLOC());
    }

    public function testEmptyFile(): void
    {
        $code = '<?php';

        $this->analyzeCode($code);

        $this->assertSame(1, $this->visitor->getLOC());
        $this->assertSame(0, $this->visitor->getLLOC());
    }

    private function analyzeCode(string $code): void
    {
        $this->visitor->setSourceCode($code);
        $ast = $this->parser->parse($code);
        $this->parser->traverse($ast, [$this->visitor]);
    }
}
