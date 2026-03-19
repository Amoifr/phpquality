<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * Generic PHP project (fallback)
 */
class PhpProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'php';
    }

    public function getLabel(): string
    {
        return 'PHP (Generic)';
    }

    public function getDescription(): string
    {
        return 'Generic PHP project without specific framework';
    }

    public function detect(string $projectPath): int
    {
        // Always returns a low score as fallback
        if ($this->fileExists($projectPath, 'composer.json')) {
            return 10;
        }

        // Check for any PHP files
        $phpFiles = glob($projectPath . '/*.php') ?: [];
        return count($phpFiles) > 0 ? 5 : 0;
    }
}
