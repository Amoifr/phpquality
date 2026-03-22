<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Analyzer\ProjectAnalyzer;
use App\Analyzer\ProjectType\ProjectTypeDetector;
use App\Analyzer\ProjectType\ProjectTypeInterface;
use App\Analyzer\Result\ProjectResult;
use App\Command\AnalyzeCommand;
use App\Report\ConsoleReportGenerator;
use App\Report\HtmlReportGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class AnalyzeCommandTest extends TestCase
{
    private ProjectAnalyzer $analyzer;
    private ProjectTypeDetector $typeDetector;
    private HtmlReportGenerator $htmlGenerator;
    private ConsoleReportGenerator $consoleGenerator;
    private AnalyzeCommand $command;
    private CommandTester $commandTester;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->analyzer = $this->createMock(ProjectAnalyzer::class);
        $this->typeDetector = $this->createMock(ProjectTypeDetector::class);
        $this->htmlGenerator = $this->createMock(HtmlReportGenerator::class);
        $this->consoleGenerator = $this->createMock(ConsoleReportGenerator::class);

        $this->typeDetector->method('getTypeNames')->willReturn(['php', 'symfony', 'laravel']);

        $this->command = new AnalyzeCommand(
            $this->analyzer,
            $this->typeDetector,
            $this->htmlGenerator,
            $this->consoleGenerator
        );

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);

        $this->fixturesPath = sys_get_temp_dir() . '/phpquality_cmd_test_' . uniqid();
        mkdir($this->fixturesPath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesPath);
    }

    public function testListTypes(): void
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getLabel')->willReturn('PHP');
        $projectType->method('getDescription')->willReturn('Generic PHP project');

        $this->typeDetector->method('getAvailableTypes')->willReturn([
            'php' => $projectType,
        ]);

        $this->commandTester->execute(['--list-types' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Available Project Types', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testListLanguages(): void
    {
        $this->commandTester->execute(['--list-langs' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Available Report Languages', $output);
        $this->assertStringContainsString('English', $output);
        $this->assertStringContainsString('Français', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testFailsWithInvalidSource(): void
    {
        $this->commandTester->execute([
            '--source' => '/nonexistent/path',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid source directory', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testFailsWithoutSource(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Invalid source directory', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testFailsWithInvalidProjectType(): void
    {
        $this->typeDetector->method('getProjectType')
            ->willThrowException(new \InvalidArgumentException('Unknown type: invalid'));

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--type' => 'invalid',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unknown type', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testSuccessfulAnalysis(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->expects($this->once())
            ->method('analyze')
            ->willReturn($projectResult);

        $this->consoleGenerator->expects($this->once())
            ->method('generate');

        $this->htmlGenerator->expects($this->once())
            ->method('generate');

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testNoHtmlOption(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);
        $this->consoleGenerator->expects($this->once())->method('generate');

        $this->htmlGenerator->expects($this->never())
            ->method('generate');

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--no-html' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testJsonOutput(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $jsonPath = $this->fixturesPath . '/output.json';

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--json' => $jsonPath,
            '--no-html' => true,
        ]);

        $this->assertFileExists($jsonPath);
        $content = json_decode(file_get_contents($jsonPath), true);
        $this->assertIsArray($content);
    }

    public function testFailOnViolationWithViolations(): void
    {
        $projectResult = $this->createProjectResult(['violations' => ['layer' => 5]]);

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--fail-on-violation' => true,
            '--no-html' => true,
        ]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testFailOnViolationWithoutViolations(): void
    {
        $projectResult = $this->createProjectResult(['violations' => []]);

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--fail-on-violation' => true,
            '--no-html' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testCustomReportPath(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $reportPath = $this->fixturesPath . '/custom-report';

        $this->htmlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                $projectResult,
                $reportPath,
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--report-html' => $reportPath,
        ]);
    }

    public function testLanguageOption(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $this->htmlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                $projectResult,
                $this->anything(),
                'fr',
                $this->anything(),
                $this->anything()
            );

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--lang' => 'fr',
        ]);
    }

    public function testGitBlameOption(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $this->htmlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                $projectResult,
                $this->anything(),
                $this->anything(),
                true,
                $this->anything()
            );

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--git-blame' => true,
        ]);
    }

    public function testProjectNameOption(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $this->htmlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                $projectResult,
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'My Project'
            );

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--project-name' => 'My Project',
        ]);
    }

    public function testCoverageOption(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $coveragePath = $this->fixturesPath . '/coverage.xml';
        file_put_contents($coveragePath, '<?xml version="1.0"?><coverage/>');

        $this->analyzer->expects($this->once())
            ->method('setCoveragePath')
            ->with($coveragePath);

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--coverage' => $coveragePath,
            '--no-html' => true,
        ]);
    }

    public function testCoverageOptionWithNonExistentFile(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->method('analyze')->willReturn($projectResult);

        $this->analyzer->expects($this->once())
            ->method('setCoveragePath')
            ->with(null);

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--coverage' => '/nonexistent/coverage.xml',
            '--no-html' => true,
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Coverage file not found', $output);
    }

    public function testExcludeOption(): void
    {
        $projectResult = $this->createProjectResult();

        $this->analyzer->expects($this->once())
            ->method('analyze')
            ->with(
                $this->fixturesPath,
                'auto',
                ['vendor', 'node_modules'],
                $this->anything()
            )
            ->willReturn($projectResult);

        $this->commandTester->execute([
            '--source' => $this->fixturesPath,
            '--exclude' => ['vendor', 'node_modules'],
            '--no-html' => true,
        ]);
    }

    private function createProjectResult(array $summary = []): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        return new ProjectResult(
            sourcePath: $this->fixturesPath,
            projectType: $projectType,
            files: [],
            summary: array_merge(['thresholds' => [], 'violations' => []], $summary),
            analyzedAt: new \DateTimeImmutable()
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
