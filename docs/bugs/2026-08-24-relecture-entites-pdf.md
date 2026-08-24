# Relecture ATAK — traces incomplètes, panneau et PDF

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Onglet Relecture / bilan après-action sur la carte.

## Symptôme

À un instant donné, une seule personne sur la carte. Les IA alliées et les téléphones GPS n’apparaissent pas comme tels. Panneau encombré. Export PDF illisible (titre technique, listes brutes).

## Cause

1. Chaque enregistrement avait son propre horodatage : un « instantané » = une seule trace.
2. La lecture carte forçait toutes les traces en « ami / OK » et jetait le type (téléphone, IA, balise).
3. Le PDF recopiait l’identifiant interne et des compteurs techniques.

## Correctif

- Instantanés regroupés (2 s) : toutes les traces encore vues depuis 90 s restent sur la carte.
- Type conservé : opérateur, unité alliée, téléphone, balise GPS.
- Panneau allégé + légende.
- PDF : en-tête, moyens par type, liste des traces, chronologie.

## Fichiers touchés

- `app/Services/Replay/ReplayTimelineBuilder.php`
- `app/Services/Replay/ReplayService.php`
- `app/Services/Replay/ReplayAarPdfService.php`
- `app/Controllers/Api/ReplayController.php`
- `app/Controllers/Api/OperationsApiController.php`
- `public/assets/js/atak-replay.js`
- `public/assets/css/atak.css`
- `views/atak.php`
- `tests/Unit/ReplayTimelineBuilderTest.php`

## Vérification

`php vendor/bin/phpunit tests/Unit/ReplayTimelineBuilderTest.php`  
Relecture : Lecture — opérateurs, IA et téléphones ensemble. Export PDF sans identifiant `mission_…_map_…`.

## Statut

corrigé
