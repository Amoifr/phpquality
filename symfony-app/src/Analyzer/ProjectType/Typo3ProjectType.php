<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * TYPO3 CMS extension
 */
class Typo3ProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'typo3';
    }

    public function getLabel(): string
    {
        return 'TYPO3';
    }

    public function getDescription(): string
    {
        return 'TYPO3 CMS extension';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerType($projectPath, 'typo3-cms-extension')) {
            $score += 70;
        }

        if ($this->hasComposerPackage($projectPath, 'typo3/cms-core')) {
            $score += 40;
        }

        // TYPO3 extension manifest
        if ($this->fileExists($projectPath, 'ext_emconf.php')) {
            $score += 40;
        }

        // TYPO3 structure
        if ($this->dirExists($projectPath, 'Classes')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'Configuration')) {
            $score += 15;
        }

        if ($this->fileExists($projectPath, 'ext_localconf.php')) {
            $score += 10;
        }

        if ($this->fileExists($projectPath, 'ext_tables.php')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'Resources/Private')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'Resources/Public',
            'Resources/Private/Language',
            'Documentation',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Repository$' => 'Repository',
            '.*Command$' => 'Command',
            '.*Service$' => 'Service',
            '.*Utility$' => 'Utility',
            '.*ViewHelper$' => 'ViewHelper',
            '.*Hook$' => 'Hook',
            '.*DataHandler$' => 'DataHandler',
            '.*Middleware$' => 'Middleware',
            '.*EventListener$' => 'EventListener',
            '.*Finisher$' => 'FormFinisher',
            '.*Validator$' => 'Validator',
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
