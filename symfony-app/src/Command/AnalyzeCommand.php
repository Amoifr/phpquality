<?php

declare(strict_types=1);

namespace App\Command;

use App\Analyzer\ProjectAnalyzer;
use App\Analyzer\ProjectType\ProjectTypeDetector;
use App\Report\HtmlReportGenerator;
use App\Report\ConsoleReportGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;

#[AsCommand(
    name: 'analyze',
    description: 'Analyze PHP source code and generate metrics report',
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
            $io->note('Usage: analyze --source=/path/to/project');
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
            $this->htmlGenerator->generate($result, $htmlPath, $lang, $enableGitBlame);
            $io->success('HTML report generated: ' . $htmlPath . '/index.html');
        }

        // Generate JSON report
        if ($jsonPath = $input->getOption('json')) {
            file_put_contents($jsonPath, json_encode($result->toArray(), JSON_PRETTY_PRINT));
            $io->success('JSON report generated: ' . $jsonPath);
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
        $io->note('Usage: analyze --source=/path --lang=fr');

        return Command::SUCCESS;
    }
}
