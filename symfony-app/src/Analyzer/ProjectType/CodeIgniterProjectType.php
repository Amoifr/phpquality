<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * CodeIgniter framework
 */
class CodeIgniterProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'codeigniter';
    }

    public function getLabel(): string
    {
        return 'CodeIgniter';
    }

    public function getDescription(): string
    {
        return 'CodeIgniter framework application';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'codeigniter4/framework')) {
            $score += 60;
        }

        if ($this->hasComposerPackage($projectPath, 'codeigniter/framework')) {
            $score += 60;
        }

        // CodeIgniter 4 structure
        if ($this->dirExists($projectPath, 'app/Controllers')) {
            $score += 20;
        }

        if ($this->dirExists($projectPath, 'app/Models')) {
            $score += 15;
        }

        if ($this->fileExists($projectPath, 'spark')) {
            $score += 20;
        }

        // CodeIgniter 3 structure
        if ($this->dirExists($projectPath, 'application/controllers')) {
            $score += 20;
        }

        if ($this->dirExists($projectPath, 'application/models')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'system/core')) {
            $score += 15;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'system',
            'writable',
            'public',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Model$' => 'Model',
            '.*Entity$' => 'Entity',
            '.*Library$' => 'Library',
            '.*Helper$' => 'Helper',
            '.*Filter$' => 'Filter',
            '.*Validation$' => 'Validation',
            '.*Migration$' => 'Migration',
            '.*Seeder$' => 'Seeder',
            '.*Command$' => 'Command',
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
