<?php

declare(strict_types=1);

namespace PhpQuality\Command;

use PhpQuality\Analyzer\ProjectAnalyzer;
use PhpQuality\Analyzer\ProjectType\ProjectTypeDetector;
use PhpQuality\Report\HtmlReportGenerator;
use PhpQuality\Report\ConsoleReportGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'phpquality:analyze',
    description: 'Analyze PHP source code and generate metrics report',
    aliases: ['analyze'],
)]
class AnalyzeCommand extends Command
{
    public function __construct(
        private readonly ProjectAnalyzer $analyzer,
        private readonly ProjectTypeDetector $typeDetector,
        private readonly HtmlReportGenerator $htmlGenerator,
        private readonly ConsoleReportGenerator $consoleGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $availableTypes = ['auto', ...$this->typeDetector->getTypeNames()];

        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source directory to analyze'
            )
            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                sprintf('Project type (%s)', implode(', ', $availableTypes)),
                'auto'
            )
            ->addOption(
                'report-html',
                null,
                InputOption::VALUE_REQUIRED,
                'Output directory for HTML report'
            )
            ->addOption(
                'exclude',
                'x',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Additional directories to exclude',
                []
            )
            ->addOption(
                'no-html',
                null,
                InputOption::VALUE_NONE,
                'Skip HTML report generation'
            )
            ->addOption(
                'json',
                null,
                InputOption::VALUE_REQUIRED,
                'Output JSON report to file'
            )
            ->addOption(
                'fail-on-violation',
                null,
                InputOption::VALUE_NONE,
                'Exit with error code if violations are found'
            )
            ->addOption(
                'list-types',
                null,
                InputOption::VALUE_NONE,
                'List available project types'
            )
            ->addOption(
                'lang',
                'l',
                InputOption::VALUE_REQUIRED,
                'Report language (en, fr, de, es, it, pt, nl, pl, ru, ja, zh, ko, ar, cs, da, el, fi, he, hi, hu, id, ro, sk, sv, th, tr, uk, vi, bg, hr)',
                'en'
            )
            ->addOption(
                'list-langs',
                null,
                InputOption::VALUE_NONE,
                'List available languages'
            )
            ->addOption(
                'git-blame',
                null,
                InputOption::VALUE_NONE,
                'Enable git blame analysis for Hall of Fame/Shame (slower)'
            )
            ->addOption(
                'project-name',
                'p',
                InputOption::VALUE_REQUIRED,
                'Project name to display in report titles'
            )
            ->addOption(
                'coverage',
                'c',
                InputOption::VALUE_REQUIRED,
                'Path to Clover XML coverage file (from PHPUnit --coverage-clover)'
            )
            ->addOption(
                'wizard',
                'w',
                InputOption::VALUE_NONE,
                'Interactive wizard mode to configure all options'
            );
    }

    private function runWizard(InputInterface $input, SymfonyStyle $io): void
    {
        $io->title('PhpQuality - Configuration Wizard');
        $io->text('Answer the following questions to configure the analysis.');
        $io->newLine();

        // Source directory (required)
        $defaultSource = $input->getOption('source') ?: getcwd() . '/src';
        $source = $io->ask('Source directory to analyze', $defaultSource, function ($value) {
            if (!$value || !is_dir($value)) {
                throw new \RuntimeException('Directory does not exist: ' . ($value ?: 'empty'));
            }
            return realpath($value);
        });
        $input->setOption('source', $source);

        // Project type
        $availableTypes = ['auto', ...$this->typeDetector->getTypeNames()];
        $defaultType = $input->getOption('type') ?: 'auto';
        $type = $io->choice('Project type', $availableTypes, $defaultType);
        $input->setOption('type', $type);

        // Project name
        $defaultName = $input->getOption('project-name') ?: basename($source);
        $projectName = $io->ask('Project name (for report title)', $defaultName);
        $input->setOption('project-name', $projectName ?: null);

        // Language
        $languages = [
            'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'es' => 'Español',
            'it' => 'Italiano', 'pt' => 'Português', 'nl' => 'Nederlands', 'pl' => 'Polski',
            'ru' => 'Русский', 'ja' => '日本語', 'zh' => '中文', 'ko' => '한국어',
        ];
        $defaultLang = $input->getOption('lang') ?: 'en';
        $langChoices = array_map(fn($code, $name) => "$code - $name", array_keys($languages), $languages);
        $langChoice = $io->choice('Report language', $langChoices, array_search("$defaultLang - " . ($languages[$defaultLang] ?? 'English'), $langChoices) ?: 0);
        $input->setOption('lang', explode(' - ', $langChoice)[0]);

        // HTML Report
        $generateHtml = $io->confirm('Generate HTML report?', true);
        $input->setOption('no-html', !$generateHtml);

        if ($generateHtml) {
            $defaultHtmlPath = $input->getOption('report-html') ?: $source . '/phpquality-report';
            $htmlPath = $io->ask('HTML report output directory', $defaultHtmlPath);
            $input->setOption('report-html', $htmlPath);
        }

        // JSON Report
        $generateJson = $io->confirm('Generate JSON report?', false);
        if ($generateJson) {
            $defaultJsonPath = $input->getOption('json') ?: $source . '/phpquality-report.json';
            $jsonPath = $io->ask('JSON report output path', $defaultJsonPath);
            $input->setOption('json', $jsonPath);
        }

        // Coverage file
        $hasCoverage = $io->confirm('Include code coverage data (requires Clover XML)?', false);
        if ($hasCoverage) {
            $defaultCoverage = $input->getOption('coverage') ?: $source . '/coverage.xml';
            $coveragePath = $io->ask('Path to Clover XML coverage file', $defaultCoverage);
            $input->setOption('coverage', $coveragePath);
        }

        // Git blame
        $enableGitBlame = $io->confirm('Enable git blame analysis (Hall of Fame/Shame - slower)?', false);
        $input->setOption('git-blame', $enableGitBlame);

        // Exclude directories
        $addExcludes = $io->confirm('Add directories to exclude?', false);
        if ($addExcludes) {
            $excludes = [];
            while (true) {
                $exclude = $io->ask('Directory to exclude (leave empty to finish)', '');
                if (empty($exclude)) {
                    break;
                }
                $excludes[] = $exclude;
            }
            $input->setOption('exclude', $excludes);
        }

        // Fail on violation
        $failOnViolation = $io->confirm('Exit with error code if violations are found (for CI)?', false);
        $input->setOption('fail-on-violation', $failOnViolation);

        $io->newLine();
        $io->success('Configuration complete! Starting analysis...');
        $io->newLine();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Wizard mode
        if ($input->getOption('wizard')) {
            $this->runWizard($input, $io);
        }

        // List types mode
        if ($input->getOption('list-types')) {
            return $this->listTypes($io);
        }

        // List languages mode
        if ($input->getOption('list-langs')) {
            return $this->listLanguages($io);
        }

        $source = $input->getOption('source');
        if (!$source || !is_dir($source)) {
            $io->error('Invalid source directory: ' . ($source ?: 'not specified'));
            $io->note('Usage: phpquality:analyze --source=/path/to/project');
            return Command::FAILURE;
        }

        $source = realpath($source);
        $projectType = $input->getOption('type');
        $excludes = $input->getOption('exclude');

        // Validate project type
        if ($projectType !== 'auto') {
            try {
                $this->typeDetector->getProjectType($projectType);
            } catch (\InvalidArgumentException $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->title('PhpQuality - PHP Code Quality Analyzer');
        $io->text([
            sprintf('Source: <info>%s</info>', $source),
            sprintf('Project Type: <info>%s</info>', $projectType),
        ]);

        // Set coverage path if provided
        $coveragePath = $input->getOption('coverage');
        if ($coveragePath) {
            if (!file_exists($coveragePath)) {
                $io->warning('Coverage file not found: ' . $coveragePath);
                $coveragePath = null;
            } else {
                $io->text(sprintf('Coverage: <info>%s</info>', $coveragePath));
            }
            $this->analyzer->setCoveragePath($coveragePath);
        }

        // Create progress bar
        $progressBar = null;
        $progressCallback = function (int $current, int $total, string $file) use ($output, &$progressBar) {
            if ($progressBar === null) {
                $progressBar = new ProgressBar($output, $total);
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                $progressBar->start();
            }
            $progressBar->setMessage($file);
            $progressBar->setProgress($current);
        };

        // Run analysis
        $io->newLine();
        $result = $this->analyzer->analyze(
            $source,
            $projectType,
            $excludes,
            $progressCallback
        );

        if ($progressBar) {
            $progressBar->finish();
        }
        $io->newLine(2);

        // Display console report
        $this->consoleGenerator->generate($result, $io);

        // Generate HTML report
        if (!$input->getOption('no-html')) {
            $htmlPath = $input->getOption('report-html') ?: $source . '/phpquality-report';
            $lang = $input->getOption('lang') ?? 'en';
            $enableGitBlame = $input->getOption('git-blame');
            $projectName = $input->getOption('project-name');
            $this->htmlGenerator->generate($result, $htmlPath, $lang, $enableGitBlame, $projectName);
            $io->success('HTML report generated: ' . $htmlPath . '/index.html');
        }

        // Generate JSON report
        if ($jsonPath = $input->getOption('json')) {
            $jsonContent = json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($jsonContent === false) {
                $io->error('Failed to encode JSON: ' . json_last_error_msg());
            } else {
                file_put_contents($jsonPath, $jsonContent);
                $io->success('JSON report generated: ' . $jsonPath);
            }
        }

        // Check for violations
        if ($input->getOption('fail-on-violation')) {
            $violations = $result->summary['violations'] ?? [];
            $totalViolations = array_sum($violations);
            if ($totalViolations > 0) {
                $io->warning(sprintf('Found %d violations', $totalViolations));
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function listTypes(SymfonyStyle $io): int
    {
        $io->title('Available Project Types');

        $types = $this->typeDetector->getAvailableTypes();
        $rows = [];

        foreach ($types as $name => $type) {
            $rows[] = [
                $name,
                $type->getLabel(),
                $type->getDescription(),
            ];
        }

        $io->table(['Name', 'Label', 'Description'], $rows);

        return Command::SUCCESS;
    }

    private function listLanguages(SymfonyStyle $io): int
    {
        $io->title('Available Report Languages');

        $languages = [
            ['en', 'English'],
            ['fr', 'Français'],
            ['de', 'Deutsch'],
            ['es', 'Español'],
            ['it', 'Italiano'],
            ['pt', 'Português'],
            ['nl', 'Nederlands'],
            ['pl', 'Polski'],
            ['ru', 'Русский'],
            ['ja', '日本語'],
            ['zh', '中文'],
            ['ko', '한국어'],
            ['ar', 'العربية'],
            ['cs', 'Čeština'],
            ['da', 'Dansk'],
            ['el', 'Ελληνικά'],
            ['fi', 'Suomi'],
            ['he', 'עברית'],
            ['hi', 'हिन्दी'],
            ['hu', 'Magyar'],
            ['id', 'Bahasa Indonesia'],
            ['ro', 'Română'],
            ['sk', 'Slovenčina'],
            ['sv', 'Svenska'],
            ['th', 'ไทย'],
            ['tr', 'Türkçe'],
            ['uk', 'Українська'],
            ['vi', 'Tiếng Việt'],
            ['bg', 'Български'],
            ['hr', 'Hrvatski'],
        ];

        $io->table(['Code', 'Language'], $languages);
        $io->note('Usage: phpquality:analyze --source=/path --lang=fr');

        return Command::SUCCESS;
    }
}
