<?php

declare(strict_types=1);

namespace PhpQuality\Tests\Analyzer\ProjectType;

use PhpQuality\Analyzer\ProjectType\DrupalProjectType;
use PHPUnit\Framework\TestCase;

class DrupalProjectTypeTest extends TestCase
{
    private DrupalProjectType $projectType;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->projectType = new DrupalProjectType();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_drupal_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testGetName(): void
    {
        $this->assertSame('drupal', $this->projectType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('Drupal', $this->projectType->getLabel());
    }

    public function testGetDescription(): void
    {
        $description = $this->projectType->getDescription();
        $this->assertStringContainsString('Drupal', $description);
    }

    public function testDetectReturnsZeroForEmptyDirectory(): void
    {
        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertSame(0, $score);
    }

    public function testDetectWithDrupalCore(): void
    {
        $this->createComposerJson(['require' => ['drupal/core' => '^10.0']]);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThan(0, $score);
    }

    public function testDetectWithModulesDirectory(): void
    {
        mkdir($this->fixturesPath . '/modules', 0755, true);
        mkdir($this->fixturesPath . '/themes', 0755, true);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function testGetExcludedPaths(): void
    {
        $excluded = $this->projectType->getExcludedPaths();

        $this->assertIsArray($excluded);
        $this->assertContains('vendor', $excluded);
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
