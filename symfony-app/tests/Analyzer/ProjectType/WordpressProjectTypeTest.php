<?php

declare(strict_types=1);

namespace App\Tests\Analyzer\ProjectType;

use App\Analyzer\ProjectType\WordpressProjectType;
use PHPUnit\Framework\TestCase;

class WordpressProjectTypeTest extends TestCase
{
    private WordpressProjectType $projectType;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->projectType = new WordpressProjectType();
        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_wp_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testGetName(): void
    {
        $this->assertSame('wordpress', $this->projectType->getName());
    }

    public function testGetLabel(): void
    {
        $this->assertSame('WordPress', $this->projectType->getLabel());
    }

    public function testGetDescription(): void
    {
        $description = $this->projectType->getDescription();
        $this->assertStringContainsString('WordPress', $description);
    }

    public function testDetectReturnsZeroForEmptyDirectory(): void
    {
        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertSame(0, $score);
    }

    public function testDetectWithPluginHeader(): void
    {
        $pluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: My Plugin
 * Plugin URI: https://example.com
 * Description: A test plugin
 * Version: 1.0.0
 */
PHP;
        file_put_contents($this->fixturesPath . '/my-plugin.php', $pluginContent);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThan(0, $score);
    }

    public function testDetectWithThemeStyleCss(): void
    {
        $styleContent = <<<'CSS'
/*
Theme Name: My Theme
Theme URI: https://example.com
Version: 1.0.0
*/
CSS;
        file_put_contents($this->fixturesPath . '/style.css', $styleContent);

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThan(0, $score);
    }

    public function testDetectWithFunctionsPhp(): void
    {
        file_put_contents($this->fixturesPath . '/functions.php', '<?php');

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThan(0, $score);
    }

    public function testDetectWithComposerPluginType(): void
    {
        file_put_contents(
            $this->fixturesPath . '/composer.json',
            json_encode(['type' => 'wordpress-plugin'])
        );

        $score = $this->projectType->detect($this->fixturesPath);

        $this->assertGreaterThanOrEqual(70, $score);
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
