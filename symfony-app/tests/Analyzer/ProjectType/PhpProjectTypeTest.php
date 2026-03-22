<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\ProjectType;

use App\Analyzer\ProjectType\PhpProjectType;
use PHPUnit\Framework\TestCase;

class PhpProjectTypeTest extends TestCase
{
    private PhpProjectType $projectType;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->projectType = new PhpProjectType();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_php_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testGetName(): void
    {
        $this->assertSame('php', $this->projectType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('PHP (Generic)', $this->projectType->getLabel());
    }

    public function testGetDescription(): void
    {
        $description = $this->projectType->getDescription();
        $this->assertStringContainsString('PHP', $description);
    }

    public function testDetectWithComposerJson(): void
    {
        file_put_contents(
            $this->fixturesPath . '/composer.json',
            json_encode(['require' => ['php' => '>=8.0']])
        );

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThan(0, $score);
    }

    public function testDetectWithSrcDirectory(): void
    {
        mkdir($this->fixturesPath . '/src', 0755, true);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function testGetExcludedPaths(): void
    {
        $excluded = $this->projectType->getExcludedPaths();

        $this->assertIsArray($excluded);
        $this->assertContains('vendor', $excluded);
        $this->assertContains('node_modules', $excluded);
    }

    public function testGetRecommendedThresholds(): void
    {
        $thresholds = $this->projectType->getRecommendedThresholds();

        $this->assertArrayHasKey('ccn', $thresholds);
        $this->assertArrayHasKey('lcom', $thresholds);
        $this->assertArrayHasKey('mi', $thresholds);
    }

    public function testGetClassCategories(): void
    {
        $categories = $this->projectType->getClassCategories();

        $this->assertIsArray($categories);
    }

    public function testGetArchitecturalPatterns(): void
    {
        $patterns = $this->projectType->getArchitecturalPatterns();

        $this->assertIsArray($patterns);
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
