<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * Sylius e-commerce (Symfony-based)
 */
class SyliusProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'sylius';
    }

    public function getLabel(): string
    {
        return 'Sylius';
    }

    public function getDescription(): string
    {
        return 'Sylius e-commerce platform (Symfony-based)';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'sylius/sylius')) {
            $score += 70;
        }

        if ($this->hasComposerPackage($projectPath, 'sylius/core-bundle')) {
            $score += 50;
        }

        if ($this->hasComposerType($projectPath, 'sylius-plugin')) {
            $score += 70;
        }

        // Sylius plugin structure
        if ($this->dirExists($projectPath, 'src/Resources/config')) {
            $score += 15;
        }

        // Sylius entities
        if ($this->dirExists($projectPath, 'src/Entity')) {
            $score += 10;
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
            'public/media',
            'migrations',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Repository$' => 'Repository',
            '.*Factory$' => 'Factory',
            '.*Processor$' => 'Processor',
            '.*Provider$' => 'Provider',
            '.*Handler$' => 'Handler',
            '.*Resolver$' => 'Resolver',
            '.*Checker$' => 'Checker',
            '.*Calculator$' => 'Calculator',
            '.*Generator$' => 'Generator',
            '.*Applicator$' => 'Applicator',
            '.*Command$' => 'Command',
            '.*Menu$' => 'Menu',
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
