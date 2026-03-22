<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Ast;

use App\Analyzer\Ast\AstParser;
use PhpParser\NodeVisitor;
use PHPUnit\Framework\TestCase;

class AstParserTest extends TestCase
{
    private AstParser $parser;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->parser = new AstParser();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_ast_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testParseValidCode(): void
    {
        $code = '<?php class Foo { public function bar(): void {} }';

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseEmptyCode(): void
    {
        $code = '<?php';

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
    }

    public function testParseInvalidCodeThrowsException(): void
    {
        $code = '<?php class { invalid }';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parse error');

        $this->parser->parse($code);
    }

    public function testParseClassWithMethods(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Test;

class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function subtract(int $a, int $b): int
    {
        return $a - $b;
    }
}
PHP;

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseFileValid(): void
    {
        $filePath = $this->fixturesPath . '/TestClass.php';
        $code = '<?php class TestClass { public function test(): void {} }';
        file_put_contents($filePath, $code);

        $ast = $this->parser->parseFile($filePath);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseFileNonExistent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read file');

        $this->parser->parseFile('/nonexistent/path/file.php');
    }

    public function testParseFileUnreadable(): void
    {
        // Skip test when running as root (e.g., in Docker)
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test file permissions as root');
        }

        $filePath = $this->fixturesPath . '/unreadable.php';
        file_put_contents($filePath, '<?php class Test {}');
        chmod($filePath, 0000);

        $this->expectException(\RuntimeException::class);

        try {
            $this->parser->parseFile($filePath);
        } finally {
            chmod($filePath, 0644);
        }
    }

    public function testParseFileWithSyntaxError(): void
    {
        $filePath = $this->fixturesPath . '/SyntaxError.php';
        file_put_contents($filePath, '<?php class { broken }');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parse error');

        $this->parser->parseFile($filePath);
    }

    public function testTraverseWithVisitors(): void
    {
        $code = '<?php class Foo { public function bar(): void {} }';
        $ast = $this->parser->parse($code);

        $visitor = $this->createMock(NodeVisitor::class);
        $visitor->expects($this->atLeastOnce())
            ->method('enterNode');

        $this->parser->traverse($ast, [$visitor]);
    }

    public function testTraverseWithMultipleVisitors(): void
    {
        $code = '<?php class Foo { public function bar(): void {} }';
        $ast = $this->parser->parse($code);

        $visitor1 = $this->createMock(NodeVisitor::class);
        $visitor1->expects($this->atLeastOnce())->method('enterNode');

        $visitor2 = $this->createMock(NodeVisitor::class);
        $visitor2->expects($this->atLeastOnce())->method('enterNode');

        $this->parser->traverse($ast, [$visitor1, $visitor2]);
    }

    public function testTraverseEmptyAst(): void
    {
        $visitor = $this->createMock(NodeVisitor::class);

        // Should not throw, just do nothing
        $this->parser->traverse([], [$visitor]);

        $this->assertTrue(true);
    }

    public function testParseInterface(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Contract;

interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function save(User $user): void;
}
PHP;

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseTrait(): void
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

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseEnum(): void
    {
        $code = <<<'PHP'
<?php

enum Status: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
}
PHP;

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseComplexClass(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {}

    public function getUser(int $id): ?User
    {
        return $this->repository->find($id);
    }

    public function createUser(string $name, string $email): User
    {
        $user = new User($name, $email);
        $this->repository->save($user);
        return $user;
    }
}
PHP;

        $ast = $this->parser->parse($code);

        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
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
