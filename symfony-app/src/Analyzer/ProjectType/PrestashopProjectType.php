<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * PrestaShop e-commerce platform
 */
class PrestashopProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'prestashop';
    }

    public function getLabel(): string
    {
        return 'PrestaShop';
    }

    public function getDescription(): string
    {
        return 'PrestaShop e-commerce platform or module';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'prestashop/prestashop')) {
            $score += 60;
        }

        if ($this->hasComposerType($projectPath, 'prestashop-module')) {
            $score += 70;
        }

        // PrestaShop core indicators
        if ($this->dirExists($projectPath, 'classes')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'controllers')) {
            $score += 15;
        }

        if ($this->fileExists($projectPath, 'config/config.inc.php')) {
            $score += 20;
        }

        // Module structure
        if ($this->dirExists($projectPath, 'views/templates')) {
            $score += 10;
        }

        // Check for ObjectModel classes
        if ($this->dirExists($projectPath, 'src') || $this->dirExists($projectPath, 'classes')) {
            $score += 5;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'var/cache',
            'app/cache',
            'img',
            'js',
            'css',
            'themes',
            'translations',
            'upload',
            'download',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*ModuleFrontController$' => 'FrontController',
            '.*ModuleAdminController$' => 'AdminController',
            'ObjectModel$' => 'ObjectModel',
            '.*Module$' => 'Module',
            '.*Hook.*' => 'Hook',
            '.*Cart.*' => 'Cart',
            '.*Order.*' => 'Order',
            '.*Product.*' => 'Product',
            '.*Customer.*' => 'Customer',
        ];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 15, // PrestaShop tends to have higher complexity
            'lcom' => 0.8,
            'mi' => 20,
        ];
    }
}
