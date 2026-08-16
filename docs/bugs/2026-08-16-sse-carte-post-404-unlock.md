# Carte dossier SSE — POST `/carte` en 404

## Contexte
Panneau carte tactique d’un dossier (`sse-case-map.js`) : sauvegarde de vue et ajout de pings.

## Symptôme
```
POST …/atak/sse/dossiers/{id}/carte 404
POST …/atak/sse/dossiers/{id}/carte/points 404
```
La page dossier s’affiche normalement ; seuls les POST carte échouent.

## Cause
Les actions carte testaient `!$this->caseUnlocked($id)`.
Or `caseUnlocked` n’est rempli que après saisie d’un code dossier.
Sans code (`has_unlock_code` vide), `caseNeedsUnlock` laisse consulter le dossier, mais `caseUnlocked` reste faux → faux 404 JSON « Dossier inaccessible ».

## Correctif
Helper `requireWritableCase()` = `requireCase` + `caseNeedsUnlock` (même règle que la consultation).
Remplace les 10 contrôles `requireCase || !caseUnlocked` (carte, capture, analyse, lacunes, décisions, relations).
Messages d’erreur JS un peu plus explicites.

## Fichiers touchés
- `app/Controllers/Web/SsePortalController.php`
- `public/assets/js/sse-case-map.js`
- `views/atak/sse/case_show.php` (cache-bust JS)

## Vérification
Ouvrir un dossier sans code → déplacer la carte / poser un ping → POST 200/201, point persistant au rechargement.

## Statut
corrigé
