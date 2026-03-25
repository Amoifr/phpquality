<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * Symfony framework project
 */
class SymfonyProjectType extends AbstractProjectType
{
    public function getName(): string
    {
        return 'symfony';
    }

    public function getLabel(): string
    {
        return 'Symfony';
    }

    public function getDescription(): string
    {
        return 'Symfony framework application';
    }

    public function detect(string $projectPath): int
    {
        $score = 0;

        // symfony/framework-bundle is the key indicator
        if ($this->hasComposerPackage($projectPath, 'symfony/framework-bundle')) {
            $score += 50;
        }

        // Symfony directory structure
        if ($this->dirExists($projectPath, 'config/packages')) {
            $score += 20;
        }

        if ($this->fileExists($projectPath, 'config/bundles.php')) {
            $score += 15;
        }

        if ($this->fileExists($projectPath, 'symfony.lock')) {
            $score += 15;
        }

        if ($this->dirExists($projectPath, 'src/Controller')) {
            $score += 10;
        }

        if ($this->dirExists($projectPath, 'src/Entity')) {
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
            'migrations',
        ]);
    }

    public function getArchitecturalPatterns(): array
    {
        return [
            'controller_service' => [
                'pattern' => 'Controller.*Service',
                'description' => 'Controller injecting service directly (check for thin controllers)',
            ],
            'repository_em' => [
                'pattern' => 'Repository.*EntityManager',
                'description' => 'Repository using EntityManager (prefer ServiceEntityRepository)',
            ],
        ];
    }

    public function getClassCategories(): array
    {
        return [
            '.*Controller$' => 'Controller',
            '.*Service$' => 'Service',
            '.*Repository$' => 'Repository',
            '.*Entity$' => 'Entity',
            '.*Command$' => 'Command',
            '.*Subscriber$' => 'EventSubscriber',
            '.*Listener$' => 'EventListener',
            '.*Voter$' => 'Security',
            '.*Form$' => 'Form',
            '.*Type$' => 'FormType',
            '.*Extension$' => 'Extension',
            '.*Normalizer$' => 'Serializer',
            '.*Transformer$' => 'DataTransformer',
            '.*Handler$' => 'Handler',
            '.*Provider$' => 'Provider',
            '.*Factory$' => 'Factory',
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
