<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * Magento 2 e-commerce platform
 */
class MagentoProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'magento';
    }

    public function getLabel(): string
    {
        return 'Magento 2';
    }

    public function getDescription(): string
    {
        return 'Magento 2 e-commerce platform or module';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'magento/framework')) {
            $score += 50;
        }

        if ($this->hasComposerType($projectPath, 'magento2-module')) {
            $score += 70;
        }

        // Magento module structure
        if ($this->fileExists($projectPath, 'registration.php')) {
            $score += 20;
        }

        if ($this->dirExists($projectPath, 'etc')) {
            if ($this->fileExists($projectPath, 'etc/module.xml')) {
                $score += 20;
            }
        }

        // Magento directories
        if ($this->dirExists($projectPath, 'Block')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'Model')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'Controller')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'Plugin')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'Observer')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'var',
            'pub/static',
            'generated',
            'setup',
            'dev',
            'lib',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Block$' => 'Block',
            '.*Controller$' => 'Controller',
            '.*Model$' => 'Model',
            '.*ResourceModel$' => 'ResourceModel',
            '.*Collection$' => 'Collection',
            '.*Plugin$' => 'Plugin',
            '.*Observer$' => 'Observer',
            '.*Helper$' => 'Helper',
            '.*Repository$' => 'Repository',
            '.*Api$' => 'Api',
            '.*Setup$' => 'Setup',
            '.*Command$' => 'Command',
            '.*Cron$' => 'Cron',
            '.*ViewModel$' => 'ViewModel',
        ];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 15,
            'lcom' => 0.8,
            'mi' => 20,
        ];
    }
}
