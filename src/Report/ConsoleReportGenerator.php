<?php

declare(strict_types=1);

namespace PhpQuality\Report;

use PhpQuality\Analyzer\Result\ProjectResult;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConsoleReportGenerator implements ReportGeneratorInterface
{
    public function generate(ProjectResult $result, mixed $output): void
    {
        if (!$output instanceof SymfonyStyle) {
            throw new \InvalidArgumentException('Output must be SymfonyStyle');
        }

        $io = $output;
        $summary = $result->summary;

        // Project info
        $io->section('Project Overview');
        $io->horizontalTable(
            ['Project Type', 'Files', 'Classes', 'Methods', 'Lines of Code'],
            [[
                sprintf('<info>%s</info>', $summary['projectTypeLabel']),
                $summary['totalFiles'],
                $summary['totalClasses'],
                $summary['totalMethods'],
                sprintf('%s (logical: %s)', $summary['totalLoc'], $summary['totalLloc']),
            ]]
        );

        // Metrics summary
        $io->section('Metrics Summary');
        $io->horizontalTable(
            ['Metric', 'Value', 'Rating', 'Violations'],
            [
                [
                    'Maintainability Index',
                    sprintf('%.2f', $summary['averageMi']),
                    $this->formatRating($summary['ratings']['mi']),
                    $this->formatViolations($summary['violations']['mi']),
                ],
                [
                    'Cyclomatic Complexity',
                    sprintf('%.2f (max: %d)', $summary['averageCcn'], $summary['maxCcn']),
                    $this->formatRating($summary['ratings']['ccn']),
                    $this->formatViolations($summary['violations']['ccn']),
                ],
                [
                    'Lack of Cohesion',
                    sprintf('%.4f', $summary['averageLcom']),
                    $this->formatRating($summary['ratings']['lcom']),
                    $this->formatViolations($summary['violations']['lcom']),
                ],
            ]
        );

        // Thresholds
        $thresholds = $summary['thresholds'];
        $io->text(sprintf(
            'Thresholds: CCN > %d, LCOM > %.1f, MI < %d',
            $thresholds['ccn'],
            $thresholds['lcom'],
            $thresholds['mi']
        ));

        // Worst files by CCN
        $worstFiles = $result->getWorstFiles('ccn', 5);
        if (!empty($worstFiles)) {
            $io->section('Top 5 Most Complex Files');
            $rows = [];
            foreach ($worstFiles as $file) {
                if ($file->getMaxCcn() > 0) {
                    $rows[] = [
                        $file->relativePath,
                        $file->getMaxCcn(),
                        sprintf('%.2f', $file->mi),
                    ];
                }
            }
            if (!empty($rows)) {
                $io->table(['File', 'Max CCN', 'MI'], $rows);
            }
        }

        // Classes by category
        $classesByCategory = $result->getClassesByCategory();
        if (!empty($classesByCategory)) {
            $io->section('Classes by Category');
            $categoryRows = [];
            foreach ($classesByCategory as $category => $classes) {
                $categoryRows[] = [$category, count($classes)];
            }
            $io->table(['Category', 'Count'], $categoryRows);
        }

        // Errors
        if ($summary['errors'] > 0) {
            $io->warning(sprintf('%d files could not be analyzed', $summary['errors']));
        }
    }

    private function formatRating(string $rating): string
    {
        $colors = [
            'A' => 'green',
            'B' => 'green',
            'C' => 'yellow',
            'D' => 'red',
            'F' => 'red',
        ];

        $color = $colors[$rating] ?? 'white';
        return sprintf('<%s>%s</%s>', $color, $rating, $color);
    }

    private function formatViolations(int $count): string
    {
        if ($count === 0) {
            return '<green>0</green>';
        }
        return sprintf('<red>%d</red>', $count);
    }
}
