<?php

declare(strict_types=1);

namespace App\Analyzer\ProjectType;

/**
 * Interface for project type definitions
 * Each project type defines specific patterns, thresholds and rules
 */
interface ProjectTypeInterface
{
    /**
     * Get the unique identifier for this project type
     */
    public function getName(): string;

    /**
     * Get the display label
     */
    public function getLabel(): string;

    /**
     * Get a description of this project type
     */
    public function getDescription(): string;

    /**
     * Check if a project matches this type based on file structure
     *
     * @param string $projectPath Root path of the project
     * @return int Detection confidence score (0-100)
     */
    public function detect(string $projectPath): int;

    /**
     * Get paths to exclude from analysis
     *
     * @return array<string>
     */
    public function getExcludedPaths(): array;

    /**
     * Get specific architectural patterns to detect
     *
     * @return array<string, array{pattern: string, description: string}>
     */
    public function getArchitecturalPatterns(): array;

    /**
     * Get recommended thresholds for this project type
     *
     * @return array{ccn: int, lcom: float, mi: int}
     */
    public function getRecommendedThresholds(): array;

    /**
     * Get class naming conventions for categorization
     *
     * @return array<string, string> Pattern => Category
     */
    public function getClassCategories(): array;
}
