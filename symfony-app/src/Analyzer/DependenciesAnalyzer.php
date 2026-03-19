<?php

declare(strict_types=1);

namespace App\Analyzer;

use App\Analyzer\Result\DependenciesResult;

final class DependenciesAnalyzer
{
    private const PACKAGIST_API = 'https://repo.packagist.org/p2/';
    private const NPM_REGISTRY = 'https://registry.npmjs.org/';

    // Roadmaps for major projects (EOL dates, LTS status)
    // Format: 'version' => ['eol' => 'YYYY-MM-DD', 'lts' => bool, 'security_eol' => 'YYYY-MM-DD']
    private const PHP_ROADMAP = [
        '8.4' => ['eol' => '2027-12-31', 'security_eol' => '2028-12-31', 'lts' => false],
        '8.3' => ['eol' => '2026-12-31', 'security_eol' => '2027-12-31', 'lts' => false],
        '8.2' => ['eol' => '2025-12-31', 'security_eol' => '2026-12-31', 'lts' => false],
        '8.1' => ['eol' => '2024-11-25', 'security_eol' => '2025-12-31', 'lts' => false],
        '8.0' => ['eol' => '2023-11-26', 'security_eol' => '2023-11-26', 'lts' => false],
        '7.4' => ['eol' => '2022-11-28', 'security_eol' => '2022-11-28', 'lts' => false],
        '7.3' => ['eol' => '2021-12-06', 'security_eol' => '2021-12-06', 'lts' => false],
        '7.2' => ['eol' => '2020-11-30', 'security_eol' => '2020-11-30', 'lts' => false],
    ];

    private const SYMFONY_ROADMAP = [
        '7.4' => ['eol' => '2028-11-30', 'security_eol' => '2029-11-30', 'lts' => true],
        '7.3' => ['eol' => '2026-01-31', 'security_eol' => '2026-01-31', 'lts' => false],
        '7.2' => ['eol' => '2025-07-31', 'security_eol' => '2025-07-31', 'lts' => false],
        '7.1' => ['eol' => '2025-01-31', 'security_eol' => '2025-01-31', 'lts' => false],
        '7.0' => ['eol' => '2024-07-31', 'security_eol' => '2024-07-31', 'lts' => false],
        '6.4' => ['eol' => '2027-11-30', 'security_eol' => '2028-11-30', 'lts' => true],
        '6.3' => ['eol' => '2024-01-31', 'security_eol' => '2024-01-31', 'lts' => false],
        '6.2' => ['eol' => '2023-07-31', 'security_eol' => '2023-07-31', 'lts' => false],
        '6.1' => ['eol' => '2023-01-31', 'security_eol' => '2023-01-31', 'lts' => false],
        '6.0' => ['eol' => '2023-01-31', 'security_eol' => '2023-01-31', 'lts' => false],
        '5.4' => ['eol' => '2024-11-30', 'security_eol' => '2025-11-30', 'lts' => true],
        '5.3' => ['eol' => '2022-01-31', 'security_eol' => '2022-01-31', 'lts' => false],
        '4.4' => ['eol' => '2023-11-30', 'security_eol' => '2024-11-30', 'lts' => true],
    ];

    private const LARAVEL_ROADMAP = [
        '11' => ['eol' => '2026-03-12', 'security_eol' => '2027-09-12', 'lts' => false],
        '10' => ['eol' => '2025-02-04', 'security_eol' => '2026-08-04', 'lts' => false],
        '9'  => ['eol' => '2024-02-06', 'security_eol' => '2024-08-06', 'lts' => false],
        '8'  => ['eol' => '2023-01-24', 'security_eol' => '2023-07-24', 'lts' => false],
        '7'  => ['eol' => '2021-03-03', 'security_eol' => '2021-09-03', 'lts' => false],
        '6'  => ['eol' => '2022-09-06', 'security_eol' => '2022-09-06', 'lts' => true],
    ];

    private const DRUPAL_ROADMAP = [
        '11' => ['eol' => '2028-12-31', 'security_eol' => '2028-12-31', 'lts' => false],
        '10' => ['eol' => '2026-12-31', 'security_eol' => '2026-12-31', 'lts' => true],
        '9'  => ['eol' => '2023-11-01', 'security_eol' => '2023-11-01', 'lts' => false],
        '8'  => ['eol' => '2021-11-02', 'security_eol' => '2021-11-02', 'lts' => false],
    ];

    private const NODEJS_ROADMAP = [
        '22' => ['eol' => '2027-04-30', 'security_eol' => '2027-04-30', 'lts' => true],
        '21' => ['eol' => '2024-06-01', 'security_eol' => '2024-06-01', 'lts' => false],
        '20' => ['eol' => '2026-04-30', 'security_eol' => '2026-04-30', 'lts' => true],
        '18' => ['eol' => '2025-04-30', 'security_eol' => '2025-04-30', 'lts' => true],
        '16' => ['eol' => '2024-04-30', 'security_eol' => '2024-04-30', 'lts' => true],
    ];

    public function analyze(string $projectPath): DependenciesResult
    {
        $composerJson = $this->findFile($projectPath, 'composer.json');
        $composerLock = $this->findFile($projectPath, 'composer.lock');
        $packageJson = $this->findFile($projectPath, 'package.json');
        $packageLock = $this->findFile($projectPath, 'package-lock.json');

        $hasComposer = $composerJson !== null;
        $hasNpm = $packageJson !== null;

        if (!$hasComposer && !$hasNpm) {
            return DependenciesResult::notFound();
        }

        $type = match (true) {
            $hasComposer && $hasNpm => 'both',
            $hasComposer => 'composer',
            default => 'npm',
        };

        // Parse Composer files
        $composer = $composerJson ? $this->parseJson($composerJson) : [];
        $composerLockData = $composerLock ? $this->parseJson($composerLock) : [];

        // Parse NPM files
        $npm = $packageJson ? $this->parseJson($packageJson) : [];
        $npmLockData = $packageLock ? $this->parseJson($packageLock) : [];

        // Extract project name
        $name = $composer['name'] ?? $npm['name'] ?? basename($projectPath);

        // Extract Composer dependencies
        $require = $composer['require'] ?? [];
        $requireDev = $composer['require-dev'] ?? [];

        // Extract PHP version and extensions
        $phpVersion = $require['php'] ?? null;
        $phpExtensions = [];
        foreach ($require as $package => $version) {
            if (str_starts_with($package, 'ext-')) {
                $phpExtensions[$package] = $version;
            }
        }

        // Parse installed packages from composer.lock
        $installed = [];
        $installedMap = [];
        $licenses = [];

        foreach ($composerLockData['packages'] ?? [] as $package) {
            $packageData = [
                'name' => $package['name'],
                'version' => $package['version'],
                'license' => $package['license'] ?? ['Unknown'],
                'description' => $package['description'] ?? '',
                'type' => 'require',
                'time' => $package['time'] ?? null,
                'source' => $package['source']['url'] ?? null,
            ];
            $installed[] = $packageData;
            $installedMap[$package['name']] = $packageData;

            foreach ($package['license'] ?? ['Unknown'] as $lic) {
                $licenses[$lic] = ($licenses[$lic] ?? 0) + 1;
            }
        }

        foreach ($composerLockData['packages-dev'] ?? [] as $package) {
            $packageData = [
                'name' => $package['name'],
                'version' => $package['version'],
                'license' => $package['license'] ?? ['Unknown'],
                'description' => $package['description'] ?? '',
                'type' => 'require-dev',
                'time' => $package['time'] ?? null,
                'source' => $package['source']['url'] ?? null,
            ];
            $installed[] = $packageData;
            $installedMap[$package['name']] = $packageData;

            foreach ($package['license'] ?? ['Unknown'] as $lic) {
                $licenses[$lic] = ($licenses[$lic] ?? 0) + 1;
            }
        }

        // Sort licenses by count
        arsort($licenses);

        // Extract autoload configuration
        $autoload = $composer['autoload'] ?? [];

        // Extract NPM dependencies
        $npmDependencies = $npm['dependencies'] ?? [];
        $npmDevDependencies = $npm['devDependencies'] ?? [];
        $nodeVersion = $npm['engines']['node'] ?? null;

        // Check for outdated packages (async via Packagist API)
        $outdated = $this->checkOutdatedPackages($installedMap, $require, $requireDev);

        // Check support status for major dependencies
        $supportStatus = $this->checkSupportStatus($phpVersion, $installedMap);

        return new DependenciesResult(
            found: true,
            name: $name,
            type: $type,
            require: $require,
            requireDev: $requireDev,
            installed: $installed,
            installedMap: $installedMap,
            outdated: $outdated,
            autoload: $autoload,
            licensesSummary: $licenses,
            phpVersion: $phpVersion,
            phpExtensions: $phpExtensions,
            npmDependencies: $npmDependencies,
            npmDevDependencies: $npmDevDependencies,
            nodeVersion: $nodeVersion,
            supportStatus: $supportStatus,
        );
    }

    private function checkSupportStatus(?string $phpConstraint, array $installedMap): array
    {
        $status = [];
        $today = new \DateTimeImmutable();

        // Check PHP version
        if ($phpConstraint) {
            $phpVersion = $this->extractMajorMinor($phpConstraint);
            if ($phpVersion && isset(self::PHP_ROADMAP[$phpVersion])) {
                $roadmap = self::PHP_ROADMAP[$phpVersion];
                $eol = new \DateTimeImmutable($roadmap['security_eol']);
                $status['php'] = [
                    'name' => 'PHP',
                    'version' => $phpVersion,
                    'lts' => $roadmap['lts'],
                    'eol_date' => $roadmap['security_eol'],
                    'supported' => $eol > $today,
                    'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                ];
            }
        }

        // Check Symfony
        foreach (['symfony/framework-bundle', 'symfony/symfony'] as $pkg) {
            if (isset($installedMap[$pkg])) {
                $version = $this->extractMajorMinor($installedMap[$pkg]['version']);
                if ($version && isset(self::SYMFONY_ROADMAP[$version])) {
                    $roadmap = self::SYMFONY_ROADMAP[$version];
                    $eol = new \DateTimeImmutable($roadmap['security_eol']);
                    $status['symfony'] = [
                        'name' => 'Symfony',
                        'version' => $version,
                        'lts' => $roadmap['lts'],
                        'eol_date' => $roadmap['security_eol'],
                        'supported' => $eol > $today,
                        'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                    ];
                    break;
                }
            }
        }

        // Check Laravel
        if (isset($installedMap['laravel/framework'])) {
            $version = $this->extractMajor($installedMap['laravel/framework']['version']);
            if ($version && isset(self::LARAVEL_ROADMAP[$version])) {
                $roadmap = self::LARAVEL_ROADMAP[$version];
                $eol = new \DateTimeImmutable($roadmap['security_eol']);
                $status['laravel'] = [
                    'name' => 'Laravel',
                    'version' => $version,
                    'lts' => $roadmap['lts'],
                    'eol_date' => $roadmap['security_eol'],
                    'supported' => $eol > $today,
                    'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                ];
            }
        }

        // Check Drupal
        foreach (['drupal/core', 'drupal/core-recommended'] as $pkg) {
            if (isset($installedMap[$pkg])) {
                $version = $this->extractMajor($installedMap[$pkg]['version']);
                if ($version && isset(self::DRUPAL_ROADMAP[$version])) {
                    $roadmap = self::DRUPAL_ROADMAP[$version];
                    $eol = new \DateTimeImmutable($roadmap['security_eol']);
                    $status['drupal'] = [
                        'name' => 'Drupal',
                        'version' => $version,
                        'lts' => $roadmap['lts'],
                        'eol_date' => $roadmap['security_eol'],
                        'supported' => $eol > $today,
                        'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                    ];
                    break;
                }
            }
        }

        return $status;
    }

    private function extractMajorMinor(string $constraint): ?string
    {
        // Handle constraint formats: ^8.2, >=8.1, ~8.0, 8.2.*, etc.
        if (preg_match('/(\d+)\.(\d+)/', $constraint, $matches)) {
            return $matches[1] . '.' . $matches[2];
        }
        return null;
    }

    private function extractMajor(string $version): ?string
    {
        if (preg_match('/^v?(\d+)/', $version, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function findFile(string $projectPath, string $filename): ?string
    {
        // Check in project path
        $path = $projectPath . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($path)) {
            return $path;
        }

        // Check in parent directories (up to 3 levels)
        $dir = $projectPath;
        for ($i = 0; $i < 3; $i++) {
            $dir = dirname($dir);
            $path = $dir . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function parseJson(string $path): array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function checkOutdatedPackages(array $installedMap, array $require, array $requireDev): array
    {
        $outdated = [];
        $packagesToCheck = array_merge(
            array_keys($require),
            array_keys($requireDev)
        );

        foreach ($packagesToCheck as $packageName) {
            // Skip PHP and extensions
            if ($packageName === 'php' || str_starts_with($packageName, 'ext-')) {
                continue;
            }

            $installed = $installedMap[$packageName] ?? null;
            if (!$installed) {
                continue;
            }

            $latestVersion = $this->getLatestVersion($packageName);
            if ($latestVersion === null) {
                continue;
            }

            $installedVersion = ltrim($installed['version'], 'v');
            $latestClean = ltrim($latestVersion, 'v');

            if ($this->isOutdated($installedVersion, $latestClean)) {
                $supportInfo = $this->getPackageSupportStatus($packageName, $installedVersion);
                $baseSeverity = $this->calculateSeverity($installedVersion, $latestClean);
                $adjustedSeverity = $this->adjustSeverityBySupport($baseSeverity, $supportInfo);
                $outdated[] = [
                    'name' => $packageName,
                    'installed' => $installed['version'],
                    'latest' => $latestVersion,
                    'constraint' => $require[$packageName] ?? $requireDev[$packageName] ?? '*',
                    'severity' => $adjustedSeverity,
                    'support' => $supportInfo,
                ];
            }
        }

        // Sort by severity (major updates first)
        usort($outdated, fn($a, $b) => $b['severity'] <=> $a['severity']);

        return $outdated;
    }

    private function getLatestVersion(string $packageName): ?string
    {
        // Try to fetch from Packagist
        $url = self::PACKAGIST_API . $packageName . '.json';

        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['packages'][$packageName])) {
            return null;
        }

        $versions = $data['packages'][$packageName];

        // Find the latest stable version
        foreach ($versions as $versionData) {
            $version = $versionData['version'] ?? '';

            // Skip dev versions
            if (str_contains($version, 'dev') || str_contains($version, 'alpha') || str_contains($version, 'beta') || str_contains($version, 'RC')) {
                continue;
            }

            return $version;
        }

        return null;
    }

    private function isOutdated(string $installed, string $latest): bool
    {
        // Parse versions
        $installedParts = $this->parseVersion($installed);
        $latestParts = $this->parseVersion($latest);

        // Compare major.minor.patch
        for ($i = 0; $i < 3; $i++) {
            $inst = $installedParts[$i] ?? 0;
            $lat = $latestParts[$i] ?? 0;

            if ($lat > $inst) {
                return true;
            }
            if ($inst > $lat) {
                return false;
            }
        }

        return false;
    }

    private function parseVersion(string $version): array
    {
        // Remove 'v' prefix and any suffix
        $version = preg_replace('/^v/', '', $version);
        $version = preg_replace('/-.*$/', '', $version);

        $parts = explode('.', $version);
        return array_map('intval', $parts);
    }

    private function calculateSeverity(string $installed, string $latest): int
    {
        $installedParts = $this->parseVersion($installed);
        $latestParts = $this->parseVersion($latest);

        // Major version difference = 3 (critical)
        if (($latestParts[0] ?? 0) > ($installedParts[0] ?? 0)) {
            return 3;
        }

        // Minor version difference = 2 (warning)
        if (($latestParts[1] ?? 0) > ($installedParts[1] ?? 0)) {
            return 2;
        }

        // Patch version difference = 1 (info)
        return 1;
    }

    private function adjustSeverityBySupport(int $baseSeverity, ?array $supportInfo): int
    {
        if ($supportInfo === null) {
            // No support info available, keep base severity
            return $baseSeverity;
        }

        // If EOL (end of life), increase severity to maximum
        if (!$supportInfo['supported']) {
            return 3; // Critical - must update
        }

        // If supported, adjust severity based on days until EOL
        $daysUntilEol = $supportInfo['days_until_eol'];

        // Less than 6 months (180 days): keep severity
        if ($daysUntilEol < 180) {
            return $baseSeverity;
        }

        // 6-12 months (180-365 days): reduce severity by 1 (min 1)
        if ($daysUntilEol < 365) {
            return max(1, $baseSeverity - 1);
        }

        // More than 12 months: low priority, reduce severity by 2 (min 1)
        // LTS with lots of time remaining = not urgent
        if ($supportInfo['lts'] && $daysUntilEol > 365) {
            return 1; // Informational only - LTS still well supported
        }

        return max(1, $baseSeverity - 1);
    }

    private function getPackageSupportStatus(string $packageName, string $version): ?array
    {
        $today = new \DateTimeImmutable();

        // Symfony packages
        if (str_starts_with($packageName, 'symfony/')) {
            $majorMinor = $this->extractMajorMinor($version);
            if ($majorMinor && isset(self::SYMFONY_ROADMAP[$majorMinor])) {
                $roadmap = self::SYMFONY_ROADMAP[$majorMinor];
                $eol = new \DateTimeImmutable($roadmap['security_eol']);
                return [
                    'framework' => 'Symfony',
                    'version' => $majorMinor,
                    'lts' => $roadmap['lts'],
                    'eol_date' => $roadmap['security_eol'],
                    'supported' => $eol > $today,
                    'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                ];
            }
        }

        // Laravel packages
        if (str_starts_with($packageName, 'laravel/') || str_starts_with($packageName, 'illuminate/')) {
            $major = $this->extractMajor($version);
            if ($major && isset(self::LARAVEL_ROADMAP[$major])) {
                $roadmap = self::LARAVEL_ROADMAP[$major];
                $eol = new \DateTimeImmutable($roadmap['security_eol']);
                return [
                    'framework' => 'Laravel',
                    'version' => $major,
                    'lts' => $roadmap['lts'],
                    'eol_date' => $roadmap['security_eol'],
                    'supported' => $eol > $today,
                    'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                ];
            }
        }

        // Drupal packages
        if (str_starts_with($packageName, 'drupal/')) {
            $major = $this->extractMajor($version);
            if ($major && isset(self::DRUPAL_ROADMAP[$major])) {
                $roadmap = self::DRUPAL_ROADMAP[$major];
                $eol = new \DateTimeImmutable($roadmap['security_eol']);
                return [
                    'framework' => 'Drupal',
                    'version' => $major,
                    'lts' => $roadmap['lts'],
                    'eol_date' => $roadmap['security_eol'],
                    'supported' => $eol > $today,
                    'days_until_eol' => $eol > $today ? $today->diff($eol)->days : -$eol->diff($today)->days,
                ];
            }
        }

        return null;
    }
}