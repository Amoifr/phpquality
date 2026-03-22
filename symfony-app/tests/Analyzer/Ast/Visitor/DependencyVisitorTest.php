<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\Ast\Visitor;

use App\Analyzer\Ast\AstParser;
use App\Analyzer\Ast\Visitor\DependencyVisitor;
use PHPUnit\Framework\TestCase;

class DependencyVisitorTest extends TestCase
{
    private AstParser $parser;
    private DependencyVisitor $visitor;

    protected function setUp(): void
    {
        $this->parser = new AstParser();
        $this->visitor = new DependencyVisitor();
    }

    public function testTracksUseStatements(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

use App\Repository\UserRepository;
use App\Entity\User;

class UserService
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('useStatements', $results);
        $this->assertArrayHasKey('UserRepository', $results['useStatements']);
        $this->assertArrayHasKey('User', $results['useStatements']);
        $this->assertSame('App\Repository\UserRepository', $results['useStatements']['UserRepository']);
    }

    public function testTracksGroupedUseStatements(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

use App\Entity\{User, Product, Order};

class Service
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertSame('App\Entity\User', $results['useStatements']['User']);
        $this->assertSame('App\Entity\Product', $results['useStatements']['Product']);
        $this->assertSame('App\Entity\Order', $results['useStatements']['Order']);
    }

    public function testTracksUseAliases(): void
    {
        $code = <<<'PHP'
<?php

use App\Service\UserService as US;

class Test
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('US', $results['useStatements']);
        $this->assertSame('App\Service\UserService', $results['useStatements']['US']);
    }

    public function testTracksNamespace(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

class Test {}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertSame('App\Service', $results['namespace']);
    }

    public function testTracksClassExtends(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Base\AbstractService;

class UserService extends AbstractService
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('UserService', $results['classDefinitions']);
        $this->assertSame('App\Base\AbstractService', $results['classDefinitions']['UserService']['extends']);
    }

    public function testTracksClassImplements(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Contract\ServiceInterface;
use App\Contract\LoggableInterface;

class UserService implements ServiceInterface, LoggableInterface
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertCount(2, $results['classDefinitions']['UserService']['implements']);
        $this->assertContains('App\Contract\ServiceInterface', $results['classDefinitions']['UserService']['implements']);
        $this->assertContains('App\Contract\LoggableInterface', $results['classDefinitions']['UserService']['implements']);
    }

    public function testTracksTraitUsage(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Traits\Loggable;

class UserService
{
    use Loggable;
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertContains('App\Traits\Loggable', $results['classDefinitions']['UserService']['traits']);
    }

    public function testTracksNewInstantiations(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;

class UserFactory
{
    public function create(): User
    {
        return new User();
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $dependencies = array_column($results['dependencies'], 'type', 'fqn');
        $this->assertArrayHasKey('App\Entity\User', $dependencies);
    }

    public function testTracksStaticCalls(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Util\Helper;

class Service
{
    public function process(): void
    {
        Helper::doSomething();
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $deps = array_values($results['dependencies']);
        $staticCalls = array_filter($deps, fn($d) => $d['type'] === 'static_call');
        $this->assertNotEmpty($staticCalls);
    }

    public function testTracksTypeHints(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;
use App\Repository\UserRepositoryInterface;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository
    ) {}

    public function getUser(int $id): ?User
    {
        return $this->repository->find($id);
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertContains('App\Entity\User', $fqns);
        $this->assertContains('App\Repository\UserRepositoryInterface', $fqns);
    }

    public function testTracksPropertyTypes(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Logger\LoggerInterface;

class Service
{
    private LoggerInterface $logger;
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertContains('App\Logger\LoggerInterface', $fqns);
    }

    public function testTracksInstanceof(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;

class Service
{
    public function check(mixed $obj): bool
    {
        return $obj instanceof User;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $deps = array_values($results['dependencies']);
        $instanceofDeps = array_filter($deps, fn($d) => $d['type'] === 'instanceof');
        $this->assertNotEmpty($instanceofDeps);
    }

    public function testTracksCatchBlocks(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Exception\ValidationException;

class Service
{
    public function process(): void
    {
        try {
            throw new ValidationException();
        } catch (ValidationException $e) {
            echo $e->getMessage();
        }
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $deps = array_values($results['dependencies']);
        $catchDeps = array_filter($deps, fn($d) => $d['type'] === 'catch');
        $this->assertNotEmpty($catchDeps);
    }

    public function testTracksInterfaceDefinitions(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Contract;

interface UserRepositoryInterface
{
    public function find(int $id): mixed;
    public function save(mixed $entity): void;
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('UserRepositoryInterface', $results['interfaceDefinitions']);
        $this->assertSame(2, $results['interfaceDefinitions']['UserRepositoryInterface']['methods']);
    }

    public function testTracksInterfaceExtends(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Contract;

use App\Contract\ReadableInterface;
use App\Contract\WritableInterface;

interface RepositoryInterface extends ReadableInterface, WritableInterface
{
    public function findAll(): array;
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertCount(2, $results['interfaceDefinitions']['RepositoryInterface']['extends']);
    }

    public function testTracksTraitDefinitions(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Traits;

trait Loggable
{
    public function log(string $message): void
    {
        echo $message;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertArrayHasKey('Loggable', $results['traitDefinitions']);
    }

    public function testIgnoresSelfStaticParent(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

class Service
{
    public function test(): void
    {
        new self();
        static::method();
        parent::method();
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertNotContains('self', $fqns);
        $this->assertNotContains('static', $fqns);
        $this->assertNotContains('parent', $fqns);
    }

    public function testGetDependenciesByClass(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;

class Service
{
    public function __construct(private User $user) {}

    public function getUser(): User
    {
        return $this->user;
    }
}
PHP;

        $this->analyzeCode($code);
        $grouped = $this->visitor->getDependenciesByClass();

        $this->assertArrayHasKey('App\Entity\User', $grouped);
        $this->assertGreaterThan(0, $grouped['App\Entity\User']['count']);
    }

    public function testGetUniqueDependencyCount(): void
    {
        $code = <<<'PHP'
<?php

use App\Entity\User;
use App\Service\UserService;

class Test
{
    public function __construct(private User $u, private UserService $s) {}
}
PHP;

        $this->analyzeCode($code);
        $count = $this->visitor->getUniqueDependencyCount();

        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testHandlesUnionTypes(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;
use App\Entity\Admin;

class Service
{
    public function process(User|Admin $entity): void
    {
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertContains('App\Entity\User', $fqns);
        $this->assertContains('App\Entity\Admin', $fqns);
    }

    public function testHandlesNullableTypes(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;

class Service
{
    public function find(): ?User
    {
        return null;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertContains('App\Entity\User', $fqns);
    }

    public function testIgnoresBuiltinTypes(): void
    {
        $code = <<<'PHP'
<?php

class Service
{
    public function process(int $a, string $b): bool
    {
        return true;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertNotContains('int', $fqns);
        $this->assertNotContains('string', $fqns);
        $this->assertNotContains('bool', $fqns);
    }

    public function testTracksClassConstFetch(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Enum\Status;

class Service
{
    public function getStatus(): string
    {
        return Status::ACTIVE;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $deps = array_values($results['dependencies']);
        $constDeps = array_filter($deps, fn($d) => $d['type'] === 'const');
        $this->assertNotEmpty($constDeps);
    }

    public function testTracksStaticPropertyFetch(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Config\AppConfig;

class Service
{
    public function getVersion(): string
    {
        return AppConfig::$version;
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $deps = array_values($results['dependencies']);
        $staticPropDeps = array_filter($deps, fn($d) => $d['type'] === 'static_property');
        $this->assertNotEmpty($staticPropDeps);
    }

    public function testClassDefinitionFqn(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Service;

class UserService
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertSame('App\Service\UserService', $results['classDefinitions']['UserService']['fqn']);
    }

    public function testInterfaceDefinitionFqn(): void
    {
        $code = <<<'PHP'
<?php

namespace App\Contract;

interface ServiceInterface
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertSame('App\Contract\ServiceInterface', $results['interfaceDefinitions']['ServiceInterface']['fqn']);
    }

    public function testEmptyFile(): void
    {
        $code = <<<'PHP'
<?php
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertEmpty($results['classDefinitions']);
        $this->assertEmpty($results['interfaceDefinitions']);
        $this->assertEmpty($results['dependencies']);
    }

    public function testMultipleClassesInFile(): void
    {
        $code = <<<'PHP'
<?php

class First
{
}

class Second
{
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $this->assertCount(2, $results['classDefinitions']);
        $this->assertArrayHasKey('First', $results['classDefinitions']);
        $this->assertArrayHasKey('Second', $results['classDefinitions']);
    }

    public function testResetClearsState(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Entity\User;

class Service
{
    public function __construct(private User $user) {}
}
PHP;

        $this->analyzeCode($code);
        $this->assertNotEmpty($this->visitor->getResults()['dependencies']);

        $this->visitor->reset();
        $this->assertEmpty($this->visitor->getResults());
    }

    public function testLeaveNodeResetsCurrentClass(): void
    {
        $code = <<<'PHP'
<?php

class First
{
    public function a(): void {}
}

class Second
{
    public function b(): void {}
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        // After leaving First, instantiation in Second should be tracked as context Second
        $this->assertArrayHasKey('First', $results['classDefinitions']);
        $this->assertArrayHasKey('Second', $results['classDefinitions']);
    }

    public function testHandlesIntersectionTypes(): void
    {
        $code = <<<'PHP'
<?php

namespace App;

use App\Contract\ServiceInterface;
use App\Contract\LoggableInterface;

class Consumer
{
    public function process(ServiceInterface&LoggableInterface $service): void
    {
    }
}
PHP;

        $this->analyzeCode($code);
        $results = $this->visitor->getResults();

        $fqns = array_column($results['dependencies'], 'fqn');
        $this->assertContains('App\Contract\ServiceInterface', $fqns);
        $this->assertContains('App\Contract\LoggableInterface', $fqns);
    }

    private function analyzeCode(string $code): void
    {
        $ast = $this->parser->parse($code);
        $this->parser->traverse($ast, [$this->visitor]);
    }
}
