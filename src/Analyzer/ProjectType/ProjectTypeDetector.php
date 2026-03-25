<?php

declare(strict_types=1);

namespace PhpQuality\Analyzer\ProjectType;

/**
 * Detects the project type based on file structure and composer.json
 */
class ProjectTypeDetector
{
    /** @var array<ProjectTypeInterface> */
    private array $projectTypes = [];

    /**
     * @param iterable<ProjectTypeInterface> $projectTypes
     */
    public function __construct(iterable $projectTypes)
    {
        foreach ($projectTypes as $projectType) {
            $this->projectTypes[$projectType->getName()] = $projectType;
        }
    }

    /**
     * Detect the project type automatically
     *
     * @return ProjectTypeInterface
     */
    public function detect(string $projectPath): ProjectTypeInterface
    {
        $scores = [];

        foreach ($this->projectTypes as $name => $projectType) {
            $score = $projectType->detect($projectPath);
            if ($score > 0) {
                $scores[$name] = $score;
            }
        }

        if (empty($scores)) {
            return $this->getProjectType('php');
        }

        // Return the type with highest score
        arsort($scores);
        $bestMatch = array_key_first($scores);

        return $this->projectTypes[$bestMatch];
    }

    /**
     * Get a specific project type by name
     *
     * @throws \InvalidArgumentException
     */
    public function getProjectType(string $name): ProjectTypeInterface
    {
        if (!isset($this->projectTypes[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown project type "%s". Available types: %s',
                $name,
                implode(', ', array_keys($this->projectTypes))
            ));
        }

        return $this->projectTypes[$name];
    }

    /**
     * Get all available project types
     *
     * @return array<string, ProjectTypeInterface>
     */
    public function getAvailableTypes(): array
    {
        return $this->projectTypes;
    }

    /**
     * Get all project type names
     *
     * @return array<string>
     */
    public function getTypeNames(): array
    {
        return array_keys($this->projectTypes);
    }
}
