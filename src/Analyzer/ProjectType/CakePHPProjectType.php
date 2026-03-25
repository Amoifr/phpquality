<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * CakePHP framework
 */
class CakePHPProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'cakephp';
    }

    public function getLabel(): string
    {
        return 'CakePHP';
    }

    public function getDescription(): string
    {
        return 'CakePHP framework application';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'cakephp/cakephp')) {
            $score += 60;
        }

        if ($this->hasComposerType($projectPath, 'cakephp-plugin')) {
            $score += 70;
        }

        // CakePHP structure
        if ($this->dirExists($projectPath, 'src/Controller')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'src/Model')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'src/View')) {
            $score += 10;
        }

        if ($this->fileExists($projectPath, 'bin/cake')) {
            $score += 20;
        }

        if ($this->fileExists($projectPath, 'config/bootstrap.php')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'templates')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'tmp',
            'logs',
            'webroot',
            'config/Migrations',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Table$' => 'Table',
            '.*Entity$' => 'Entity',
            '.*Behavior$' => 'Behavior',
            '.*Component$' => 'Component',
            '.*Helper$' => 'Helper',
            '.*Cell$' => 'Cell',
            '.*Command$' => 'Command',
            '.*Middleware$' => 'Middleware',
            '.*Mailer$' => 'Mailer',
            '.*Form$' => 'Form',
            '.*Shell$' => 'Shell',
            '.*Task$' => 'Task',
            '.*Fixture$' => 'Fixture',
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
