<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\Result;

use PhpQuality\Analyzer\Result\DependenciesResult;
use PHPUnit\Framework\TestCase;

class DependenciesResultTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $result = new DependenciesResult(
            found: true,
            name: 'my-project',
            type: 'composer',
            require: ['symfony/framework-bundle' => '^7.0'],
            requireDev: ['phpunit/phpunit' => '^11.0'],
            installed: [['name' => 'symfony/framework-bundle', 'version' => 'v7.0.0']],
            installedMap: ['symfony/framework-bundle' => 'v7.0.0'],
            outdated: [],
            autoload: ['psr-4' => ['App\\' => 'src/']],
            licensesSummary: ['MIT' => 10, 'Apache-2.0' => 5],
            phpVersion: '>=8.3',
            phpExtensions: ['ext-json', 'ext-mbstring'],
            npmDependencies: ['lodash' => '^4.0'],
            npmDevDependencies: ['webpack' => '^5.0'],
            nodeVersion: '>=18',
            supportStatus: ['symfony/framework-bundle' => 'supported']
        );

        $this->assertTrue($result->found);
        $this->assertSame('my-project', $result->name);
        $this->assertSame('composer', $result->type);
        $this->assertArrayHasKey('symfony/framework-bundle', $result->require);
        $this->assertArrayHasKey('phpunit/phpunit', $result->requireDev);
        $this->assertSame('>=8.3', $result->phpVersion);
        $this->assertContains('ext-json', $result->phpExtensions);
    }

    public function testNotFoundFactoryMethod(): void
    {
        $result = DependenciesResult::notFound();

        $this->assertFalse($result->found);
        $this->assertSame('', $result->name);
        $this->assertSame('none', $result->type);
        $this->assertEmpty($result->require);
        $this->assertEmpty($result->requireDev);
        $this->assertEmpty($result->installed);
        $this->assertNull($result->phpVersion);
        $this->assertNull($result->nodeVersion);
    }

    public function testToArray(): void
    {
        $result = new DependenciesResult(
            found: true,
            name: 'test-project',
            type: 'both',
            require: ['package/a' => '^1.0'],
            requireDev: ['package/b' => '^2.0'],
            installed: [],
            installedMap: [],
            outdated: [],
            autoload: [],
            licensesSummary: ['MIT' => 5],
            phpVersion: '>=8.2',
            phpExtensions: [],
            npmDependencies: ['react' => '^18.0'],
            npmDevDependencies: [],
            nodeVersion: '>=16'
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('found', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('require', $array);
        $this->assertArrayHasKey('requireDev', $array);
        $this->assertArrayHasKey('installed', $array);
        $this->assertArrayHasKey('phpVersion', $array);
        $this->assertArrayHasKey('nodeVersion', $array);
        $this->assertArrayHasKey('supportStatus', $array);

        $this->assertTrue($array['found']);
        $this->assertSame('test-project', $array['name']);
        $this->assertSame('both', $array['type']);
    }

    public function testComposerOnlyProject(): void
    {
        $result = new DependenciesResult(
            found: true,
            name: 'php-only',
            type: 'composer',
            require: ['vendor/package' => '^1.0'],
            requireDev: [],
            installed: [],
            installedMap: [],
            outdated: [],
            autoload: [],
            licensesSummary: [],
            phpVersion: '>=8.0',
            phpExtensions: [],
            npmDependencies: [],
            npmDevDependencies: [],
            nodeVersion: null
        );

        $this->assertSame('composer', $result->type);
        $this->assertNull($result->nodeVersion);
        $this->assertEmpty($result->npmDependencies);
    }

    public function testNpmOnlyProject(): void
    {
        $result = new DependenciesResult(
            found: true,
            name: 'js-only',
            type: 'npm',
            require: [],
            requireDev: [],
            installed: [],
            installedMap: [],
            outdated: [],
            autoload: [],
            licensesSummary: [],
            phpVersion: null,
            phpExtensions: [],
            npmDependencies: ['react' => '^18.0'],
            npmDevDependencies: ['typescript' => '^5.0'],
            nodeVersion: '>=18.0'
        );

        $this->assertSame('npm', $result->type);
        $this->assertNull($result->phpVersion);
        $this->assertNotEmpty($result->npmDependencies);
    }
}
