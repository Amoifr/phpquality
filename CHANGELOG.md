# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2026-03-26

### Added
- Configuration file support for quality thresholds via `config/packages/php_quality.yaml`
- Symfony Profiler integration with dedicated panel showing metrics for files loaded during request
- `ThresholdsConfig` DTO for threshold management with framework defaults merging
- Symfony Flex recipe for automatic bundle configuration on install
- Development environment config enabling profiler by default

### Changed
- Thresholds can now be overridden per-project while keeping framework-specific defaults for non-configured values

## [1.4.1] - 2026-03-26

### Fixed
- Memory limit handling for large project analysis

## [1.4.0] - 2026-03-26

### Added
- Multi-architecture Docker build support (amd64 + arm64)

### Fixed
- Docker build with correct package name

## [1.3.0] - 2026-03-25

### Added
- Interactive wizard mode for command configuration (`--wizard` or `-w` option)

## [1.2.2] - 2026-03-25

### Fixed
- JSON report generation with proper error handling

## [1.2.1] - 2026-03-25

### Fixed
- Twig template paths to use bundle namespace

## [1.2.0] - 2026-03-25

### Added
- Symfony Bundle architecture (package renamed to `amoifr/phpquality-bundle`)
- README translated to English

### Changed
- Refactored entire codebase to Symfony Bundle structure
- Package renamed from standalone tool to Symfony bundle

## [1.1.0] - 2026-03-25

### Added
- Code Coverage Analysis feature from Clover XML reports (`--coverage` option)
- Architecture Analysis inspired by Deptrac/PHP Insights (layer detection, SOLID principles)
- Hall of Fame/Shame feature using git blame (`--git-blame` option)
- Comprehensive PHPUnit test suite
- Translations for coverage and recommendations sections
- 17 language translations for reports

### Changed
- Git blame analysis now optional (disabled by default)

### Fixed
- Docker Hub image name to `amoifr13/phpquality`

## [1.0.0] - 2026-03-19

### Added
- Initial release of PhpQuality PHP Code Analyzer
- Cyclomatic Complexity (CCN) analysis per method and file
- Maintainability Index (MI) calculation with ratings (A-F)
- Lines of Code metrics (LOC, LLOC, CLOC, comment ratio)
- Halstead metrics (Volume, Difficulty, Effort, Bugs)
- Lack of Cohesion of Methods (LCOM) analysis
- HTML report generation with interactive charts
- Console report output with tables and summaries
- Multiple project type detection (Symfony, Laravel, WordPress, Magento, Drupal, etc.)
- Docker support for containerized analysis
- GitHub Actions workflow for Docker image publishing

### Fixed
- Allow running Docker container with any user (`--user` flag)

[Unreleased]: https://github.com/amoifr/PhpQuality/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/amoifr/PhpQuality/compare/v1.4.1...v1.5.0
[1.4.1]: https://github.com/amoifr/PhpQuality/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/amoifr/PhpQuality/compare/1.3.0...v1.4.0
[1.3.0]: https://github.com/amoifr/PhpQuality/compare/1.2.2...1.3.0
[1.2.2]: https://github.com/amoifr/PhpQuality/compare/1.2.1...1.2.2
[1.2.1]: https://github.com/amoifr/PhpQuality/compare/v1.2.0...1.2.1
[1.2.0]: https://github.com/amoifr/PhpQuality/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/amoifr/PhpQuality/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/amoifr/PhpQuality/releases/tag/v1.0.0
