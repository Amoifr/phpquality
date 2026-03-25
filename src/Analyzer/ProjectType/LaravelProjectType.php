<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * Laravel framework project
 */
class LaravelProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'laravel';
    }

    public function getLabel(): string
    {
        return 'Laravel';
    }

    public function getDescription(): string
    {
        return 'Laravel framework application';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerPackage($projectPath, 'laravel/framework')) {
            $score += 60;
        }

        if ($this->fileExists($projectPath, 'artisan')) {
            $score += 20;
        }

        if ($this->dirExists($projectPath, 'app/Http/Controllers')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'app/Models')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'resources/views')) {
            $score += 5;
        }

        if ($this->fileExists($projectPath, 'bootstrap/app.php')) {
            $score += 10;
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'storage',
            'bootstrap/cache',
            'public/storage',
            'database/migrations',
            'database/seeders',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Model$' => 'Model',
            '.*Middleware$' => 'Middleware',
            '.*Request$' => 'FormRequest',
            '.*Resource$' => 'Resource',
            '.*Policy$' => 'Policy',
            '.*Provider$' => 'ServiceProvider',
            '.*Job$' => 'Job',
            '.*Event$' => 'Event',
            '.*Listener$' => 'Listener',
            '.*Mail$' => 'Mail',
            '.*Notification$' => 'Notification',
            '.*Command$' => 'Command',
            '.*Observer$' => 'Observer',
            '.*Scope$' => 'Scope',
            '.*Cast$' => 'Cast',
            '.*Rule$' => 'ValidationRule',
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
