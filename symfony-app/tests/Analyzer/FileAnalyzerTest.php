<?php

declare(strict_types=1);

namespace App\Tests\Analyzer;

use App\Analyzer\Ast\AstParser;
use App\Analyzer\FileAnalyzer;
use App\Analyzer\Metric\MaintainabilityIndex;
use App\Analyzer\ProjectType\ProjectTypeInterface;
use App\Analyzer\Result\FileResult;
use PHPUnit\Framework\TestCase;

class FileAnalyzerTest extends TestCase
{
    private FileAnalyzer $analyzer;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $parser = new AstParser();
        $miCalculator = new MaintainabilityIndex();
        $this->analyzer = new FileAnalyzer($parser, $miCalculator);
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_fileanalyzer_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testAnalyzeSimpleClass(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/Calculator.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertInstanceOf(FileResult::class, $result);
        $this->assertSame($filePath, $result->path);
        $this->assertSame('Calculator.php', $result->relativePath);
        $this->assertFalse($result->hasErrors);
        $this->assertNull($result->error);
    }

    public function testAnalyzeReturnsClassResults(): void
    {
        $code = <<<'PHP'
<?php

class SimpleClass
{
    private string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/SimpleClass.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertNotEmpty($result->classes);
        $this->assertCount(1, $result->classes);
        $this->assertSame('SimpleClass', $result->classes[0]->name);
    }

    public function testAnalyzeCalculatesLoc(): void
    {
        $code = <<<'PHP'
<?php

class TestClass
{
    public function method(): void
    {
        $a = 1;
        $b = 2;
        $c = 3;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/TestClass.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertArrayHasKey('loc', $result->loc);
        $this->assertGreaterThan(0, $result->loc['loc']);
    }

    public function testAnalyzeCalculatesCcn(): void
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
        $filePath = $this->fixturesPath . '/Control.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertArrayHasKey('methods', $result->ccn);
        $this->assertNotEmpty($result->ccn['methods']);
    }

    public function testAnalyzeCalculatesHalstead(): void
    {
        $code = <<<'PHP'
<?php

class Math
{
    public function calculate(int $a, int $b): int
    {
        return ($a + $b) * ($a - $b);
    }
}
PHP;
        $filePath = $this->fixturesPath . '/Math.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertArrayHasKey('volume', $result->halstead);
        $this->assertArrayHasKey('difficulty', $result->halstead);
        $this->assertArrayHasKey('effort', $result->halstead);
    }

    public function testAnalyzeCalculatesLcom(): void
    {
        $code = <<<'PHP'
<?php

class Cohesive
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
}
PHP;
        $filePath = $this->fixturesPath . '/Cohesive.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertArrayHasKey('classes', $result->lcom);
        $this->assertArrayHasKey('Cohesive', $result->lcom['classes']);
    }

    public function testAnalyzeCalculatesMi(): void
    {
        $code = <<<'PHP'
<?php

class Simple
{
    public function test(): void
    {
        echo "hello";
    }
}
PHP;
        $filePath = $this->fixturesPath . '/Simple.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertIsFloat($result->mi);
        $this->assertGreaterThanOrEqual(0, $result->mi);
        $this->assertContains($result->miRating, ['A', 'B', 'C', 'D', 'F']);
    }

    public function testAnalyzeHandlesSyntaxError(): void
    {
        $code = '<?php class { invalid }';
        $filePath = $this->fixturesPath . '/Invalid.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertTrue($result->hasErrors);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('Parse error', $result->error);
    }

    public function testAnalyzeHandlesNonExistentFile(): void
    {
        $filePath = $this->fixturesPath . '/nonexistent.php';

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertTrue($result->hasErrors);
        $this->assertNotNull($result->error);
    }

    public function testAnalyzeMultipleClasses(): void
    {
        $code = <<<'PHP'
<?php

class FirstClass
{
    public function first(): void {}
}

class SecondClass
{
    public function second(): void {}
}
PHP;
        $filePath = $this->fixturesPath . '/Multiple.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertCount(2, $result->classes);
    }

    public function testAnalyzeWithMethodResults(): void
    {
        $code = <<<'PHP'
<?php

class WithMethods
{
    private int $count;

    public function increment(): void
    {
        $this->count++;
    }

    public function process(int $value): int
    {
        if ($value > 0) {
            return $value * 2;
        }
        return 0;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/WithMethods.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertCount(1, $result->classes);
        $class = $result->classes[0];
        $this->assertNotEmpty($class->methods);
    }

    public function testAnalyzeWithProjectType(): void
    {
        $code = <<<'PHP'
<?php

class UserController
{
    public function index(): void {}
}
PHP;
        $filePath = $this->fixturesPath . '/UserController.php';
        file_put_contents($filePath, $code);

        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getClassCategories')->willReturn([
            'Controller$' => 'Controller',
        ]);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath, $projectType);

        $this->assertCount(1, $result->classes);
        $this->assertSame('Controller', $result->classes[0]->category);
    }

    public function testAnalyzeWithoutProjectType(): void
    {
        $code = <<<'PHP'
<?php

class UserController
{
    public function index(): void {}
}
PHP;
        $filePath = $this->fixturesPath . '/UserController.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertCount(1, $result->classes);
        $this->assertNull($result->classes[0]->category);
    }

    public function testAnalyzeCalculatesDependencies(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private UserRepository $repository,
        private LoggerInterface $logger
    ) {}
}
PHP;
        $filePath = $this->fixturesPath . '/UserService.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertIsArray($result->dependencies);
    }

    public function testAnalyzeEmptyClass(): void
    {
        $code = <<<'PHP'
<?php

class EmptyClass
{
}
PHP;
        $filePath = $this->fixturesPath . '/EmptyClass.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertFalse($result->hasErrors);
        $this->assertCount(1, $result->classes);
    }

    public function testAnalyzeInterface(): void
    {
        $code = <<<'PHP'
<?php

interface TestInterface
{
    public function test(): void;
}
PHP;
        $filePath = $this->fixturesPath . '/TestInterface.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertFalse($result->hasErrors);
    }

    public function testAnalyzeTrait(): void
    {
        $code = <<<'PHP'
<?php

trait Loggable
{
    public function log(string $message): void
    {
        echo $message;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/Loggable.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertFalse($result->hasErrors);
    }

    public function testAnalyzeComplexClass(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

class ComplexService
{
    private array $data = [];
    private int $count = 0;

    public function process(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if ($this->isValid($item)) {
                $result[] = $this->transform($item);
                $this->count++;
            }
        }
        $this->data = $result;
        return $result;
    }

    private function isValid(mixed $item): bool
    {
        return $item !== null && $item !== '';
    }

    private function transform(mixed $item): string
    {
        return (string) $item;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/ComplexService.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertFalse($result->hasErrors);
        $this->assertCount(1, $result->classes);
        $class = $result->classes[0];
        $this->assertSame('ComplexService', $class->name);
        $this->assertGreaterThan(0, $class->methodCount);
        $this->assertGreaterThan(0, $class->propertyCount);
    }

    public function testAnalyzeClassRatings(): void
    {
        $code = <<<'PHP'
<?php

class RatedClass
{
    private string $value;

    public function getValue(): string
    {
        return $this->value;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/RatedClass.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $class = $result->classes[0];
        $this->assertContains($class->lcomRating, ['A', 'B', 'C', 'D', 'F']);
        $this->assertContains($class->miRating, ['A', 'B', 'C', 'D', 'F']);
    }

    public function testAnalyzeRelativePath(): void
    {
        $subdir = $this->fixturesPath . '/src/Service';
        mkdir($subdir, 0755, true);

        $code = '<?php class Test {}';
        $filePath = $subdir . '/Test.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertSame('src/Service/Test.php', $result->relativePath);
    }

    public function testAnalyzeHighCcnMethod(): void
    {
        $code = <<<'PHP'
<?php

class HighComplexity
{
    public function complex(int $a, int $b, int $c): int
    {
        if ($a > 0) {
            if ($b > 0) {
                if ($c > 0) {
                    return $a + $b + $c;
                } elseif ($c < 0) {
                    return $a + $b - $c;
                }
            } elseif ($b < 0) {
                return $a - $b;
            }
        } elseif ($a < 0) {
            return -$a;
        }
        return 0;
    }
}
PHP;
        $filePath = $this->fixturesPath . '/HighComplexity.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertFalse($result->hasErrors);
        $class = $result->classes[0];
        $this->assertGreaterThan(1, $class->maxCcn);
    }

    public function testAnalyzeLowCohesionClass(): void
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
        $filePath = $this->fixturesPath . '/LowCohesion.php';
        file_put_contents($filePath, $code);

        $result = $this->analyzer->analyze($filePath, $this->fixturesPath);

        $this->assertFalse($result->hasErrors);
        $class = $result->classes[0];
        // Low cohesion should have higher LCOM value
        $this->assertGreaterThan(0.5, $class->lcom);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
