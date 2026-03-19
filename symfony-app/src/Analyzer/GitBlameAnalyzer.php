<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Result\ProjectResult;

class GitBlameAnalyzer
{
    /**
     * Analyze git blame for all files and return author statistics.
     *
     * @return array<string, array{
     *     name: string,
     *     email: string,
     *     loc: int,
     *     files: int,
     *     classes: int,
     *     methods: int,
     *     totalMi: float,
     *     totalCcn: int,
     *     avgMi: float,
     *     avgCcn: float,
     *     miRating: string,
     *     ccnRating: string,
     *     score: float,
     *     scoreRating: string
     * }>
     */
    public function analyze(ProjectResult $result): array
    {
        $sourcePath = $result->sourcePath;

        // Check if it's a git repository
        if (!$this->isGitRepository($sourcePath)) {
            return [];
        }

        $authorStats = [];

        foreach ($result->files as $file) {
            if ($file->hasErrors) {
                continue;
            }

            $filePath = $file->path;
            $blameData = $this->getBlameData($filePath, $sourcePath);

            if (empty($blameData)) {
                continue;
            }

            // Get the primary author (most lines)
            $primaryAuthor = $this->getPrimaryAuthor($blameData);

            if ($primaryAuthor === null) {
                continue;
            }

            $authorKey = $this->normalizeAuthorKey($primaryAuthor['email']);

            if (!isset($authorStats[$authorKey])) {
                $authorStats[$authorKey] = [
                    'name' => $primaryAuthor['name'],
                    'email' => $primaryAuthor['email'],
                    'loc' => 0,
                    'files' => 0,
                    'classes' => 0,
                    'methods' => 0,
                    'totalMi' => 0.0,
                    'totalCcn' => 0,
                    'miWeightedSum' => 0.0,
                    'ccnWeightedSum' => 0,
                    'locForMi' => 0,
                ];
            }

            $fileLoc = $file->loc['loc'] ?? 0;
            $fileMi = $file->mi;
            $fileMaxCcn = $file->ccn['summary']['maxCcn'] ?? 0;

            $authorStats[$authorKey]['loc'] += $fileLoc;
            $authorStats[$authorKey]['files']++;
            $authorStats[$authorKey]['classes'] += count($file->classes);
            $authorStats[$authorKey]['methods'] += array_sum(array_map(fn($c) => $c->methodCount, $file->classes));
            $authorStats[$authorKey]['miWeightedSum'] += $fileMi * $fileLoc;
            $authorStats[$authorKey]['locForMi'] += $fileLoc;
            $authorStats[$authorKey]['totalCcn'] += $fileMaxCcn;
        }

        // Calculate averages and scores
        foreach ($authorStats as $key => &$stats) {
            // Weighted average MI (by LOC)
            $stats['avgMi'] = $stats['locForMi'] > 0
                ? $stats['miWeightedSum'] / $stats['locForMi']
                : 0;

            // Average CCN per file
            $stats['avgCcn'] = $stats['files'] > 0
                ? $stats['totalCcn'] / $stats['files']
                : 0;

            // Calculate ratings
            $stats['miRating'] = $this->getMiRating($stats['avgMi']);
            $stats['ccnRating'] = $this->getCcnRating((int) round($stats['avgCcn']));

            // Score: combines MI (higher is better) and CCN (lower is better)
            // Formula: score = (MI / 100) * 50 + (1 - min(CCN/20, 1)) * 50
            // This gives a score from 0 to 100
            $miScore = min($stats['avgMi'] / 100, 1) * 50;
            $ccnScore = (1 - min($stats['avgCcn'] / 20, 1)) * 50;
            $stats['score'] = $miScore + $ccnScore;
            $stats['scoreRating'] = $this->getScoreRating($stats['score']);

            // Clean up temporary fields
            unset($stats['miWeightedSum'], $stats['locForMi'], $stats['totalCcn']);
        }

        // Sort by score descending
        uasort($authorStats, fn($a, $b) => $b['score'] <=> $a['score']);

        return $authorStats;
    }

    private function isGitRepository(string $path): bool
    {
        // Walk up the directory tree to find .git
        $currentPath = $path;
        while ($currentPath !== '/' && $currentPath !== '') {
            if (is_dir($currentPath . '/.git')) {
                return true;
            }
            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath) {
                break;
            }
            $currentPath = $parentPath;
        }
        return false;
    }

    private function getGitRoot(string $path): ?string
    {
        $currentPath = $path;
        while ($currentPath !== '/' && $currentPath !== '') {
            if (is_dir($currentPath . '/.git')) {
                return $currentPath;
            }
            $parentPath = dirname($currentPath);
            if ($parentPath === $currentPath) {
                break;
            }
            $currentPath = $parentPath;
        }
        return null;
    }

    /**
     * @return array<array{name: string, email: string, lines: int}>
     */
    private function getBlameData(string $filePath, string $sourcePath): array
    {
        $gitRoot = $this->getGitRoot($sourcePath);
        if ($gitRoot === null) {
            return [];
        }

        // Make file path relative to git root
        $relativePath = $filePath;
        if (str_starts_with($filePath, $gitRoot)) {
            $relativePath = substr($filePath, strlen($gitRoot) + 1);
        }

        // Add safe.directory to handle Docker volume permissions
        // Then run git blame with porcelain format for easy parsing
        $command = sprintf(
            'git config --global --add safe.directory %s 2>/dev/null; cd %s && git blame --line-porcelain %s 2>/dev/null | grep "^author\\|^author-mail"',
            escapeshellarg($gitRoot),
            escapeshellarg($gitRoot),
            escapeshellarg($relativePath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || empty($output)) {
            return [];
        }

        // Parse output to count lines per author
        $authorCounts = [];
        $currentAuthor = null;
        $currentEmail = null;

        foreach ($output as $line) {
            if (str_starts_with($line, 'author ')) {
                $currentAuthor = substr($line, 7);
            } elseif (str_starts_with($line, 'author-mail ')) {
                $currentEmail = trim(substr($line, 12), '<>');

                if ($currentAuthor !== null && $currentEmail !== null) {
                    $key = $currentEmail;
                    if (!isset($authorCounts[$key])) {
                        $authorCounts[$key] = [
                            'name' => $currentAuthor,
                            'email' => $currentEmail,
                            'lines' => 0,
                        ];
                    }
                    $authorCounts[$key]['lines']++;
                }
            }
        }

        return array_values($authorCounts);
    }

    /**
     * @param array<array{name: string, email: string, lines: int}> $blameData
     * @return array{name: string, email: string, lines: int}|null
     */
    private function getPrimaryAuthor(array $blameData): ?array
    {
        if (empty($blameData)) {
            return null;
        }

        // Return the author with the most lines
        usort($blameData, fn($a, $b) => $b['lines'] <=> $a['lines']);
        return $blameData[0];
    }

    private function normalizeAuthorKey(string $email): string
    {
        return strtolower(trim($email));
    }

    private function getMiRating(float $mi): string
    {
        return match (true) {
            $mi >= 85 => 'A',
            $mi >= 65 => 'B',
            $mi >= 40 => 'C',
            $mi >= 20 => 'D',
            default => 'F',
        };
    }

    private function getCcnRating(int $ccn): string
    {
        return match (true) {
            $ccn <= 4 => 'A',
            $ccn <= 7 => 'B',
            $ccn <= 10 => 'C',
            $ccn <= 15 => 'D',
            default => 'F',
        };
    }

    private function getScoreRating(float $score): string
    {
        return match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 50 => 'C',
            $score >= 30 => 'D',
            default => 'F',
        };
    }
}
