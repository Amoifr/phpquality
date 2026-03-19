# PhpQuality - Analyseur statique PHP

> Analyseur statique PHP distribué via Docker, conçu pour remplacer `phpmetrics/phpmetrics` (désormais non maintenu).
> Analyse votre code PHP et génère des rapports détaillés sur la complexité, la maintenabilité, le couplage et bien plus encore.

**Auteur:** [Pascal CESCON](https://moi.ruedesjasses.fr)
**GitHub:** [amoifr/PhpQuality](https://github.com/amoifr/PhpQuality)

---

## Fonctionnalités

### Métriques v0.1

| Métrique | Description |
|----------|-------------|
| **LOC** | Lines of Code (total, CLOC sans commentaires, LLOC logiques) |
| **CCN** | Complexité cyclomatique de McCabe par méthode |
| **MI** | Maintainability Index (0-100, plus élevé = meilleur) |
| **LCOM** | Lack of Cohesion of Methods (0-1, plus bas = meilleur) |
| **Halstead** | Volume, Difficulté, Effort, Bugs estimés |

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
docker build -t amoifr/phpquality -f docker/Dockerfile .

# Ou utiliser l'image pré-construite (quand disponible)
docker pull amoifr/phpquality
```

### Analyse rapide

```bash
# Analyse avec auto-détection du type de projet
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr/phpquality \
  analyze --source=/project/src --report-html=/reports
```

### Spécifier le type de projet

```bash
# Projet Symfony
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr/phpquality \
  analyze --source=/project/src --type=symfony --report-html=/reports

# Projet PrestaShop
docker run --rm \
  -v $(pwd):/project \
  amoifr/phpquality \
  analyze --source=/project/modules/mymodule --type=prestashop

# Projet WordPress
docker run --rm \
  -v $(pwd):/project \
  amoifr/phpquality \
  analyze --source=/project/wp-content/plugins/myplugin --type=wordpress

# Projet Magento 2
docker run --rm \
  -v $(pwd):/project \
  amoifr/phpquality \
  analyze --source=/project/app/code/Vendor/Module --type=magento
```

### Afficher le résumé dans le terminal uniquement

```bash
docker run --rm \
  -v $(pwd):/project \
  amoifr/phpquality \
  analyze --source=/project/src --no-html
```

### Export JSON

```bash
docker run --rm \
  -v $(pwd):/project \
  -v $(pwd)/reports:/reports \
  amoifr/phpquality \
  analyze --source=/project/src --json=/reports/metrics.json
```

### Lister les types disponibles

```bash
docker run --rm amoifr/phpquality analyze --list-types
```

### Mode CI (échouer si violations)

```bash
docker run --rm \
  -v $(pwd):/project \
  amoifr/phpquality \
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
| `--list-types` | Lister tous les types de projets disponibles |

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
│   │   │   │       └── CohesionVisitor.php
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
│   │       └── halstead.html.twig
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
| Rendu HTML | Twig + Chart.js |
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

---

## Feuille de route

- [x] **v0.1** - Analyse basique : LOC, CCN, MI, LCOM + rapport HTML + types de projets
- [x] **v0.2** - Rapport HTML multi-pages avec documentation des métriques
- [ ] **v0.3** - Exports JSON, CSV, XML violations
- [ ] **v0.4** - Graphe de dépendances, PageRank, couplage afférent/efférent
- [ ] **v0.5** - Règles CI configurables, mode `failIfFound`, intégration GitHub Actions
- [ ] **v0.6** - Plugin Git, corrélation historique/métriques
- [ ] **v1.0** - Rapport HTML complet (cercles, daltonisme, filtres, groupes)

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
