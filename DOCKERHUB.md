**_# PhpQuality - PHP Static Code Analyzer

> A modern PHP static analysis tool distributed via Docker, designed as an alternative to `phpmetrics/phpmetrics`.
> Analyzes your PHP code and generates detailed reports on complexity, maintainability, coupling, architecture, and more.

**Author:** [Pascal CESCON](https://moi.ruedesjasses.fr)
**GitHub:** [Amoifr/PhpQuality](https://github.com/Amoifr/PhpQuality)

---

## Features

### Code Metrics
- **LOC** - Lines of Code (total, comments, logical)
- **CCN** - McCabe Cyclomatic Complexity per method
- **MI** - Maintainability Index (0-100)
- **LCOM** - Lack of Cohesion of Methods (0-1)
- **Halstead** - Volume, Difficulty, Effort, Estimated Bugs

### Architecture Analysis
- **Layer Detection** - Auto-detect architectural layers (Controller, Application, Domain, Infrastructure)
- **Layer Violations** - Detect forbidden dependencies (Clean Architecture)
- **SOLID Violations** - SRP, ISP, DIP detection
- **Circular Dependencies** - Tarjan's algorithm detection
- **Dependency Graph** - Interactive D3.js visualization

### HTML Reports
Beautiful multi-page HTML reports with:
- Dashboard with metric distributions
- Detailed pages for each metric
- Interactive charts (Chart.js)
- Dependency graph (D3.js)
- Dark/Light theme support
- 30+ languages supported

---

## Quick Start

```bash
# Basic analysis with auto-detection
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --report-html=/reports

# Specify project type (symfony, laravel, wordpress, etc.)
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --type=symfony --report-html=/reports

# Enable git blame for Hall of Fame/Shame
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --report-html=/reports --git-blame
```

---

## Options

| Option | Description |
|--------|-------------|
| `--source`, `-s` | Source directory to analyze (required) |
| `--type`, `-t` | Project type: `auto`, `symfony`, `laravel`, `wordpress`,<br>`prestashop`, `magento`, `drupal`, `joomla`, etc. |
| `--report-html` | Output directory for HTML report |
| `--json` | Output JSON report to file |
| `--lang`, `-l` | Report language (en, fr, de, es, it, ...) |
| `--git-blame` | Enable git blame analysis (Hall of Fame/Shame) |
| `--project-name`, `-p` | Project name to display in report titles |
| `--exclude`, `-x` | Additional directories to exclude |
| `--fail-on-violation` | Exit with error if violations found (CI mode) |
| `--list-types` | List available project types |
| `--list-langs` | List available languages |

---

## Supported Project Types

- PHP (Generic)
- Symfony
- Laravel
- PrestaShop
- Magento 2
- WordPress
- WooCommerce
- Drupal
- Joomla
- TYPO3
- Sulu CMS
- Sylius
- CodeIgniter
- CakePHP

---

## Stack

- PHP 8.3 (Alpine)
- Symfony 7.x
- nikic/php-parser
- Twig + Chart.js + D3.js

---

## License

MIT**_
