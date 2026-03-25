<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * WordPress CMS plugin or theme
 */
class WordpressProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'wordpress';
    }

    public function getLabel(): string
    {
        return 'WordPress';
    }

    public function getDescription(): string
    {
        return 'WordPress plugin or theme';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerType($projectPath, 'wordpress-plugin')) {
            $score += 70;
        }

        if ($this->hasComposerType($projectPath, 'wordpress-theme')) {
            $score += 70;
        }

        // Plugin header check
        $phpFiles = glob($projectPath . '/*.php') ?: [];
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if ($content !== false && str_contains($content, 'Plugin Name:')) {
                $score += 40;
                break;
            }
        }

        // Theme indicators
        if ($this->fileExists($projectPath, 'style.css')) {
            $content = file_get_contents($projectPath . '/style.css');
            if ($content !== false && str_contains($content, 'Theme Name:')) {
                $score += 40;
            }
        }

        if ($this->fileExists($projectPath, 'functions.php')) {
            $score += 15;
        }

        // Common WP functions usage
        if ($this->dirExists($projectPath, 'includes')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'admin')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'assets',
            'languages',
            'js',
            'css',
            'images',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Admin.*' => 'Admin',
            '.*Widget$' => 'Widget',
            '.*Shortcode.*' => 'Shortcode',
            '.*Block.*' => 'Block',
            '.*Rest.*' => 'RestAPI',
            '.*Ajax.*' => 'Ajax',
            '.*Hook.*' => 'Hook',
            '.*Cron.*' => 'Cron',
            '.*Settings.*' => 'Settings',
            '.*Meta.*' => 'Meta',
            '.*Taxonomy.*' => 'Taxonomy',
            '.*PostType.*' => 'PostType',
        ];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 12,
            'lcom' => 0.8,
            'mi' => 20,
        ];
    }
}
