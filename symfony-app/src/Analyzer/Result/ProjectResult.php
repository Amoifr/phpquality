<?php

declare(strict_types=1);

namespace App\Analyzer\Result;

use App\Analyzer\ProjectType\ProjectTypeInterface;

class ProjectResult
{
    /**
     * @param array<FileResult> $files
     */
    public function __construct(
        public readonly string $sourcePath,
        public readonly ProjectTypeInterface $projectType,
        public readonly array $files,
        public readonly array $summary,
        public readonly \DateTimeImmutable $analyzedAt,
        public readonly ?DependenciesResult $dependencies = null,
        public readonly ?ArchitectureResult $architecture = null,
        public readonly ?CoverageResult $coverage = null,
    ) {}

    public function getFileCount(): int
    {
        return count($this->files);
    }

    public function getClassCount(): int
    {
        return array_sum(array_map(fn($f) => count($f->classes), $this->files));
    }

    public function getMethodCount(): int
    {
        $count = 0;
        foreach ($this->files as $file) {
            foreach ($file->classes as $class) {
                $count += count($class->methods);
            }
        }
        return $count;
    }

    public function getTotalLoc(): int
    {
        return $this->summary['totalLoc'] ?? 0;
    }

    public function getAverageMi(): float
    {
        return $this->summary['averageMi'] ?? 0;
    }

    public function getAverageCcn(): float
    {
        return $this->summary['averageCcn'] ?? 0;
    }

    public function getAverageLcom(): float
    {
        return $this->summary['averageLcom'] ?? 0;
    }

    /**
     * Get files sorted by a metric (worst first)
     *
     * @return array<FileResult>
     */
    public function getWorstFiles(string $metric, int $limit = 10): array
    {
        $files = $this->files;

        usort($files, function (FileResult $a, FileResult $b) use ($metric) {
            return match ($metric) {
                'ccn' => $b->getMaxCcn() <=> $a->getMaxCcn(),
                'mi' => $a->mi <=> $b->mi, // Lower MI is worse
                'loc' => $b->getTotalLoc() <=> $a->getTotalLoc(),
                default => 0,
            };
        });

        return array_slice($files, 0, $limit);
    }

    /**
     * Get all classes with their results
     *
     * @return array<ClassResult>
     */
    public function getAllClasses(): array
    {
        $classes = [];
        foreach ($this->files as $file) {
            foreach ($file->classes as $class) {
                $classes[] = $class;
            }
        }
        return $classes;
    }

    /**
     * Get classes grouped by category
     *
     * @return array<string, array<ClassResult>>
     */
    public function getClassesByCategory(): array
    {
        $grouped = [];
        foreach ($this->getAllClasses() as $class) {
            $category = $class->category ?? 'Other';
            $grouped[$category][] = $class;
        }
        ksort($grouped);
        return $grouped;
    }

    public function toArray(): array
    {
        return [
            'sourcePath' => $this->sourcePath,
            'projectType' => $this->projectType->getName(),
            'analyzedAt' => $this->analyzedAt->format('Y-m-d H:i:s'),
            'summary' => $this->summary,
            'files' => array_map(fn($f) => $f->toArray(), $this->files),
            'dependencies' => $this->dependencies?->toArray(),
            'architecture' => $this->architecture?->toArray(),
            'coverage' => $this->coverage?->toArray(),
        ];
    }
}
