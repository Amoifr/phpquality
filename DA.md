# 🎨 Direction Artistique — PhpInsight

> Document de référence pour la conception visuelle de l'interface de rapport HTML de **PhpInsight**.  
> Ce document définit les règles typographiques, chromatiques, spatiales et comportementales pour chacun des trois thèmes officiels.

---

## 🧭 Vision générale

PhpInsight est un **outil de mesure scientifique** destiné à des développeurs exigeants. Son interface doit incarner deux valeurs apparemment opposées :

- **La rigueur** : chiffres précis, hiérarchie claire, pas de bruit visuel
- **La lisibilité émotionnelle** : un coup d'œil suffit pour savoir si le code est sain ou dangereux

L'interface emprunte son vocabulaire visuel à l'**instrumentation scientifique** et aux **tableaux de bord d'ingénierie** — oscilloscopes, jauges, dashboards de monitoring — plutôt qu'aux outils marketing ou aux SaaS génériques.

> **Principe directeur** : *"La beauté des données, pas la beauté pour la beauté."*

---

## 🔤 Typographie partagée

Les choix typographiques sont communs aux trois thèmes. Seules les couleurs et les graisses varient.

| Rôle | Police | Source |
|---|---|---|
| **Titres & métriques clés** | `DM Serif Display` | Google Fonts |
| **Interface & labels** | `IBM Plex Sans` | Google Fonts |
| **Valeurs numériques & code** | `JetBrains Mono` | Google Fonts / JetBrains |
| **Annotations & légendes** | `IBM Plex Sans` — style italique | Google Fonts |

**Échelle typographique** (base 16px) :

```
xs  : 11px — Labels de jauges, tooltips
sm  : 13px — Métadonnées, légendes
base: 16px — Corps de texte, valeurs
md  : 20px — Titres de section
lg  : 28px — Métriques principales
xl  : 40px — Score global, valeur mise en avant
2xl : 56px — Titre de rapport (page d'accueil)
```

**Règles typographiques globales :**
- Interlignage corps : `1.65`
- Interlignage titres : `1.1`
- Lettre-espacement titres display : `-0.02em`
- Lettre-espacement monospace : `0.03em`
- Chiffres tabulaires obligatoires (`font-variant-numeric: tabular-nums`) pour toutes les valeurs numériques

---

## 🗂️ Composants UI partagés

Ces composants sont les mêmes dans les trois thèmes ; seul leur habillage coloré change.

### Jauge circulaire (`<MetricGauge>`)
- Cercle SVG avec `stroke-dasharray` animé
- Valeur centrale en `JetBrains Mono` — taille `xl`
- Légende sous la valeur en `IBM Plex Sans sm`
- Couleur de l'arc selon seuil : **vert → ambre → rouge**
- Animation d'entrée : rotation depuis 0 en `600ms ease-out`

### Carte métrique (`<MetricCard>`)
- Bordure gauche colorée (4px) selon criticité
- Valeur principale en `lg`, label en `sm`
- Indicateur de tendance (↑ ↓ →) si historique Git disponible

### Graphe en bulles (`<BubbleMap>`)
- Reproduit le visuel signature de phpmetrics
- Diamètre = lignes de code (`LOC`)
- Couleur = indice de maintenabilité (`MI`)
- Remplissage alternatif (hachures diagonales) en mode daltonien
- Tooltip au survol : nom de classe, CCN, MI, LOC

### Tableau de classes (`<ClassTable>`)
- Tri par colonne, recherche en temps réel
- Coloration conditionnelle des cellules numériques
- Pagination virtuelle (scroll infini)

### Badge de violation (`<ViolationBadge>`)
- Trois niveaux : `info` / `warning` / `critical`
- Icône + libellé + fichier concerné + lien vers ligne

### Graphes croisés (`<CrossDimensionChart>`)

Visualisations avancées croisant deux métriques sur les axes X/Y, avec une troisième dimension représentée par la taille des bulles et une quatrième par la couleur.

#### Configurations prédéfinies

| Nom | Axe X | Axe Y | Taille bulle | Couleur |
|---|---|---|---|---|
| **Complexité vs Taille** | Lignes de code (LOC) | Complexité cyclomatique (CCN) | Nombre de méthodes | Indice de maintenabilité (MI) |
| **Couplage vs Instabilité** | Couplage afférent (Ca) | Couplage efférent (Ce) | LOC | Distance à la séquence principale |
| **Dette technique** | Âge du fichier (jours) | Nombre de violations | LOC | Sévérité max des violations |
| **Couverture vs Risque** | Couverture de tests (%) | CCN | LOC | Nombre de bugs potentiels |
| **Maintenabilité vs Volume** | Indice de maintenabilité (MI) | Volume Halstead | Nombre de classes | Difficulté Halstead |

#### Spécifications visuelles

- **Axes** :
  - Libellés en `IBM Plex Sans sm`
  - Graduation automatique selon l'étendue des données
  - Ligne de référence (seuil critique) en pointillés `--metric-warn`
  - Zones de danger (quadrants) avec fond teinté subtil (`rgba(--metric-bad, 0.05)`)

- **Bulles** :
  - Diamètre : `12px` (min) à `80px` (max), échelle logarithmique
  - Opacité : `0.75` (permet de voir les chevauchements)
  - Bordure : `1.5px solid` couleur légèrement plus foncée que le remplissage
  - Hover : opacité `1`, bordure `--accent`, ombre portée, bulle passe au premier plan (`z-index`)

- **Tooltip au survol** :
  ```
  ┌─────────────────────────────┐
  │ App\Service\PaymentHandler  │
  ├─────────────────────────────┤
  │ LOC          │         342  │
  │ CCN          │          28  │
  │ Méthodes     │          12  │
  │ MI           │          45  │
  ├─────────────────────────────┤
  │ ⚠ Zone à risque             │
  └─────────────────────────────┘
  ```

- **Légende** :
  - Échelle de couleur (gradient) avec valeurs min/max
  - Échelle de taille (3 cercles de référence : petit, moyen, grand)
  - Position : coin inférieur droit du graphe

#### Interactions

- **Zoom** : molette souris ou pinch tactile, zoom centré sur le curseur
- **Pan** : clic-glisser sur le fond du graphe
- **Sélection** : clic sur une bulle ouvre le détail de la classe dans un panneau latéral
- **Filtre** : sélection rectangulaire (shift + glisser) pour isoler un groupe de bulles
- **Reset** : double-clic pour revenir à la vue initiale

#### Quadrants et seuils

Le graphe peut afficher des **zones colorées** délimitant les quadrants critiques :

```
         │
    ⚠    │    ✕
  Risque │  Critique
─────────┼─────────
    ✓    │    ⚠
   Sain  │  À surveiller
         │
```

- Seuils configurables par métrique dans `phpinsight.json`
- Affichage optionnel (toggle dans l'interface)

### Bouton export PDF (`<ExportPdfButton>`)
- Position : header, à droite du score global
- Icône : téléchargement (↓) + libellé "Exporter PDF"
- Style : bouton secondaire, bordure `--border-strong`, fond transparent
- Hover : fond `--bg-subtle`, bordure `--accent`
- Au clic :
  1. Bascule temporaire vers `theme-print`
  2. Masque les éléments interactifs (sidebar, tooltips, boutons)
  3. Déclenche `window.print()` avec destination PDF
  4. Restaure le thème précédent après fermeture du dialogue
- Variante "téléchargement direct" (optionnelle) : génération côté serveur via wkhtmltopdf ou Puppeteer
- Accessibilité : `aria-label="Exporter le rapport au format PDF"`

---

## 🎨 Thème 1 — Classique (`theme-classic`)

> **Inspiration** : Publication scientifique imprimée rencontre dashboard d'ingénierie moderne.  
> Papier légèrement chaud, encre anthracite, accents encre de chine. L'élégance de la précision.

### Palette

| Nom | Valeur | Usage |
|---|---|---|
| `--bg-canvas` | `#F7F5F0` | Fond de page (blanc cassé chaud) |
| `--bg-surface` | `#FFFFFF` | Cartes, panneaux |
| `--bg-subtle` | `#EEECE7` | Fond secondaire, alternance de tableau |
| `--border` | `#D6D2C8` | Bordures et séparateurs |
| `--border-strong` | `#A8A39A` | Bordures actives, focus |
| `--text-primary` | `#1C1A17` | Corps de texte principal |
| `--text-secondary` | `#5C574F` | Labels, annotations |
| `--text-muted` | `#9C9590` | Métadonnées, placeholders |
| `--accent` | `#1D4ED8` | Bleu cobalt — liens, actions, focus |
| `--accent-hover` | `#1E40AF` | État hover |
| `--metric-good` | `#15803D` | Indicateur favorable (vert forêt) |
| `--metric-warn` | `#B45309` | Indicateur ambigu (ambre foncé) |
| `--metric-bad` | `#B91C1C` | Indicateur critique (rouge brique) |
| `--metric-neutral` | `#374151` | Valeur sans seuil |
| `--chart-1` | `#1D4ED8` | Série 1 — bleu cobalt |
| `--chart-2` | `#047857` | Série 2 — vert émeraude |
| `--chart-3` | `#B45309` | Série 3 — ambre |
| `--chart-4` | `#7C3AED` | Série 4 — violet |
| `--chart-5` | `#0E7490` | Série 5 — cyan ardoise |

### Layout

- Largeur maximale du contenu : `1440px`, centré
- Grille principale : `260px` (sidebar fixe) + `1fr` (contenu)
- Gouttières : `24px` entre cartes, `40px` entre sections
- Padding de section : `48px 40px`
- Rayon de bordure des cartes : `6px` (sobre, pas rond)
- Ombre des cartes : `0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)`

### En-tête (Header)

- Fond `--bg-surface` avec bordure basse `--border`
- Logo `PhpInsight` en `DM Serif Display 24px` + tag de version en `JetBrains Mono xs` gris
- Fil d'Ariane discret : `Rapport / src/ / [timestamp]`
- Score global affiché à droite : note lettrée (A–F) en `DM Serif Display 40px` + couleur selon seuil

### Sidebar

- Fond `--bg-subtle`
- Navigation hiérarchique : sections en `IBM Plex Sans sm` gras, sous-sections en poids normal
- Indicateur de section active : barre gauche `4px` en `--accent`
- Sticky sur scroll

### Graphe en bulles (classique)

- Fond `--bg-canvas`
- Bulles avec bordure `1px solid rgba(0,0,0,0.12)`
- Palette spectrale : `#15803D` → `#EAB308` → `#DC2626`
- Grille de fond subtile (lignes pointillées `--border`)

### Animations

- Entrées de cartes : `fadeInUp` — `opacity 0→1` + `translateY 12px→0`, `300ms ease-out`, délai échelonné `50ms` par carte
- Jauges : rotation SVG `600ms cubic-bezier(0.34, 1.56, 0.64, 1)`
- Hover sur carte : `translateY(-2px)` + ombre renforcée, `150ms ease`
- Transitions de couleur : `200ms ease`
- Respect de `prefers-reduced-motion` : toutes les animations désactivées

---

## 🌑 Thème 2 — Dark (`theme-dark`)

> **Inspiration** : Éditeur de code à 3h du matin, terminal de monitoring satellite.  
> Profondeur maximale, contrastes chirurgicaux, accents phosphorescents. Chaque chiffre brille dans le noir.

### Palette

| Nom | Valeur | Usage |
|---|---|---|
| `--bg-canvas` | `#0A0C10` | Fond de page (noir quasi-absolu, légère teinte bleue) |
| `--bg-surface` | `#111318` | Cartes, panneaux |
| `--bg-elevated` | `#181C24` | Couche supérieure, dropdowns |
| `--bg-subtle` | `#1A1F2B` | Fond secondaire, alternance tableau |
| `--border` | `#242B38` | Bordures et séparateurs |
| `--border-strong` | `#3D4A5C` | Bordures actives, focus |
| `--text-primary` | `#E8EBF0` | Corps de texte principal |
| `--text-secondary` | `#8B95A5` | Labels, annotations |
| `--text-muted` | `#4D5768` | Métadonnées, placeholders |
| `--accent` | `#38BDF8` | Cyan électrique — liens, actions, focus |
| `--accent-glow` | `rgba(56,189,248,0.15)` | Halo lumineux autour de l'accent |
| `--metric-good` | `#4ADE80` | Phosphorescent vert |
| `--metric-warn` | `#FBBF24` | Ambre lumineux |
| `--metric-bad` | `#F87171` | Rouge pulsé |
| `--metric-neutral` | `#94A3B8` | Ardoise |
| `--chart-1` | `#38BDF8` | Cyan électrique |
| `--chart-2` | `#4ADE80` | Vert phosphorescent |
| `--chart-3` | `#FBBF24` | Ambre |
| `--chart-4` | `#C084FC` | Violet néon |
| `--chart-5` | `#F472B6` | Rose électrique |

### Layout

- Identique au thème classique en termes de grille et de proportions
- Rayon de bordure des cartes : `8px`
- Ombre des cartes : `0 0 0 1px var(--border), 0 4px 24px rgba(0,0,0,0.4)`
- Effet de profondeur sur les cartes actives : `box-shadow: 0 0 0 1px var(--border-strong), 0 0 20px var(--accent-glow)`

### En-tête (Header)

- Fond `--bg-surface` + bordure basse `--border`
- Effet de verre dépoli : `backdrop-filter: blur(12px)`, position sticky
- Logo avec micro-animation : curseur clignotant (▮) après `PhpInsight` simulant un terminal
- Score global : valeur lettrée avec halo coloré (`text-shadow`) selon criticité

### Sidebar

- Fond `--bg-canvas` (plus sombre que les cartes = profondeur)
- Indicateur actif : fond `--bg-elevated` + bordure gauche `--accent` + léger halo `--accent-glow`
- Séparateurs de section en pointillés `--border`

### Graphe en bulles (dark)

- Fond `--bg-canvas`
- Bulles sans bordure solide — lueur externe (`filter: drop-shadow`) selon criticité
- Effet de particules dormantes en fond (canvas JS, très subtil, 10% opacité)
- Palette : `#4ADE80` → `#FBBF24` → `#F87171`

### Animations

- Entrées : `fadeIn` depuis `opacity: 0`, `translateY: 8px`, plus rapides qu'en classique (`200ms`)
- Jauges : identiques au classique mais l'arc a un `filter: blur(0.5px)` pour effet lumineux
- Valeurs numériques en hausse : flash `--metric-good` → couleur normale en `800ms`
- Valeurs numériques en baisse : flash `--metric-bad` → couleur normale en `800ms`
- Hover carte : `border-color: --border-strong` + halo `--accent-glow`, `150ms ease`
- Scanline subtile en fond : `repeating-linear-gradient` noir semi-transparent, `2px`, 3% opacité (désactivable)

### Accessibilité dark

- Contraste minimum WCAG AA garanti sur tous les textes primaires et secondaires
- Valeurs numériques critiques toujours accompagnées d'une icône (pas seulement la couleur)

---

## 🖨️ Thème 3 — Imprimable (`theme-print`)

> **Inspiration** : Rapport d'audit technique remis à un client, thèse d'ingénierie, publication de revue scientifique.  
> Noir sur blanc absolu. Pas un pixel de couleur qui ne serve à transmettre une information.

### Philosophie

Ce thème n'est **pas un simple `@media print`**. Il constitue une mise en page éditoriale autonome, consultable aussi en navigateur, conçue pour être imprimée ou exportée en PDF sans perte de lisibilité ni gaspillage d'encre.

> **Règle d'or** : Si une couleur de fond consomme de l'encre sans ajouter de sens, elle disparaît.

### Palette

| Nom | Valeur | Usage |
|---|---|---|
| `--bg-canvas` | `#FFFFFF` | Fond de page — blanc absolu |
| `--bg-surface` | `#FFFFFF` | Cartes (pas de distinction fond/carte) |
| `--bg-subtle` | `#F2F2F2` | Alternance de lignes de tableau uniquement |
| `--border` | `#CCCCCC` | Séparateurs fins |
| `--border-strong` | `#333333` | Cadres de sections importantes |
| `--text-primary` | `#000000` | Corps de texte |
| `--text-secondary` | `#444444` | Labels, annotations |
| `--text-muted` | `#888888` | Métadonnées |
| `--accent` | `#000000` | Liens (soulignés), titres forts |
| `--metric-good` | `#000000` + motif `✓` | Favorable — texte + symbole |
| `--metric-warn` | `#000000` + motif `⚠` | Ambigu — texte + symbole |
| `--metric-bad` | `#000000` + motif `✕` | Critique — texte + symbole |

> **Note** : les niveaux de criticité sont rendus par des **symboles** et du **gras**, jamais par la couleur seule.

### Layout & pagination

- Largeur de page : `210mm` (A4) ou `8.5in` (Letter) — configurable
- Marges : `20mm` haut/bas, `25mm` gauche/droite
- Colonne unique, pas de sidebar
- Saut de page automatique (`page-break-before: always`) entre sections principales
- Pas d'éléments sticky ou flottants
- Grille de contenu : `max-width: 170mm`, centrée

### En-tête de rapport

```
┌─────────────────────────────────────────────────────────┐
│  PhpInsight — Rapport d'analyse statique                │
│  Projet : monprojet/src          Version : 2.1.0        │
│  Date   : 2025-03-19             Score   : B (76/100)   │
└─────────────────────────────────────────────────────────┘
```
- Encadré complet, ligne de séparation `1px solid #000`
- Police : `DM Serif Display` pour le titre, `IBM Plex Sans` pour les méta
- En-tête répété sur chaque page via CSS (`@page`, `position: running(header)`)

### En-tête de page courant

```
PhpInsight — monprojet/src                          Page 3 / 12
────────────────────────────────────────────────────────────────
```

### Typographie imprimable

- Taille de base : `11pt`
- Titres de section : `16pt`, `DM Serif Display`, `font-weight: 700`
- Sous-titres : `13pt`, `IBM Plex Sans`, `font-weight: 600`, lettres espacées `0.05em` majuscules
- Valeurs numériques : `JetBrains Mono 11pt`
- Interlignage : `1.5` (optimisé lecture papier)

### Tableaux

- Bordure extérieure : `1.5pt solid #000`
- Bordures internes horizontales : `0.5pt solid #CCCCCC`
- Pas de bordures verticales internes
- En-tête de tableau : fond `#000`, texte `#FFF`, `IBM Plex Sans sm gras`
- Alternance de lignes : `#F2F2F2` / `#FFFFFF`
- Répétition de l'en-tête sur chaque page (`thead` avec `display: table-header-group`)

### Graphe en bulles (imprimable)

- Version simplifiée : **tableau de données** en remplacement du graphe SVG interactif
- Si rendu SVG : niveaux de gris uniquement + motifs de hachures pour distinguer les niveaux
  - Favorable : blanc
  - Ambigu : gris clair `#AAAAAA`
  - Critique : gris foncé `#444444` + hachures diagonales
- Pas de `filter`, `backdrop-filter`, `box-shadow`

### Éléments supprimés à l'impression

```css
@media print {
  /* Éléments sans valeur à l'impression */
  .sidebar,
  .theme-switcher,
  .search-bar,
  .tooltip,
  .chart-interactive,
  .btn,
  nav { display: none; }

  /* Éviter les coupures malheureuses */
  .metric-card,
  .violation-block,
  tr { page-break-inside: avoid; }
}
```

### Notation de criticité (sans couleur)

| Niveau | Rendu imprimable |
|---|---|
| Bon | `✓ 87` — valeur précédée d'une coche |
| Avertissement | `⚠ 12` — valeur précédée d'un triangle |
| Critique | `✕ 42` — valeur en **gras** précédée d'une croix |
| Neutre | `→ 5` — valeur précédée d'une flèche |

---

## ♿ Accessibilité transversale

Ces règles s'appliquent aux **trois thèmes** sans exception.

| Règle | Détail |
|---|---|
| **Contraste WCAG AA minimum** | Ratio 4.5:1 pour le texte normal, 3:1 pour les grands textes et composants UI |
| **Mode daltonien** | Case à cocher globale remplaçant les couleurs des bulles par des hachures SVG |
| **Navigation clavier** | Tous les éléments interactifs atteignables au clavier, focus visible |
| **ARIA** | Rôles `region`, `table`, `meter` sur les jauges (`aria-valuenow`, `aria-valuemin`, `aria-valuemax`) |
| **Pas de sens par la couleur seule** | Toujours doublon icône/motif + couleur pour les niveaux de criticité |
| **Taille minimale des cibles** | 44×44px pour tous les boutons et liens |
| **`prefers-reduced-motion`** | Toutes les animations désactivées ou réduites à `opacity` uniquement |
| **`prefers-color-scheme`** | Sélection automatique `classic` / `dark` selon préférence système (overridable manuellement) |

---

## 🧩 Variables CSS — Contrat d'interface

Chaque thème **doit implémenter l'intégralité** de ces variables. Aucun composant ne doit référencer une valeur codée en dur.

```css
:root {
  /* Surfaces */
  --bg-canvas: ;
  --bg-surface: ;
  --bg-subtle: ;
  --bg-elevated: ;

  /* Bordures */
  --border: ;
  --border-strong: ;

  /* Texte */
  --text-primary: ;
  --text-secondary: ;
  --text-muted: ;

  /* Accent */
  --accent: ;
  --accent-hover: ;
  --accent-glow: ;

  /* Métriques */
  --metric-good: ;
  --metric-warn: ;
  --metric-bad: ;
  --metric-neutral: ;

  /* Graphes */
  --chart-1: ;
  --chart-2: ;
  --chart-3: ;
  --chart-4: ;
  --chart-5: ;

  /* Typographie */
  --font-display: 'DM Serif Display', Georgia, serif;
  --font-body: 'IBM Plex Sans', system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;

  /* Espacements */
  --radius-sm: 4px;
  --radius-md: 6px;
  --radius-lg: 12px;
  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 40px;
  --space-2xl: 64px;

  /* Transitions */
  --transition-fast: 150ms ease;
  --transition-base: 200ms ease;
  --transition-slow: 300ms ease;
}
```

---

## 📁 Structure des fichiers CSS

```
assets/
├── styles/
│   ├── base/
│   │   ├── _reset.css          ← Reset moderne (box-sizing, margins)
│   │   ├── _typography.css     ← Imports Google Fonts + échelle
│   │   └── _variables.css      ← Contrat de variables (valeurs vides)
│   ├── themes/
│   │   ├── classic.css         ← Implémente toutes les variables
│   │   ├── dark.css            ← Implémente toutes les variables
│   │   └── print.css           ← Implémente toutes les variables + @media print
│   ├── components/
│   │   ├── _gauge.css
│   │   ├── _card.css
│   │   ├── _bubble-map.css
│   │   ├── _table.css
│   │   ├── _badge.css
│   │   └── _sidebar.css
│   └── main.css                ← Import orchestrateur
└── js/
    ├── theme-switcher.js       ← Gestion du switcher + localStorage
    ├── bubble-chart.js         ← D3.js bubble map
    ├── colorblind-mode.js      ← Remplacement motifs SVG
    ├── print-helper.js         ← Nettoyage DOM avant impression
    └── pdf-export.js           ← Logique du bouton export PDF
```

---

## 🔀 Sélection et persistance du thème

```javascript
// Priorité de sélection du thème au chargement
const theme =
  localStorage.getItem('phpinsight-theme')   // 1. Préférence explicite
  ?? (window.matchMedia('(prefers-color-scheme: dark)').matches
      ? 'dark' : 'classic')                  // 2. Préférence système
  ?? 'classic';                              // 3. Fallback

document.documentElement.setAttribute('data-theme', theme);
```

Le thème `print` est activé :
- Automatiquement via `@media print`
- Manuellement via un bouton "Aperçu impression" dans l'interface

---

## 🌍 Internationalisation (i18n)

PhpInsight supporte plusieurs langues pour l'interface et les rapports générés.

### Langues supportées

| Code | Langue | Statut |
|---|---|---|
| `fr` | Français | Langue par défaut |
| `en` | English | Complet |
| `de` | Deutsch | Prévu |
| `es` | Español | Prévu |

### Architecture des traductions

```
src/
└── Resources/
    └── i18n/
        ├── fr.json      ← Fichier de référence
        └── en.json
```

Les traductions sont injectées dans le template HTML au moment de la génération par le moteur PHP.

### Structure des fichiers de traduction

```json
{
  "meta": {
    "locale": "fr",
    "direction": "ltr",
    "dateFormat": "DD/MM/YYYY",
    "numberFormat": {
      "decimal": ",",
      "thousands": " "
    }
  },
  "common": {
    "score": "Score",
    "class": "Classe",
    "method": "Méthode",
    "file": "Fichier",
    "line": "Ligne",
    "lines": "Lignes",
    "export_pdf": "Exporter PDF",
    "print": "Imprimer",
    "search": "Rechercher...",
    "no_results": "Aucun résultat",
    "loading": "Chargement..."
  },
  "metrics": {
    "loc": "Lignes de code",
    "ccn": "Complexité cyclomatique",
    "mi": "Indice de maintenabilité",
    "coverage": "Couverture de tests",
    "coupling_afferent": "Couplage afférent",
    "coupling_efferent": "Couplage efférent",
    "halstead_volume": "Volume Halstead",
    "halstead_difficulty": "Difficulté Halstead"
  },
  "levels": {
    "good": "Bon",
    "warning": "À surveiller",
    "critical": "Critique",
    "neutral": "Neutre"
  },
  "sections": {
    "overview": "Vue d'ensemble",
    "classes": "Classes",
    "methods": "Méthodes",
    "violations": "Violations",
    "dependencies": "Dépendances",
    "trends": "Tendances"
  },
  "charts": {
    "complexity_vs_size": "Complexité vs Taille",
    "coupling_vs_instability": "Couplage vs Instabilité",
    "technical_debt": "Dette technique",
    "coverage_vs_risk": "Couverture vs Risque",
    "maintainability_vs_volume": "Maintenabilité vs Volume"
  },
  "violations": {
    "info": "Information",
    "warning": "Avertissement",
    "critical": "Critique",
    "count": "{count} violation | {count} violations"
  },
  "report": {
    "title": "Rapport d'analyse statique",
    "generated_at": "Généré le {date}",
    "project": "Projet",
    "version": "Version",
    "page": "Page {current} / {total}"
  }
}
```

### Sélection de la langue

La langue est définie **à la génération du rapport**, pas dans l'interface. Le rapport HTML généré est monolingue.

#### Ligne de commande

```bash
# Via l'option --locale
phpinsight analyse src/ --locale=fr
phpinsight analyse src/ --locale=en

# Via l'option courte -l
phpinsight analyse src/ -l fr
```

#### Fichier de configuration

```json
// phpinsight.json
{
  "locale": "fr"
}
```

#### Priorité de résolution

1. Option CLI (`--locale`) — priorité maximale
2. Variable d'environnement `PHPINSIGHT_LOCALE`
3. Fichier `phpinsight.json` (`locale`)
4. Fallback : `fr`

> **Note** : Le rapport généré embarque uniquement les traductions de la langue sélectionnée (pas de changement dynamique côté client).

### Formatage localisé

| Type | Français | English |
|---|---|---|
| **Nombres** | `1 234,56` | `1,234.56` |
| **Dates** | `19/03/2025` | `03/19/2025` ou `19 Mar 2025` |
| **Pourcentages** | `87,5 %` | `87.5%` |
| **Durées** | `il y a 3 jours` | `3 days ago` |

### Pluralisation

Utilisation du format ICU MessageFormat pour les pluriels :

```javascript
// Exemple : "{count} violation | {count} violations"
t('violations.count', { count: 1 })  // → "1 violation"
t('violations.count', { count: 5 })  // → "5 violations"
```

### Traduction des violations

Les messages de violation proviennent des analyseurs (PHPStan, Psalm, etc.). PhpInsight fournit :
- Une traduction des **catégories** de violations
- Les **messages détaillés** restent en anglais (langue source des outils)
- Option future : traduction automatique via API (configurable)

### Fichier de configuration complet

```json
// phpinsight.json
{
  "locale": "fr",
  "i18n": {
    "fallbackLocale": "en"
  }
}
```

---

*Document maintenu par l'équipe PhpInsight — à mettre à jour à chaque évolution visuelle majeure.*
