<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * Sulu CMS (Symfony-based)
 */
class SuluProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'sulu';
    }

    public function getLabel(): string
    {
        return 'Sulu CMS';
    }

    public function getDescription(): string
    {
        return 'Sulu CMS application (Symfony-based)';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'sulu/sulu')) {
            $score += 70;
        }

        // Sulu skeleton
        if ($this->hasComposerPackage($projectPath, 'sulu/skeleton')) {
            $score += 30;
        }

        // Sulu specific directories
        if ($this->dirExists($projectPath, 'config/webspaces')) {
            $score += 20;
        }

        if ($this->dirExists($projectPath, 'config/templates')) {
            $score += 15;
        }

        // Sulu content types
        if ($this->dirExists($projectPath, 'src/Content')) {
            $score += 15;
        }

        // It's also a Symfony project
        if ($this->hasComposerPackage($projectPath, 'symfony/framework-bundle')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'var/cache',
            'var/log',
            'public/bundles',
            'public/uploads',
            'migrations',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Admin$' => 'Admin',
            '.*ContentType$' => 'ContentType',
            '.*Document$' => 'Document',
            '.*Repository$' => 'Repository',
            '.*Service$' => 'Service',
            '.*Handler$' => 'Handler',
            '.*Subscriber$' => 'EventSubscriber',
            '.*Command$' => 'Command',
            '.*DataProvider$' => 'DataProvider',
            '.*Resolver$' => 'Resolver',
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
