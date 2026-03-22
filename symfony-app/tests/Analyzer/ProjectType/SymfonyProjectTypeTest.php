<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\ProjectType;

use App\Analyzer\ProjectType\SymfonyProjectType;
use PHPUnit\Framework\TestCase;

class SymfonyProjectTypeTest extends TestCase
{
    private SymfonyProjectType $projectType;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->projectType = new SymfonyProjectType();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testGetName(): void
    {
        $this->assertSame('symfony', $this->projectType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Symfony', $this->projectType->getLabel());
    }

    public function testGetDescription(): void
    {
        $this->assertStringContainsString('Symfony', $this->projectType->getDescription());
    }

    public function testDetectReturnsZeroForEmptyDirectory(): void
    {
        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertSame(0, $score);
    }

    public function testDetectWithFrameworkBundle(): void
    {
        $this->createComposerJson(['require' => ['symfony/framework-bundle' => '^7.0']]);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(50, $score);
    }

    public function testDetectWithConfigPackages(): void
    {
        mkdir($this->fixturesPath . '/config/packages', 0755, true);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(20, $score);
    }

    public function testDetectWithBundlesPhp(): void
    {
        mkdir($this->fixturesPath . '/config', 0755, true);
        file_put_contents($this->fixturesPath . '/config/bundles.php', '<?php return [];');

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(15, $score);
    }

    public function testDetectWithSymfonyLock(): void
    {
        file_put_contents($this->fixturesPath . '/symfony.lock', '{}');

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(15, $score);
    }

    public function testDetectWithControllerDirectory(): void
    {
        mkdir($this->fixturesPath . '/src/Controller', 0755, true);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(10, $score);
    }

    public function testDetectWithEntityDirectory(): void
    {
        mkdir($this->fixturesPath . '/src/Entity', 0755, true);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(10, $score);
    }

    public function testDetectFullSymfonyProject(): void
    {
        $this->createComposerJson(['require' => ['symfony/framework-bundle' => '^7.0']]);
        mkdir($this->fixturesPath . '/config/packages', 0755, true);
        mkdir($this->fixturesPath . '/src/Controller', 0755, true);
        mkdir($this->fixturesPath . '/src/Entity', 0755, true);
        file_put_contents($this->fixturesPath . '/config/bundles.php', '<?php return [];');
        file_put_contents($this->fixturesPath . '/symfony.lock', '{}');

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertSame(100, $score);
    }

    public function testGetExcludedPaths(): void
    {
        $excluded = $this->projectType->getExcludedPaths();

        $this->assertContains('vendor', $excluded);
        $this->assertContains('var/cache', $excluded);
        $this->assertContains('var/log', $excluded);
        $this->assertContains('migrations', $excluded);
    }

    public function testGetArchitecturalPatterns(): void
    {
        $patterns = $this->projectType->getArchitecturalPatterns();

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('controller_service', $patterns);
        $this->assertArrayHasKey('repository_em', $patterns);
    }

    public function testGetClassCategories(): void
    {
        $categories = $this->projectType->getClassCategories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('.*Controller$', $categories);
        $this->assertArrayHasKey('.*Service$', $categories);
        $this->assertArrayHasKey('.*Repository$', $categories);
        $this->assertArrayHasKey('.*Entity$', $categories);

        $this->assertSame('Controller', $categories['.*Controller$']);
        $this->assertSame('Service', $categories['.*Service$']);
    }

    public function testGetRecommendedThresholds(): void
    {
        $thresholds = $this->projectType->getRecommendedThresholds();

        $this->assertArrayHasKey('ccn', $thresholds);
        $this->assertArrayHasKey('lcom', $thresholds);
        $this->assertArrayHasKey('mi', $thresholds);

        $this->assertSame(10, $thresholds['ccn']);
        $this->assertSame(0.7, $thresholds['lcom']);
        $this->assertSame(25, $thresholds['mi']);
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
