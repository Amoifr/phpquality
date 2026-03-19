<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * Joomla CMS extension
 */
class JoomlaProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'joomla';
    }

    public function getLabel(): string
    {
        return 'Joomla';
    }

    public function getDescription(): string
    {
        return 'Joomla component, module, plugin or template';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        if ($this->hasComposerType($projectPath, 'joomla-component')) {
            $score += 70;
        }

        if ($this->hasComposerType($projectPath, 'joomla-plugin')) {
            $score += 70;
        }

        if ($this->hasComposerType($projectPath, 'joomla-module')) {
            $score += 70;
        }

        // Joomla manifest files
        $xmlFiles = glob($projectPath . '/*.xml') ?: [];
        foreach ($xmlFiles as $file) {
            $content = file_get_contents($file);
            if ($content !== false && str_contains($content, '<extension')) {
                $score += 40;
                break;
            }
        }

        // Joomla component structure
        if ($this->dirExists($projectPath, 'administrator')) {
            $score += 20;
        }

        if ($this->dirExists($projectPath, 'site')) {
            $score += 15;
        }

        // J prefix classes
        $phpFiles = glob($projectPath . '/src/**/*.php') ?: [];
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            if ($content !== false && preg_match('/class\s+J[A-Z]/', $content)) {
                $score += 15;
                break;
            }
        }

        return min($score, 100);
    }

    public function getExcludedPaths(): array
    {
        return array_merge(parent::getExcludedPaths(), [
            'media',
            'language',
            'tmpl',
        ]);
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Model$' => 'Model',
            '.*View$' => 'View',
            '.*Table$' => 'Table',
            '.*Helper$' => 'Helper',
            '.*Plugin$' => 'Plugin',
            '.*Field$' => 'FormField',
            '.*Rule$' => 'FormRule',
            '.*Router$' => 'Router',
        ];
    }

    public function getRecommendedThresholds(): array
    {
        return [
            'ccn' => 12,
            'lcom' => 0.8,
            'mi' => 20,
        ];
    }
}
