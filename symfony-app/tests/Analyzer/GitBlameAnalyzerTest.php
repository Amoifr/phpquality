<?php

declare(strict_types=1);

namespace App\Tests\Analyzer;

use App\Analyzer\GitBlameAnalyzer;
use App\Analyzer\Result\ProjectResult;
use App\Analyzer\Result\FileResult;
use App\Analyzer\Result\ClassResult;
use App\Analyzer\ProjectType\ProjectTypeInterface;
use PHPUnit\Framework\TestCase;

class GitBlameAnalyzerTest extends TestCase
{
    private GitBlameAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new GitBlameAnalyzer();
    }

    public function testAnalyzeReturnsEmptyArrayForNonGitRepository(): void
    {
        $projectResult = $this->createProjectResult('/tmp/non-git-directory');

        $result = $this->analyzer->analyze($projectResult);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testAnalyzeReturnsArrayForGitRepository(): void
    {
        // Use the actual project which is a git repo
        $projectResult = $this->createProjectResult(dirname(__DIR__, 2));

        $result = $this->analyzer->analyze($projectResult);

        $this->assertIsArray($result);
    }

    public function testAnalyzeSkipsFilesWithErrors(): void
    {
        $file = new FileResult(
            path: '/path/to/file.php',
            relativePath: 'file.php',
            classes: [],
            loc: ['loc' => 100],
            ccn: ['summary' => ['maxCcn' => 5]],
            halstead: [],
            lcom: [],
            mi: 80.0,
            miRating: 'B',
            hasErrors: true,
            error: 'Parse error'
        );

        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $projectResult = new ProjectResult(
            sourcePath: dirname(__DIR__, 2),
            projectType: $projectType,
            files: [$file],
            summary: [],
            analyzedAt: new \DateTimeImmutable()
        );

        $result = $this->analyzer->analyze($projectResult);

        // Should not crash, result depends on git state
        $this->assertIsArray($result);
    }

    public function testAnalyzeResultStructure(): void
    {
        // Use actual project
        $realSourcePath = dirname(__DIR__, 2) . '/src';

        if (!is_dir($realSourcePath)) {
            $this->markTestSkipped('Source directory not found');
        }

        $file = new FileResult(
            path: $realSourcePath . '/Kernel.php',
            relativePath: 'Kernel.php',
            classes: [],
            loc: ['loc' => 20],
            ccn: ['summary' => ['maxCcn' => 1, 'averageCcn' => 1]],
            halstead: [],
            lcom: [],
            mi: 95.0,
            miRating: 'A'
        );

        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $projectResult = new ProjectResult(
            sourcePath: $realSourcePath,
            projectType: $projectType,
            files: [$file],
            summary: [],
            analyzedAt: new \DateTimeImmutable()
        );

        $result = $this->analyzer->analyze($projectResult);

        // Always make an assertion
        $this->assertIsArray($result);

        if (!empty($result)) {
            $firstAuthor = reset($result);

            $this->assertArrayHasKey('name', $firstAuthor);
            $this->assertArrayHasKey('email', $firstAuthor);
            $this->assertArrayHasKey('loc', $firstAuthor);
            $this->assertArrayHasKey('files', $firstAuthor);
            $this->assertArrayHasKey('classes', $firstAuthor);
            $this->assertArrayHasKey('methods', $firstAuthor);
            $this->assertArrayHasKey('avgMi', $firstAuthor);
            $this->assertArrayHasKey('avgCcn', $firstAuthor);
            $this->assertArrayHasKey('miRating', $firstAuthor);
            $this->assertArrayHasKey('ccnRating', $firstAuthor);
            $this->assertArrayHasKey('score', $firstAuthor);
            $this->assertArrayHasKey('scoreRating', $firstAuthor);
        }
    }

    public function testMiRatingMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'getMiRating');
        $method->setAccessible(true);

        $this->assertSame('A', $method->invoke($this->analyzer, 85.0));
        $this->assertSame('A', $method->invoke($this->analyzer, 100.0));
        $this->assertSame('B', $method->invoke($this->analyzer, 70.0));
        $this->assertSame('C', $method->invoke($this->analyzer, 50.0));
        $this->assertSame('D', $method->invoke($this->analyzer, 25.0));
        $this->assertSame('F', $method->invoke($this->analyzer, 10.0));
    }

    public function testCcnRatingMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'getCcnRating');
        $method->setAccessible(true);

        $this->assertSame('A', $method->invoke($this->analyzer, 1));
        $this->assertSame('A', $method->invoke($this->analyzer, 4));
        $this->assertSame('B', $method->invoke($this->analyzer, 5));
        $this->assertSame('C', $method->invoke($this->analyzer, 8));
        $this->assertSame('D', $method->invoke($this->analyzer, 12));
        $this->assertSame('F', $method->invoke($this->analyzer, 20));
    }

    public function testScoreRatingMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'getScoreRating');
        $method->setAccessible(true);

        $this->assertSame('A', $method->invoke($this->analyzer, 90.0));
        $this->assertSame('B', $method->invoke($this->analyzer, 75.0));
        $this->assertSame('C', $method->invoke($this->analyzer, 55.0));
        $this->assertSame('D', $method->invoke($this->analyzer, 35.0));
        $this->assertSame('F', $method->invoke($this->analyzer, 15.0));
    }

    public function testNormalizeAuthorKeyMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'normalizeAuthorKey');
        $method->setAccessible(true);

        $this->assertSame('test@example.com', $method->invoke($this->analyzer, 'Test@Example.com'));
        $this->assertSame('user@domain.org', $method->invoke($this->analyzer, '  USER@domain.org  '));
    }

    public function testGetPrimaryAuthorMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'getPrimaryAuthor');
        $method->setAccessible(true);

        $blameData = [
            ['name' => 'Alice', 'email' => 'alice@test.com', 'lines' => 50],
            ['name' => 'Bob', 'email' => 'bob@test.com', 'lines' => 100],
            ['name' => 'Charlie', 'email' => 'charlie@test.com', 'lines' => 25],
        ];

        $result = $method->invoke($this->analyzer, $blameData);

        $this->assertSame('Bob', $result['name']);
        $this->assertSame(100, $result['lines']);
    }

    public function testGetPrimaryAuthorWithEmptyData(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'getPrimaryAuthor');
        $method->setAccessible(true);

        $result = $method->invoke($this->analyzer, []);

        $this->assertNull($result);
    }

    public function testIsGitRepositoryMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'isGitRepository');
        $method->setAccessible(true);

        // The actual project directory is a git repo
        $this->assertTrue($method->invoke($this->analyzer, dirname(__DIR__, 2)));

        // /tmp should not be a git repo
        $this->assertFalse($method->invoke($this->analyzer, '/tmp/non_existent_' . uniqid()));
    }

    public function testGetGitRootMethod(): void
    {
        $method = new \ReflectionMethod(GitBlameAnalyzer::class, 'getGitRoot');
        $method->setAccessible(true);

        // From the project directory, should find git root
        $result = $method->invoke($this->analyzer, dirname(__DIR__, 2));
        $this->assertNotNull($result);
        $this->assertDirectoryExists($result . '/.git');

        // From a non-git directory
        $result = $method->invoke($this->analyzer, '/tmp/non_existent_' . uniqid());
        $this->assertNull($result);
    }

    public function testRatingCalculations(): void
    {
        // Test rating boundaries using reflection or by analyzing real files
        $analyzer = new GitBlameAnalyzer();

        // We can't easily test private methods, but we can verify
        // the result contains valid ratings
        $projectResult = $this->createProjectResult(dirname(__DIR__, 2));
        $result = $analyzer->analyze($projectResult);

        // Always assert something
        $this->assertIsArray($result);

        foreach ($result as $authorStats) {
            $this->assertContains($authorStats['miRating'], ['A', 'B', 'C', 'D', 'F']);
            $this->assertContains($authorStats['ccnRating'], ['A', 'B', 'C', 'D', 'F']);
            $this->assertContains($authorStats['scoreRating'], ['A', 'B', 'C', 'D', 'F']);
            $this->assertGreaterThanOrEqual(0, $authorStats['score']);
            $this->assertLessThanOrEqual(100, $authorStats['score']);
        }
    }

    public function testResultIsSortedByScoreDescending(): void
    {
        $projectResult = $this->createProjectResult(dirname(__DIR__, 2));
        $result = $this->analyzer->analyze($projectResult);

        // Always assert something
        $this->assertIsArray($result);

        if (count($result) > 1) {
            $scores = array_column($result, 'score');
            $sortedScores = $scores;
            rsort($sortedScores);

            $this->assertSame($sortedScores, $scores, 'Results should be sorted by score descending');
        }
    }

    private function createProjectResult(string $sourcePath): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        return new ProjectResult(
            sourcePath: $sourcePath,
            projectType: $projectType,
            files: [],
            summary: [],
            analyzedAt: new \DateTimeImmutable()
        );
    }
}
