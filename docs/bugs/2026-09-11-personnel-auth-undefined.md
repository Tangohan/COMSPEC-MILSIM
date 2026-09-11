# Fiche personnel — `auth()` inexistant

## Contexte

11 septembre 2026. Production Athena. GET `/personnel/{slug}?onglet=resume` (ex. jake-gylenhall). Corrélation `3dd6c0f7aa0696a6`.

## Symptôme

Page dossier personnel en erreur : `Call to undefined function auth()`.

## Cause

Dans `views/personnel/file.php`, un hint « forcer une remontée » comparait le visiteur au dossier via `auth()['id']`. Ce helper Laravel n’existe pas dans COMSPEC ; le fichier utilise déjà `Session::get('user_id')` / `$viewerIsPersonnelSubject`.

## Correctif

Réutiliser `$viewerIsPersonnelSubject` (même logique, déjà calculée en tête de vue).

## Fichiers touchés

- `views/personnel/file.php`

## Vérification

Ouvrir une fiche personnel (onglet résumé) avec temps de jeu affiché : la page charge ; le hint « Remonter le temps » n’apparaît que sur son propre dossier.

## Statut

corrigé
