<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

abstract class AbstractProjectType implements ProjectTypeInterface
{
    public function getExcludedPaths(): array
    {
        return [
            'vendor',
            'node_modules',
            'var',
            'cache',
            'logs',
            'tests',
            'Tests',
            'test',
            'spec',
        ];
    }

    public function getArchitecturalPatterns(): array
    {
        return [];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 10,
            'lcom' => 0.8,
            'mi' => 20,
        ];
    }

    public function getClassCategories(): array
    {
        return [];
    }

    /**
     * Helper to check if a file exists in the project
     */
    protected function fileExists(string $projectPath, string $file): bool
    {
        return file_exists($projectPath . DIRECTORY_SEPARATOR . $file);
    }

    /**
     * Helper to check if a directory exists in the project
     */
    protected function dirExists(string $projectPath, string $dir): bool
    {
        return is_dir($projectPath . DIRECTORY_SEPARATOR . $dir);
    }

    /**
     * Helper to check composer.json for a package
     */
    protected function hasComposerPackage(string $projectPath, string $package): bool
    {
        $composerFile = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
        if (!file_exists($composerFile)) {
            return false;
        }

        $content = file_get_contents($composerFile);
        if ($content === false) {
            return false;
        }

        $composer = json_decode($content, true);
        if (!is_array($composer)) {
            return false;
        }

        $require = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        return isset($require[$package]);
    }

    /**
     * Helper to check composer.json type
     */
    protected function hasComposerType(string $projectPath, string $type): bool
    {
        $composerFile = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';
        if (!file_exists($composerFile)) {
            return false;
        }

        $content = file_get_contents($composerFile);
        if ($content === false) {
            return false;
        }

        $composer = json_decode($content, true);
        return ($composer['type'] ?? '') === $type;
    }
}
