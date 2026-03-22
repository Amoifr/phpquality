# PhpQuality - Analyseur statique PHP

> Analyseur statique PHP distribué via Docker, conçu pour remplacer `phpmetrics/phpmetrics` (désormais non maintenu).
> Analyse votre code PHP et génère des rapports détaillés sur la complexité, la maintenabilité, le couplage, l'architecture et la couverture de tests.

**Version:** 1.1.0
**Auteur:** [Pascal CESCON](https://moi.ruedesjasses.fr)
**GitHub:** [amoifr/PhpQuality](https://github.com/amoifr/PhpQuality)

---

## Fonctionnalités

### Métriques de code v0.1

| Métrique | Description |
|----------|-------------|
| **LOC** | Lines of Code (total, CLOC sans commentaires, LLOC logiques) |
| **CCN** | Complexité cyclomatique de McCabe par méthode |
| **MI** | Maintainability Index (0-100, plus élevé = meilleur) |
| **LCOM** | Lack of Cohesion of Methods (0-1, plus bas = meilleur) |
| **Halstead** | Volume, Difficulté, Effort, Bugs estimés |

### Analyse d'architecture v0.4

| Fonctionnalité | Description |
|----------------|-------------|
| **Détection de couches** | Auto-détection des couches (Controller, Application, Domain, Infrastructure) |
| **Violations de couches** | Détection des dépendances interdites (ex: Domain → Infrastructure) |
| **Violations SOLID** | Détection des violations SRP, OCP, ISP, DIP |
| **Dépendances circulaires** | Détection des cycles de dépendances avec Tarjan |
| **Graphe de dépendances** | Visualisation interactive D3.js |
| **Matrice de dépendances** | Vue matricielle des dépendances entre couches |
| **Score d'architecture** | Score global 0-100 basé sur les violations |

### Couverture de tests v1.1 (NOUVEAU)

| Fonctionnalité | Description |
|----------------|-------------|
| **Couverture des lignes** | Pourcentage de lignes de code couvertes par les tests |
| **Couverture des méthodes** | Pourcentage de méthodes testées |
| **Couverture des classes** | Pourcentage de classes testées |
| **Couverture par package** | Analyse de couverture par namespace/package |
| **Couverture par fichier** | Détail de couverture pour chaque fichier |
| **Score de couverture** | Note A-F basée sur le pourcentage de couverture |

### Hall of Fame / Hall of Shame v0.3

| Fonctionnalité | Description |
|----------------|-------------|
| **Analyse git blame** | Statistiques par auteur via git blame |
| **Score composite** | Score combinant MI et CCN par contributeur |
| **Hall of Fame** | Top contributeurs en qualité de code |
| **Hall of Shame** | Contributeurs dont le code nécessite de l'attention |

### Rapports HTML v0.2

Le rapport HTML multi-pages inclut :

| Page | Contenu |
|------|---------|
| **Dashboard** | Vue d'ensemble avec graphiques de distribution |
| **Documentation** | Explication détaillée de chaque métrique |
| **CCN** | Détail de la complexité par méthode |
| **MI** | Index de maintenabilité par classe |
| **LCOM** | Cohésion par classe |
| **LOC** | Lignes de code par fichier |
| **Halstead** | Métriques de complexité avancées |
| **Analysis** | Analyse multi-dimensionnelle, arbre du code, contributeurs |
| **Architecture** | Graphe de dépendances, violations SOLID, matrice de dépendances |
| **Coverage** | Couverture de tests par fichier et package |
| **Dependencies** | Analyse des dépendances Composer |

### Types de projets supportés

PhpQuality adapte son analyse selon le type de projet. Utilisez `--type=` pour spécifier le framework :

| Type | Label | Description |
|------|-------|-------------|
| `php` | PHP (Generic) | Analyse générique PHP (défaut) |
| `symfony` | Symfony | Framework Symfony (Controllers, Services, Repositories...) |
| `laravel` | Laravel | Framework Laravel (Models Eloquent, Middleware, Jobs...) |
| `prestashop` | PrestaShop | E-commerce PrestaShop (Modules, ObjectModel, Hooks...) |
| `magento` | Magento 2 | E-commerce Magento 2 (Plugins, Observers, Blocks...) |
| `wordpress` | WordPress | CMS WordPress (Plugins, Themes, Hooks...) |
| `woocommerce` | WooCommerce | Extension WooCommerce (Gateways, Shipping...) |
| `drupal` | Drupal | CMS Drupal (Modules, Plugins, Forms...) |
| `joomla` | Joomla | CMS Joomla (Components, Modules, Plugins...) |
| `typo3` | TYPO3 | CMS TYPO3 (Extensions, ViewHelpers...) |
| `sulu` | Sulu CMS | CMS Sulu (Symfony-based, Content Types...) |
| `sylius` | Sylius | E-commerce Sylius (Processors, Calculators...) |
| `codeigniter` | CodeIgniter | Framework CodeIgniter (Controllers, Models...) |
| `cakephp` | CakePHP | Framework CakePHP (Tables, Behaviors, Cells...) |

Chaque type définit :
- Les répertoires à exclure automatiquement
- Les patterns de catégorisation des classes
- Les seuils de qualité recommandés

---

## Utilisation avec Docker

### Installation

```bash
# Construire l'image
docker build -t amoifr13/phpquality -f docker/Dockerfile .

# Ou utiliser l'image pré-construite
docker pull amoifr13/phpquality
```

### Analyse rapide

```bash
# Analyse avec auto-détection du type de projet
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --report-html=/reports
```

### Spécifier le type de projet

```bash
# Projet Symfony
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --type=symfony --report-html=/reports

# Projet PrestaShop
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  analyze --source=/project/modules/mymodule --type=prestashop

# Projet WordPress
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  analyze --source=/project/wp-content/plugins/myplugin --type=wordpress

# Projet Magento 2
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  analyze --source=/project/app/code/Vendor/Module --type=magento
```

### Avec couverture de tests

```bash
# Générer d'abord le rapport de couverture avec PHPUnit
./vendor/bin/phpunit --coverage-clover coverage.xml

# Puis analyser avec la couverture
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --coverage=/project/coverage.xml --report-html=/reports
```

### Avec Hall of Fame/Shame (git blame)

```bash
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --git-blame --report-html=/reports
```

### Afficher le résumé dans le terminal uniquement

```bash
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  analyze --source=/project/src --no-html
```

### Export JSON

```bash
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr13/phpquality \
  analyze --source=/project/src --json=/reports/metrics.json
```

### Lister les types disponibles

```bash
docker run --rm amoifr13/phpquality analyze --list-types
```

### Mode CI (échouer si violations)

```bash
docker run --rm \
  -v $(pwd):/project \
  amoifr13/phpquality \
  analyze --source=/project/src --type=symfony --fail-on-violation
```

---

## Options de la commande `analyze`

| Option | Description |
|--------|-------------|
| `--source`, `-s` | Répertoire source à analyser (requis) |
| `--type`, `-t` | Type de projet (`auto`, `symfony`, `laravel`, etc.) |
| `--report-html` | Répertoire de sortie pour le rapport HTML |
| `--json` | Fichier de sortie pour le rapport JSON |
| `--exclude`, `-x` | Répertoires supplémentaires à exclure (répétable) |
| `--no-html` | Ne pas générer le rapport HTML |
| `--fail-on-violation` | Échouer si des violations sont détectées |
| `--git-blame` | Activer l'analyse git blame pour Hall of Fame/Shame (plus lent) |
| `--coverage`, `-c` | Chemin vers le fichier de couverture Clover XML |
| `--project-name`, `-p` | Nom du projet à afficher dans les titres du rapport |
| `--lang`, `-l` | Langue du rapport (en, fr, de, es, it, pt, nl, pl, ru, ja, zh, ko...) |
| `--list-types` | Lister tous les types de projets disponibles |
| `--list-langs` | Lister toutes les langues disponibles |

---

## Architecture

```
phpquality/
├── docker/
│   ├── Dockerfile
│   └── entrypoint.sh
├── symfony-app/
│   ├── bin/console
│   ├── config/
│   ├── src/
│   │   ├── Analyzer/
│   │   │   ├── Ast/
│   │   │   │   ├── AstParser.php
│   │   │   │   └── Visitor/
│   │   │   │       ├── LinesOfCodeVisitor.php
│   │   │   │       ├── CyclomaticComplexityVisitor.php
│   │   │   │       ├── HalsteadVisitor.php
│   │   │   │       ├── CohesionVisitor.php
│   │   │   │       └── DependencyVisitor.php
│   │   │   ├── Architecture/
│   │   │   │   ├── LayerDetector.php
│   │   │   │   └── SolidAnalyzer.php
│   │   │   ├── ArchitectureAnalyzer.php
│   │   │   ├── CoverageAnalyzer.php
│   │   │   ├── GitBlameAnalyzer.php
│   │   │   ├── Metric/
│   │   │   │   └── MaintainabilityIndex.php
│   │   │   ├── ProjectType/
│   │   │   │   ├── ProjectTypeInterface.php
│   │   │   │   ├── ProjectTypeDetector.php
│   │   │   │   ├── SymfonyProjectType.php
│   │   │   │   ├── LaravelProjectType.php
│   │   │   │   └── ...
│   │   │   ├── FileAnalyzer.php
│   │   │   ├── ProjectAnalyzer.php
│   │   │   └── Result/
│   │   │       ├── ProjectResult.php
│   │   │       ├── FileResult.php
│   │   │       ├── ClassResult.php
│   │   │       ├── ArchitectureResult.php
│   │   │       └── CoverageResult.php
│   │   ├── Command/
│   │   │   └── AnalyzeCommand.php
│   │   └── Report/
│   │       ├── HtmlReportGenerator.php
│   │       └── ConsoleReportGenerator.php
│   ├── templates/
│   │   └── report/
│   │       ├── base.html.twig
│   │       ├── index.html.twig
│   │       ├── metrics.html.twig
│   │       ├── ccn.html.twig
│   │       ├── mi.html.twig
│   │       ├── lcom.html.twig
│   │       ├── loc.html.twig
│   │       ├── halstead.html.twig
│   │       ├── analysis.html.twig
│   │       ├── architecture.html.twig
│   │       ├── coverage.html.twig
│   │       └── dependencies.html.twig
│   ├── translations/
│   │   ├── messages.en.yaml
│   │   ├── messages.fr.yaml
│   │   └── ... (30+ langues)
│   └── composer.json
└── README.md
```

---

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Langage | PHP 8.3 |
| Framework | Symfony 7.x |
| Parseur AST | [nikic/php-parser](https://github.com/nikic/PHP-Parser) |
| Rendu HTML | Twig + Chart.js + D3.js |
| CLI | Symfony Console |
| Image de base | `php:8.3-cli-alpine` |
| Gestion des dépendances | Composer |

---

## Interprétation des métriques

### Maintainability Index (MI)

| Score | Rating | Interprétation |
|-------|--------|----------------|
| 85-100 | A | Hautement maintenable |
| 65-84 | B | Modérément maintenable |
| 40-64 | C | Difficile à maintenir |
| 20-39 | D | Très difficile à maintenir |
| 0-19 | F | Non maintenable |

### Cyclomatic Complexity (CCN)

| Score | Rating | Interprétation |
|-------|--------|----------------|
| 1-4 | A | Faible complexité |
| 5-7 | B | Complexité modérée |
| 8-10 | C | Haute complexité |
| 11-15 | D | Très haute complexité |
| 16+ | F | Complexité excessive |

### Lack of Cohesion (LCOM)

| Score | Rating | Interprétation |
|-------|--------|----------------|
| 0-0.2 | A | Excellente cohésion |
| 0.2-0.4 | B | Bonne cohésion |
| 0.4-0.6 | C | Cohésion modérée |
| 0.6-0.8 | D | Faible cohésion |
| 0.8-1.0 | F | Très faible cohésion |

### Architecture Score

| Score | Rating | Interprétation |
|-------|--------|----------------|
| 85-100 | A | Architecture exemplaire |
| 70-84 | B | Bonne architecture |
| 50-69 | C | Architecture acceptable |
| 30-49 | D | Architecture à améliorer |
| 0-29 | F | Architecture critique |

### Test Coverage

| Score | Rating | Interprétation |
|-------|--------|----------------|
| 80-100% | A | Excellente couverture |
| 60-79% | B | Bonne couverture |
| 40-59% | C | Couverture modérée |
| 20-39% | D | Faible couverture |
| 0-19% | F | Couverture critique |

### Règles de couches (Clean Architecture)

PhpQuality détecte automatiquement les couches et vérifie les règles de dépendances :

| Couche | Peut dépendre de | Ne peut pas dépendre de |
|--------|------------------|-------------------------|
| **Domain** | rien | Application, Infrastructure, Controller |
| **Application** | Domain | Infrastructure, Controller |
| **Infrastructure** | Domain, Application | Controller |
| **Controller** | Domain, Application, Infrastructure | - |

### Violations SOLID détectées

| Principe | Détection | Seuils |
|----------|-----------|--------|
| **SRP** (Single Responsibility) | Classes avec trop de méthodes, dépendances et lignes de code | LCOM > 0.7, méthodes > 20, deps > 15 |
| **OCP** (Open/Closed) | Nombreux switch/match sur type | À venir |
| **ISP** (Interface Segregation) | Interfaces avec trop de méthodes | > 5 méthodes |
| **DIP** (Dependency Inversion) | Ratio dépendances concrètes/abstraites | ratio < 0.5 |

---

## Intégration CI/CD

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
            analyze --source=/project/src \
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
    - analyze --source=/builds/$CI_PROJECT_PATH/src --coverage=/builds/$CI_PROJECT_PATH/coverage.xml --fail-on-violation
  artifacts:
    reports:
      codequality: phpquality.json
```

---

## Feuille de route

- [x] **v0.1** - Analyse basique : LOC, CCN, MI, LCOM + rapport HTML + types de projets
- [x] **v0.2** - Rapport HTML multi-pages avec documentation des métriques
- [x] **v0.3** - Analyse multi-dimensionnelle, arbre du code, Hall of Fame/Shame, analyse des dépendances Composer
- [x] **v0.4** - Analyse d'architecture (couches, violations SOLID, graphe de dépendances D3.js, dépendances circulaires)
- [x] **v1.0** - Option git blame, nom de projet personnalisé
- [x] **v1.1** - Analyse de couverture de tests (Clover XML)
- [ ] **v1.2** - Règles CI configurables, seuils personnalisés
- [ ] **v1.3** - Export PDF, comparaison entre versions
- [ ] **v2.0** - Interface web interactive, historique des analyses

---

## Contribuer

Les contributions sont les bienvenues ! Pour proposer une métrique manquante ou corriger un calcul :

1. Forkez le dépôt
2. Créez une branche : `git checkout -b feature/ma-metrique`
3. Committez vos changements
4. Ouvrez une Pull Request

---

## Licence

MIT - voir le fichier [LICENSE](./LICENSE).

---

## Références

- [phpmetrics/PhpMetrics (GitHub)](https://github.com/phpmetrics/PhpMetrics) - projet original
- [nikic/php-parser](https://github.com/nikic/PHP-Parser)
- [Halstead complexity measures (Wikipedia)](https://en.wikipedia.org/wiki/Halstead_complexity_measures)
- [Cyclomatic complexity (Wikipedia)](https://en.wikipedia.org/wiki/Cyclomatic_complexity)
- [Software package metrics - Robert Martin (Wikipedia)](https://en.wikipedia.org/wiki/Software_package_metrics)
- [Deptrac (GitHub)](https://github.com/qossmic/deptrac) - inspiration pour l'analyse de couches
- [PHP Insights (GitHub)](https://github.com/nunomaduro/phpinsights) - inspiration pour l'analyse de qualité
- [SOLID principles (Wikipedia)](https://en.wikipedia.org/wiki/SOLID)
- [Clean Architecture - Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [PHPUnit Code Coverage](https://phpunit.readthedocs.io/en/9.5/code-coverage-analysis.html)