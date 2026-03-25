<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer;

use PhpQuality\Analyzer\DependenciesAnalyzer;
use PhpQuality\Analyzer\Result\DependenciesResult;
use PHPUnit\Framework\TestCase;

class DependenciesAnalyzerTest extends TestCase
{
    private DependenciesAnalyzer $analyzer;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->analyzer = new DependenciesAnalyzer();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_deps_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testAnalyzeReturnsNotFoundForEmptyProject(): void
    {
        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertInstanceOf(DependenciesResult::class, $result);
        $this->assertFalse($result->found);
    }

    public function testAnalyzeWithComposerJson(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'require' => [
                'php' => '^8.1',
                'symfony/framework-bundle' => '^6.4',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^10.0',
            ],
        ];
        file_put_contents(
            $this->fixturesPath . '/composer.json',
            json_encode($composerJson)
        );

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertTrue($result->found);
        $this->assertSame('test/project', $result->name);
        $this->assertSame('composer', $result->type);
        $this->assertArrayHasKey('php', $result->require);
        $this->assertSame('^8.1', $result->phpVersion);
    }

    public function testAnalyzeWithComposerLock(): void
    {
        $composerJson = [
            'name' => 'test/project',
            'require' => ['symfony/console' => '^6.0'],
        ];
        $composerLock = [
            'packages' => [
                [
                    'name' => 'symfony/console',
                    'version' => 'v6.4.0',
                    'license' => ['MIT'],
                    'description' => 'Console component',
                ],
            ],
            'packages-dev' => [],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertNotEmpty($result->installed);
        $this->assertArrayHasKey('symfony/console', $result->installedMap);
        $this->assertArrayHasKey('MIT', $result->licensesSummary);
    }

    public function testAnalyzeWithPackageJson(): void
    {
        $packageJson = [
            'name' => 'my-frontend',
            'dependencies' => [
                'react' => '^18.0.0',
            ],
            'devDependencies' => [
                'typescript' => '^5.0.0',
            ],
            'engines' => [
                'node' => '>=18.0.0',
            ],
        ];
        file_put_contents($this->fixturesPath . '/package.json', json_encode($packageJson));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertTrue($result->found);
        $this->assertSame('my-frontend', $result->name);
        $this->assertSame('npm', $result->type);
        $this->assertArrayHasKey('react', $result->npmDependencies);
        $this->assertArrayHasKey('typescript', $result->npmDevDependencies);
        $this->assertSame('>=18.0.0', $result->nodeVersion);
    }

    public function testAnalyzeWithBothComposerAndNpm(): void
    {
        file_put_contents(
            $this->fixturesPath . '/composer.json',
            json_encode(['name' => 'test/project'])
        );
        file_put_contents(
            $this->fixturesPath . '/package.json',
            json_encode(['name' => 'test-frontend'])
        );

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertSame('both', $result->type);
    }

    public function testAnalyzeExtractsPhpExtensions(): void
    {
        $composerJson = [
            'require' => [
                'php' => '^8.1',
                'ext-json' => '*',
                'ext-mbstring' => '*',
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('ext-json', $result->phpExtensions);
        $this->assertArrayHasKey('ext-mbstring', $result->phpExtensions);
    }

    public function testAnalyzeExtractsAutoload(): void
    {
        $composerJson = [
            'autoload' => [
                'psr-4' => [
                    'App\\' => 'src/',
                ],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('psr-4', $result->autoload);
    }

    public function testAnalyzeWithDevPackages(): void
    {
        $composerJson = ['require-dev' => ['phpunit/phpunit' => '^10.0']];
        $composerLock = [
            'packages' => [],
            'packages-dev' => [
                [
                    'name' => 'phpunit/phpunit',
                    'version' => '10.5.0',
                    'license' => ['BSD-3-Clause'],
                ],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('phpunit/phpunit', $result->installedMap);
        $this->assertSame('require-dev', $result->installedMap['phpunit/phpunit']['type']);
    }

    public function testAnalyzeChecksPhpSupportStatus(): void
    {
        $composerJson = [
            'require' => ['php' => '^8.2'],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('php', $result->supportStatus);
        $this->assertSame('PHP', $result->supportStatus['php']['name']);
        $this->assertSame('8.2', $result->supportStatus['php']['version']);
    }

    public function testAnalyzeChecksSymfonySupportStatus(): void
    {
        $composerJson = ['require' => ['symfony/framework-bundle' => '^6.4']];
        $composerLock = [
            'packages' => [
                [
                    'name' => 'symfony/framework-bundle',
                    'version' => 'v6.4.0',
                ],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('symfony', $result->supportStatus);
        $this->assertTrue($result->supportStatus['symfony']['lts']);
    }

    public function testAnalyzeHandlesInvalidJson(): void
    {
        file_put_contents($this->fixturesPath . '/composer.json', '{invalid json}');

        $result = $this->analyzer->analyze($this->fixturesPath);

        // Should not crash, but might return limited info
        $this->assertTrue($result->found);
    }

    public function testAnalyzeFindsFileInParentDirectory(): void
    {
        $subdir = $this->fixturesPath . '/src/Service';
        mkdir($subdir, 0755, true);

        file_put_contents(
            $this->fixturesPath . '/composer.json',
            json_encode(['name' => 'test/project'])
        );

        $result = $this->analyzer->analyze($subdir);

        $this->assertTrue($result->found);
        $this->assertSame('test/project', $result->name);
    }

    public function testAnalyzeLicensesSummary(): void
    {
        $composerLock = [
            'packages' => [
                ['name' => 'a/a', 'version' => '1.0', 'license' => ['MIT']],
                ['name' => 'b/b', 'version' => '1.0', 'license' => ['MIT']],
                ['name' => 'c/c', 'version' => '1.0', 'license' => ['Apache-2.0']],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', '{}');
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('MIT', $result->licensesSummary);
        $this->assertSame(2, $result->licensesSummary['MIT']);
        $this->assertArrayHasKey('Apache-2.0', $result->licensesSummary);
    }

    public function testAnalyzePackageDetails(): void
    {
        $composerLock = [
            'packages' => [
                [
                    'name' => 'vendor/package',
                    'version' => '2.0.0',
                    'license' => ['GPL-3.0'],
                    'description' => 'A test package',
                    'time' => '2024-01-15T10:00:00+00:00',
                    'source' => ['url' => 'https://github.com/vendor/package'],
                ],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', '{}');
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $package = $result->installedMap['vendor/package'];
        $this->assertSame('2.0.0', $package['version']);
        $this->assertSame('A test package', $package['description']);
        $this->assertContains('GPL-3.0', $package['license']);
    }

    public function testAnalyzeUsesBasenameAsNameFallback(): void
    {
        file_put_contents($this->fixturesPath . '/composer.json', '{}');

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertStringContainsString('phpquality_deps_test_', $result->name);
    }

    public function testAnalyzeLaravelSupportStatus(): void
    {
        $composerJson = ['require' => ['laravel/framework' => '^10.0']];
        $composerLock = [
            'packages' => [
                [
                    'name' => 'laravel/framework',
                    'version' => 'v10.48.0',
                ],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('laravel', $result->supportStatus);
        $this->assertSame('Laravel', $result->supportStatus['laravel']['name']);
        $this->assertSame('10', $result->supportStatus['laravel']['version']);
    }

    public function testAnalyzeDrupalSupportStatus(): void
    {
        $composerJson = ['require' => ['drupal/core' => '^10.0']];
        $composerLock = [
            'packages' => [
                [
                    'name' => 'drupal/core',
                    'version' => '10.2.0',
                ],
            ],
        ];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));
        file_put_contents($this->fixturesPath . '/composer.lock', json_encode($composerLock));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('drupal', $result->supportStatus);
        $this->assertSame('Drupal', $result->supportStatus['drupal']['name']);
        $this->assertTrue($result->supportStatus['drupal']['lts']);
    }

    public function testAnalyzeOldPhpVersion(): void
    {
        $composerJson = ['require' => ['php' => '^7.4']];
        file_put_contents($this->fixturesPath . '/composer.json', json_encode($composerJson));

        $result = $this->analyzer->analyze($this->fixturesPath);

        $this->assertArrayHasKey('php', $result->supportStatus);
        $this->assertFalse($result->supportStatus['php']['supported']);
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
