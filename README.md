# PhpQuality - PHP Static Code Analyzer

> PHP static analyzer available as a Symfony Bundle and Docker image, designed to replace `phpmetrics/phpmetrics` (now unmaintained).
> Analyzes your PHP code and generates detailed reports on complexity, maintainability, coupling, architecture, and test coverage.

**Version:** 1.2.0
**Author:** [Pascal CESCON](https://moi.ruedesjasses.fr)
**GitHub:** [amoifr/PhpQuality](https://github.com/amoifr/PhpQuality)

---

## Installation

### As a Symfony Bundle

```bash
composer require amoifr/phpquality-bundle
```

Then register the bundle in `config/bundles.php`:

```php
return [
    // ...
    PhpQuality\PhpQualityBundle::class => ['all' => true],
];
```

Run the analysis:

```bash
php bin/console phpquality:analyze --source=src/
```

### Using Docker

```bash
# Pull the pre-built image
docker pull amoifr13/phpquality

# Run analysis
docker run --rm -v $(pwd):/project amoifr13/phpquality --source=/project/src
```

---

## Features

### Code Metrics v0.1

| Metric | Description |
|--------|-------------|
| **LOC** | Lines of Code (total, CLOC without comments, LLOC logical) |
| **CCN** | McCabe Cyclomatic Complexity per method |
| **MI** | Maintainability Index (0-100, higher = better) |
| **LCOM** | Lack of Cohesion of Methods (0-1, lower = better) |
| **Halstead** | Volume, Difficulty, Effort, Estimated Bugs |

### Architecture Analysis v0.4

| Feature | Description |
|---------|-------------|
| **Layer Detection** | Auto-detection of layers (Controller, Application, Domain, Infrastructure) |
| **Layer Violations** | Detection of forbidden dependencies (e.g., Domain → Infrastructure) |
| **SOLID Violations** | Detection of SRP, OCP, ISP, DIP violations |
| **Circular Dependencies** | Detection of dependency cycles using Tarjan's algorithm |
| **Dependency Graph** | Interactive D3.js visualization |
| **Dependency Matrix** | Matrix view of dependencies between layers |
| **Architecture Score** | Global score 0-100 based on violations |

### Test Coverage v1.1

| Feature | Description |
|---------|-------------|
| **Line Coverage** | Percentage of code lines covered by tests |
| **Method Coverage** | Percentage of methods tested |
| **Class Coverage** | Percentage of classes tested |
| **Package Coverage** | Coverage analysis by namespace/package |
| **File Coverage** | Coverage details for each file |
| **Coverage Grade** | A-F grade based on coverage percentage |

### Hall of Fame / Hall of Shame v0.3

| Feature | Description |
|---------|-------------|
| **Git Blame Analysis** | Per-author statistics via git blame |
| **Composite Score** | Score combining MI and CCN per contributor |
| **Hall of Fame** | Top contributors in code quality |
| **Hall of Shame** | Contributors whose code needs attention |

### HTML Reports v0.2

Multi-page HTML report includes:

| Page | Content |
|------|---------|
| **Dashboard** | Overview with distribution charts |
| **Documentation** | Detailed explanation of each metric |
| **CCN** | Complexity details per method |
| **MI** | Maintainability index per class |
| **LCOM** | Cohesion per class |
| **LOC** | Lines of code per file |
| **Halstead** | Advanced complexity metrics |
| **Analysis** | Multi-dimensional analysis, code tree, contributors |
| **Architecture** | Dependency graph, SOLID violations, dependency matrix |
| **Coverage** | Test coverage per file and package |
| **Dependencies** | Composer dependency analysis |

### Supported Project Types

PhpQuality adapts its analysis based on project type. Use `--type=` to specify the framework:

| Type | Label | Description |
|------|-------|-------------|
| `php` | PHP (Generic) | Generic PHP analysis (default) |
| `symfony` | Symfony | Symfony Framework (Controllers, Services, Repositories...) |
| `laravel` | Laravel | Laravel Framework (Eloquent Models, Middleware, Jobs...) |
| `prestashop` | PrestaShop | PrestaShop E-commerce (Modules, ObjectModel, Hooks...) |
| `magento` | Magento 2 | Magento 2 E-commerce (Plugins, Observers, Blocks...) |
| `wordpress` | WordPress | WordPress CMS (Plugins, Themes, Hooks...) |
| `woocommerce` | WooCommerce | WooCommerce Extension (Gateways, Shipping...) |
| `drupal` | Drupal | Drupal CMS (Modules, Plugins, Forms...) |
| `joomla` | Joomla | Joomla CMS (Components, Modules, Plugins...) |
| `typo3` | TYPO3 | TYPO3 CMS (Extensions, ViewHelpers...) |
| `sulu` | Sulu CMS | Sulu CMS (Symfony-based, Content Types...) |
| `sylius` | Sylius | Sylius E-commerce (Processors, Calculators...) |
| `codeigniter` | CodeIgniter | CodeIgniter Framework (Controllers, Models...) |
| `cakephp` | CakePHP | CakePHP Framework (Tables, Behaviors, Cells...) |

Each type defines:
- Directories to automatically exclude
- Class categorization patterns
- Recommended quality thresholds

---

## Usage with Docker

### Quick Analysis

```bash
# Analysis with auto-detection of project type
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  --source=/project/src --report-html=/reports
```

### Specify Project Type

```bash
# Symfony project
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  --source=/project/src --type=symfony --report-html=/reports

# PrestaShop project
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  --source=/project/modules/mymodule --type=prestashop

# WordPress project
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  --source=/project/wp-content/plugins/myplugin --type=wordpress
```

### With Test Coverage

```bash
# First generate coverage report with PHPUnit
./vendor/bin/phpunit --coverage-clover coverage.xml

# Then analyze with coverage
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  --source=/project/src --coverage=/project/coverage.xml --report-html=/reports
```

### With Hall of Fame/Shame (git blame)

```bash
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  --source=/project/src --git-blame --report-html=/reports
```

### Display Summary in Terminal Only

```bash
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  --source=/project/src --no-html
```

### JSON Export

```bash
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  --source=/project/src --json=/reports/metrics.json
```

### List Available Types

```bash
docker run --rm amoifr13/phpquality --list-types
```

### CI Mode (Fail on Violations)

```bash
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  --source=/project/src --type=symfony --fail-on-violation
```

---

## Command Options

| Option | Description |
|--------|-------------|
| `--source`, `-s` | Source directory to analyze (required) |
| `--type`, `-t` | Project type (`auto`, `symfony`, `laravel`, etc.) |
| `--report-html` | Output directory for HTML report |
| `--json` | Output file for JSON report |
| `--exclude`, `-x` | Additional directories to exclude (repeatable) |
| `--no-html` | Skip HTML report generation |
| `--fail-on-violation` | Exit with error if violations are detected |
| `--git-blame` | Enable git blame analysis for Hall of Fame/Shame (slower) |
| `--coverage`, `-c` | Path to Clover XML coverage file |
| `--project-name`, `-p` | Project name to display in report titles |
| `--lang`, `-l` | Report language (en, fr, de, es, it, pt, nl, pl, ru, ja, zh, ko...) |
| `--list-types` | List all available project types |
| `--list-langs` | List all available languages |

---

## Architecture

```
phpquality/
├── src/                           # Symfony Bundle
│   ├── PhpQualityBundle.php
│   ├── DependencyInjection/
│   ├── Analyzer/
│   │   ├── Ast/
│   │   │   ├── AstParser.php
│   │   │   └── Visitor/
│   │   ├── Architecture/
│   │   ├── ProjectType/
│   │   ├── Result/
│   │   ├── FileAnalyzer.php
│   │   └── ProjectAnalyzer.php
│   ├── Command/
│   │   └── AnalyzeCommand.php
│   ├── Report/
│   │   ├── HtmlReportGenerator.php
│   │   └── ConsoleReportGenerator.php
│   └── Resources/
│       ├── config/services.yaml
│       ├── views/report/
│       └── translations/
├── tests/
├── docker/
│   ├── app/                       # Minimal Symfony skeleton
│   ├── Dockerfile
│   └── entrypoint.sh
└── composer.json
```

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| Language | PHP 8.3 |
| Framework | Symfony 7.x |
| AST Parser | [nikic/php-parser](https://github.com/nikic/PHP-Parser) |
| HTML Rendering | Twig + Chart.js + D3.js |
| CLI | Symfony Console |
| Base Image | `php:8.3-cli-alpine` |
| Dependency Management | Composer |

---

## Metrics Interpretation

### Maintainability Index (MI)

| Score | Rating | Interpretation |
|-------|--------|----------------|
| 85-100 | A | Highly maintainable |
| 65-84 | B | Moderately maintainable |
| 40-64 | C | Difficult to maintain |
| 20-39 | D | Very difficult to maintain |
| 0-19 | F | Unmaintainable |

### Cyclomatic Complexity (CCN)

| Score | Rating | Interpretation |
|-------|--------|----------------|
| 1-4 | A | Low complexity |
| 5-7 | B | Moderate complexity |
| 8-10 | C | High complexity |
| 11-15 | D | Very high complexity |
| 16+ | F | Excessive complexity |

### Lack of Cohesion (LCOM)

| Score | Rating | Interpretation |
|-------|--------|----------------|
| 0-0.2 | A | Excellent cohesion |
| 0.2-0.4 | B | Good cohesion |
| 0.4-0.6 | C | Moderate cohesion |
| 0.6-0.8 | D | Low cohesion |
| 0.8-1.0 | F | Very low cohesion |

### Architecture Score

| Score | Rating | Interpretation |
|-------|--------|----------------|
| 85-100 | A | Exemplary architecture |
| 70-84 | B | Good architecture |
| 50-69 | C | Acceptable architecture |
| 30-49 | D | Architecture needs improvement |
| 0-29 | F | Critical architecture |

### Test Coverage

| Score | Rating | Interpretation |
|-------|--------|----------------|
| 80-100% | A | Excellent coverage |
| 60-79% | B | Good coverage |
| 40-59% | C | Moderate coverage |
| 20-39% | D | Low coverage |
| 0-19% | F | Critical coverage |

### Layer Rules (Clean Architecture)

PhpQuality automatically detects layers and checks dependency rules:

| Layer | Can depend on | Cannot depend on |
|-------|---------------|------------------|
| **Domain** | nothing | Application, Infrastructure, Controller |
| **Application** | Domain | Infrastructure, Controller |
| **Infrastructure** | Domain, Application | Controller |
| **Controller** | Domain, Application, Infrastructure | - |

### Detected SOLID Violations

| Principle | Detection | Thresholds |
|-----------|-----------|------------|
| **SRP** (Single Responsibility) | Classes with too many methods, dependencies, and lines of code | LCOM > 0.7, methods > 20, deps > 15 |
| **OCP** (Open/Closed) | Numerous switch/match on type | Coming soon |
| **ISP** (Interface Segregation) | Interfaces with too many methods | > 5 methods |
| **DIP** (Dependency Inversion) | Concrete/abstract dependency ratio | ratio < 0.5 |

---

## CI/CD Integration

### GitHub Actions

```yaml
name: Code Quality

on: [push, pull_request]

jobs:
  quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: xdebug

      - name: Install dependencies
        run: composer install

      - name: Run tests with coverage
        run: ./vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Run PhpQuality
        run: |
          docker run --rm \
            -v ${{ github.workspace }}:/project \
            amoifr13/phpquality \
            --source=/project/src \
            --coverage=/project/coverage.xml \
            --fail-on-violation \
            --json=/project/phpquality.json

      - name: Upload results
        uses: actions/upload-artifact@v4
        with:
          name: phpquality-report
          path: phpquality.json
```

### GitLab CI

```yaml
code-quality:
  image: amoifr13/phpquality
  script:
    - phpquality:analyze --source=/builds/$CI_PROJECT_PATH/src --coverage=/builds/$CI_PROJECT_PATH/coverage.xml --fail-on-violation
  artifacts:
    reports:
      codequality: phpquality.json
```

---

## Roadmap

- [x] **v0.1** - Basic analysis: LOC, CCN, MI, LCOM + HTML report + project types
- [x] **v0.2** - Multi-page HTML report with metrics documentation
- [x] **v0.3** - Multi-dimensional analysis, code tree, Hall of Fame/Shame, Composer dependency analysis
- [x] **v0.4** - Architecture analysis (layers, SOLID violations, D3.js dependency graph, circular dependencies)
- [x] **v1.0** - Git blame option, custom project name
- [x] **v1.1** - Test coverage analysis (Clover XML)
- [x] **v1.2** - Symfony Bundle architecture
- [ ] **v1.3** - Configurable CI rules, custom thresholds
- [ ] **v1.4** - PDF export, version comparison
- [ ] **v2.0** - Interactive web interface, analysis history

---

## Contributing

Contributions are welcome! To propose a missing metric or fix a calculation:

1. Fork the repository
2. Create a branch: `git checkout -b feature/my-metric`
3. Commit your changes
4. Open a Pull Request

---

## License

MIT - see [LICENSE](./LICENSE) file.

---

## References

- [phpmetrics/PhpMetrics (GitHub)](https://github.com/phpmetrics/PhpMetrics) - original project
- [nikic/php-parser](https://github.com/nikic/PHP-Parser)
- [Halstead complexity measures (Wikipedia)](https://en.wikipedia.org/wiki/Halstead_complexity_measures)
- [Cyclomatic complexity (Wikipedia)](https://en.wikipedia.org/wiki/Cyclomatic_complexity)
- [Software package metrics - Robert Martin (Wikipedia)](https://en.wikipedia.org/wiki/Software_package_metrics)
- [Deptrac (GitHub)](https://github.com/qossmic/deptrac) - inspiration for layer analysis
- [PHP Insights (GitHub)](https://github.com/nunomaduro/phpinsights) - inspiration for quality analysis
- [SOLID principles (Wikipedia)](https://en.wikipedia.org/wiki/SOLID)
- [Clean Architecture - Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [PHPUnit Code Coverage](https://phpunit.readthedocs.io/en/9.5/code-coverage-analysis.html)
