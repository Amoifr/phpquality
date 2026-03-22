<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\ProjectType;

use App\Analyzer\ProjectType\LaravelProjectType;
use PHPUnit\Framework\TestCase;

class LaravelProjectTypeTest extends TestCase
{
    private LaravelProjectType $projectType;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->projectType = new LaravelProjectType();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testGetName(): void
    {
        $this->assertSame('laravel', $this->projectType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Laravel', $this->projectType->getLabel());
    }

    public function testGetDescription(): void
    {
        $this->assertStringContainsString('Laravel', $this->projectType->getDescription());
    }

    public function testDetectReturnsZeroForEmptyDirectory(): void
    {
        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertSame(0, $score);
    }

    public function testDetectWithLaravelFramework(): void
    {
        $this->createComposerJson(['require' => ['laravel/framework' => '^11.0']]);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(50, $score);
    }

    public function testDetectWithArtisan(): void
    {
        file_put_contents($this->fixturesPath . '/artisan', '<?php // Laravel artisan');

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(20, $score);
    }

    public function testDetectWithHttpControllers(): void
    {
        mkdir($this->fixturesPath . '/app/Http/Controllers', 0755, true);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(10, $score);
    }

    public function testDetectWithBootstrapApp(): void
    {
        mkdir($this->fixturesPath . '/bootstrap', 0755, true);
        file_put_contents($this->fixturesPath . '/bootstrap/app.php', '<?php');

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(10, $score);
    }

    public function testGetExcludedPaths(): void
    {
        $excluded = $this->projectType->getExcludedPaths();

        $this->assertContains('vendor', $excluded);
        $this->assertContains('storage', $excluded);
        $this->assertContains('bootstrap/cache', $excluded);
    }

    public function testGetClassCategories(): void
    {
        $categories = $this->projectType->getClassCategories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('.*Controller$', $categories);
        $this->assertArrayHasKey('.*Model$', $categories);
        $this->assertArrayHasKey('.*Provider$', $categories);
        $this->assertSame('Controller', $categories['.*Controller$']);
        $this->assertSame('Model', $categories['.*Model$']);
    }

    public function testGetRecommendedThresholds(): void
    {
        $thresholds = $this->projectType->getRecommendedThresholds();

        $this->assertArrayHasKey('ccn', $thresholds);
        $this->assertArrayHasKey('lcom', $thresholds);
        $this->assertArrayHasKey('mi', $thresholds);
    }

    private function createComposerJson(array $content): void
    {
        file_put_contents(
            $this->fixturesPath . '/composer.json',
            json_encode($content, JSON_PRETTY_PRINT)
        );
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
