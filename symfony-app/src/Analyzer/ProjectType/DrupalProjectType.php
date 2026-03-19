<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * Drupal CMS module or theme
 */
class DrupalProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'drupal';
    }

    public function getLabel(): string
    {
        return 'Drupal';
    }

    public function getDescription(): string
    {
        return 'Drupal module or theme';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerType($projectPath, 'drupal-module')) {
            $score += 70;
        }

        if ($this->hasComposerType($projectPath, 'drupal-theme')) {
            $score += 70;
        }

        if ($this->hasComposerPackage($projectPath, 'drupal/core')) {
            $score += 40;
        }

        // Drupal module indicators
        $infoFiles = glob($projectPath . '/*.info.yml') ?: [];
        if (count($infoFiles) > 0) {
            $score += 30;
        }

        // .module file
        $moduleFiles = glob($projectPath . '/*.module') ?: [];
        if (count($moduleFiles) > 0) {
            $score += 20;
        }

        // Drupal structure
        if ($this->dirExists($projectPath, 'src/Plugin')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'src/Controller')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'src/Form')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'config/install',
            'templates',
            'css',
            'js',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Form$' => 'Form',
            '.*Block$' => 'Block',
            '.*Plugin$' => 'Plugin',
            '.*Service$' => 'Service',
            '.*Subscriber$' => 'EventSubscriber',
            '.*Manager$' => 'Manager',
            '.*Builder$' => 'Builder',
            '.*Handler$' => 'Handler',
            '.*Formatter$' => 'Formatter',
            '.*Widget$' => 'Widget',
            '.*Access.*' => 'Access',
        ];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 10,
            'lcom' => 0.7,
            'mi' => 25,
        ];
    }
}
