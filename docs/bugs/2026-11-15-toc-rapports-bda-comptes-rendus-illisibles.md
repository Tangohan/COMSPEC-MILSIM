# Poste TOC web : impossible de lire les BDA et les comptes rendus

## Contexte

Un opérateur du poste TOC web (`/atak/toc`) ne pouvait pas lire les BDA ni les comptes rendus envoyés par les téléphones ATAK. « CR » désigne les comptes rendus de l'app Comptes rendus du téléphone (contact TIC, SALUTE, SPOTREP, SITREP, patrouille, blessé, fin d'opération), enregistrés dans `atak_tactical_reports`. Un BDA (Battle Damage Assessment, évaluation des dommages après un tir) arrive de l'app BDA ou de l'app Comptes rendus, dans la même table.

## Symptôme

Les rapports n'existaient dans le poste que comme ligne du « Flux » (heure, type, émetteur, grille) et comme épingle générique : texte seulement dans une info-bulle (invisible au doigt), aucun détail, aucun résultat de BDA, aucun 9-line ni MEDEVAC, rien lu ou non lu.

## Cause

Le poste n'avait pas de lecteur de rapports : une seule liste de 80 lignes tirée de l'API de la carte, sans fiche ni filtre ; les 9-line et MEDEVAC (`atak_nine_line`) n'étaient lus par aucune vue du poste ; les passages caviardés étaient masqués au niveau le plus bas pour tous, sans tenir compte de l'habilitation de l'opérateur.

## Correctif

Panneau Rapports (liste filtrable, fiche par type dans le tiroir, épingles par type, lu / non lu, accusé, jonction à un plan, PDF), lecture bornée par la communauté de la session et l'habilitation de l'opérateur. Voir `docs/TOC-WEB.md`, section « Panneau Rapports ».

## Fichiers touchés

- `app/Services/AtakToc/TocReportService.php`, `TocReportCatalog.php`, `TocReportPdf.php`
- `app/Controllers/Api/AtakTocReportsApiController.php`, `AtakTocApiController.php`, `routes/web.php`
- `public/assets/js/toc/toc-reports.js`, `toc-console.js`, `toc-layers.js`, `public/assets/css/atak-toc-reports.css`
- `database/steps/4740_atak_toc_report_reads.php`, `migrations/2026_11_16_200_atak_toc_report_reads.sql`

## Vérification

Tests `AtakTocReport*Test` et `AtakTocReportsApiAccessTest` sur 40 rapports réalistes ; captures du panneau, de chaque type de fiche, des épingles et de l'état vide à 1440, 1024 et 390 px.

## Statut

corrigé (à vérifier en jeu : envoi d'un BDA, d'un SITREP, d'un 9-line CAS et d'un MEDEVAC depuis un téléphone, puis lecture dans le poste)
