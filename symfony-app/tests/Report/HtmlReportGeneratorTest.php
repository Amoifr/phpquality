<?php

declare(strict_types=1);

namespace App\Tests\Report;

use App\Analyzer\GitBlameAnalyzer;
use App\Analyzer\ProjectType\ProjectTypeInterface;
use App\Analyzer\Result\ArchitectureResult;
use App\Analyzer\Result\ClassResult;
use App\Analyzer\Result\CoverageResult;
use App\Analyzer\Result\FileResult;
use App\Analyzer\Result\MethodResult;
use App\Analyzer\Result\ProjectResult;
use App\Report\HtmlReportGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

interface TestableTranslator extends TranslatorInterface, LocaleAwareInterface {}

class HtmlReportGeneratorTest extends TestCase
{
    private HtmlReportGenerator $generator;
    private Environment $twig;
    private Filesystem $filesystem;
    private TranslatorInterface $translator;
    private GitBlameAnalyzer $gitBlameAnalyzer;
    private string $outputPath;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->translator = $this->createMock(TestableTranslator::class);
        $this->gitBlameAnalyzer = $this->createMock(GitBlameAnalyzer::class);

        $this->generator = new HtmlReportGenerator(
            $this->twig,
            $this->filesystem,
            $this->translator,
            $this->gitBlameAnalyzer
        );

        $this->outputPath = sys_get_temp_dir() . '/phpquality_html_test_' . uniqid();
    }

    public function testGenerateCreatesOutputDirectory(): void
    {
        $projectResult = $this->createProjectResult();

        $this->filesystem->expects($this->once())
            ->method('mkdir')
            ->with($this->outputPath);

        $this->twig->method('render')->willReturn('<html></html>');

        $this->generator->generate($projectResult, $this->outputPath);
    }

    public function testGenerateRendersMultiplePages(): void
    {
        $projectResult = $this->createProjectResult();

        $this->twig->expects($this->atLeast(8))
            ->method('render')
            ->willReturn('<html></html>');

        $this->filesystem->method('mkdir');
        $this->filesystem->method('copy');
        $this->filesystem->method('dumpFile');

        $this->generator->generate($projectResult, $this->outputPath);
    }

    public function testGenerateWritesFiles(): void
    {
        $projectResult = $this->createProjectResult();

        $this->twig->method('render')->willReturn('<html></html>');

        $this->filesystem->expects($this->atLeast(8))
            ->method('dumpFile');

        $this->generator->generate($projectResult, $this->outputPath);
    }

    public function testGenerateUsesCorrectLanguage(): void
    {
        $projectResult = $this->createProjectResult();

        $this->translator->expects($this->atLeastOnce())
            ->method('setLocale')
            ->with('fr');

        $this->twig->method('render')->willReturn('<html></html>');

        $this->generator->generate($projectResult, $this->outputPath, 'fr');
    }

    public function testGenerateFallsBackToEnglishForUnsupportedLanguage(): void
    {
        $projectResult = $this->createProjectResult();

        $this->translator->expects($this->atLeastOnce())
            ->method('setLocale')
            ->with('en');

        $this->twig->method('render')->willReturn('<html></html>');

        $this->generator->generate($projectResult, $this->outputPath, 'invalid');
    }

    public function testGenerateThrowsExceptionForInvalidOutputPath(): void
    {
        $projectResult = $this->createProjectResult();

        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate($projectResult, 123);
    }

    public function testGenerateWithGitBlameEnabled(): void
    {
        $projectResult = $this->createProjectResult();

        $this->gitBlameAnalyzer->expects($this->once())
            ->method('analyze')
            ->with($projectResult)
            ->willReturn([]);

        $this->twig->method('render')->willReturn('<html></html>');

        $this->generator->generate($projectResult, $this->outputPath, 'en', true);
    }

    public function testGenerateWithProjectName(): void
    {
        $projectResult = $this->createProjectResult();

        $this->twig->method('render')
            ->with(
                $this->anything(),
                $this->callback(function ($data) {
                    return isset($data['projectName']) && $data['projectName'] === 'My Project';
                })
            )
            ->willReturn('<html></html>');

        $this->generator->generate($projectResult, $this->outputPath, 'en', false, 'My Project');
    }

    public function testGenerateWithDependencies(): void
    {
        $projectResult = $this->createProjectResultWithDependencies();

        $renderCount = 0;
        $this->twig->method('render')
            ->willReturnCallback(function ($template) use (&$renderCount) {
                $renderCount++;
                return '<html></html>';
            });

        $this->generator->generate($projectResult, $this->outputPath);

        // Should render dependencies.html when dependencies exist
        $this->assertGreaterThan(8, $renderCount);
    }

    public function testGenerateWithArchitecture(): void
    {
        $projectResult = $this->createProjectResultWithArchitecture();

        $renderCount = 0;
        $this->twig->method('render')
            ->willReturnCallback(function ($template) use (&$renderCount) {
                $renderCount++;
                return '<html></html>';
            });

        $this->generator->generate($projectResult, $this->outputPath);

        // Should render architecture.html when architecture exists
        $this->assertGreaterThan(8, $renderCount);
    }

    public function testGenerateWithCoverage(): void
    {
        $projectResult = $this->createProjectResultWithCoverage();

        $coveragePageRendered = false;
        $this->twig->method('render')
            ->willReturnCallback(function ($template, $data) use (&$coveragePageRendered) {
                if (str_contains($template, 'coverage')) {
                    $coveragePageRendered = true;
                }
                return '<html></html>';
            });

        $this->generator->generate($projectResult, $this->outputPath);

        $this->assertTrue($coveragePageRendered);
    }

    public function testGenerateWithClassesAndMethods(): void
    {
        $method = new MethodResult(
            name: 'testMethod',
            className: 'TestClass',
            startLine: 10,
            endLine: 20,
            ccn: 5,
            ccnRating: 'B',
            mi: 75.0,
            miRating: 'B',
            loc: 10
        );

        $class = new ClassResult(
            name: 'TestClass',
            namespace: 'App\Test',
            filePath: '/path/test.php',
            startLine: 1,
            endLine: 50,
            methods: [$method],
            lcom: 0.2,
            lcomRating: 'A',
            totalLoc: 50,
            methodCount: 1,
            propertyCount: 2,
            maxCcn: 5,
            avgCcn: 5.0,
            mi: 75.0,
            miRating: 'B',
            category: 'Service'
        );

        $file = new FileResult(
            path: '/path/test.php',
            relativePath: 'test.php',
            classes: [$class],
            loc: ['loc' => 50, 'lloc' => 40, 'cloc' => 10],
            ccn: ['summary' => ['maxCcn' => 5, 'averageCcn' => 5]],
            halstead: ['volume' => 100, 'difficulty' => 5, 'effort' => 500, 'operators' => ['+' => 10]],
            lcom: ['classes' => []],
            mi: 75.0,
            miRating: 'B'
        );

        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $projectResult = new ProjectResult(
            sourcePath: '/path',
            projectType: $projectType,
            files: [$file],
            summary: ['thresholds' => []],
            analyzedAt: new \DateTimeImmutable()
        );

        $this->twig->method('render')->willReturn('<html></html>');

        $this->generator->generate($projectResult, $this->outputPath);

        // Test passes if no exceptions
        $this->assertTrue(true);
    }

    private function createProjectResult(): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        return new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: [],
            summary: ['thresholds' => [], 'violations' => []],
            analyzedAt: new \DateTimeImmutable()
        );
    }

    private function createProjectResultWithDependencies(): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $dependencies = new \App\Analyzer\Result\DependenciesResult(
            found: true,
            name: 'test/project',
            type: 'composer',
            require: ['php' => '^8.1'],
            requireDev: [],
            installed: [],
            installedMap: [],
            outdated: [],
            autoload: [],
            licensesSummary: [],
            phpVersion: '^8.1',
            phpExtensions: [],
            npmDependencies: [],
            npmDevDependencies: [],
            nodeVersion: null,
            supportStatus: []
        );

        return new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: [],
            summary: ['thresholds' => []],
            analyzedAt: new \DateTimeImmutable(),
            dependencies: $dependencies
        );
    }

    private function createProjectResultWithArchitecture(): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $architecture = new ArchitectureResult(
            dependencyGraph: ['nodes' => [], 'edges' => []],
            layerAssignments: [],
            layerViolations: [],
            solidViolations: [],
            circularDependencies: [],
            layerStats: [],
            score: 100.0,
            rating: 'A'
        );

        return new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: [],
            summary: ['thresholds' => []],
            analyzedAt: new \DateTimeImmutable(),
            architecture: $architecture
        );
    }

    private function createProjectResultWithCoverage(): ProjectResult
    {
        $projectType = $this->createMock(ProjectTypeInterface::class);
        $projectType->method('getName')->willReturn('php');

        $coverage = new CoverageResult(
            found: true,
            lineCoverage: 75.0,
            methodCoverage: 80.0,
            classCoverage: 85.0,
            coveredLines: 75,
            totalLines: 100,
            coveredMethods: 8,
            totalMethods: 10,
            coveredClasses: 17,
            totalClasses: 20,
            files: [['coverage' => 75, 'name' => 'test.php']],
            packages: [],
            rating: 'B',
            generatedAt: null
        );

        return new ProjectResult(
            sourcePath: '/path/to/project',
            projectType: $projectType,
            files: [],
            summary: ['thresholds' => []],
            analyzedAt: new \DateTimeImmutable(),
            coverage: $coverage
        );
    }
}
