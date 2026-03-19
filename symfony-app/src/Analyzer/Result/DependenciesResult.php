<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

final class DependenciesResult
{
    public function __construct(
        public readonly bool $found,
        public readonly string $name,
        public readonly string $type, // 'composer', 'npm', 'both'
        public readonly array $require,
        public readonly array $requireDev,
        public readonly array $installed,
        public readonly array $installedMap,
        public readonly array $outdated,
        public readonly array $autoload,
        public readonly array $licensesSummary,
        public readonly ?string $phpVersion,
        public readonly array $phpExtensions,
        public readonly array $npmDependencies,
        public readonly array $npmDevDependencies,
        public readonly ?string $nodeVersion,
        public readonly array $supportStatus = [],
    ) {}

    public static function notFound(): self
    {
        return new self(
            found: false,
            name: '',
            type: 'none',
            require: [],
            requireDev: [],
            installed: [],
            installedMap: [],
            outdated: [],
            autoload: [],
            licensesSummary: [],
            phpVersion: null,
            phpExtensions: [],
            npmDependencies: [],
            npmDevDependencies: [],
            nodeVersion: null,
            supportStatus: [],
        );
    }

    public function toArray(): array
    {
        return [
            'found' => $this->found,
            'name' => $this->name,
            'type' => $this->type,
            'require' => $this->require,
            'requireDev' => $this->requireDev,
            'installed' => $this->installed,
            'installedMap' => $this->installedMap,
            'outdated' => $this->outdated,
            'autoload' => $this->autoload,
            'licensesSummary' => $this->licensesSummary,
            'phpVersion' => $this->phpVersion,
            'phpExtensions' => $this->phpExtensions,
            'npmDependencies' => $this->npmDependencies,
            'npmDevDependencies' => $this->npmDevDependencies,
            'nodeVersion' => $this->nodeVersion,
            'supportStatus' => $this->supportStatus,
        ];
    }
}