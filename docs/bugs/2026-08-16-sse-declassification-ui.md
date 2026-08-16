# Déclassification SSE — UI + PDF au niveau choisi

## Contexte

Écran `/atak/sse/dossiers/{id}/declassification` et export PDF du dossier.

## Symptôme

Écran dense (notices empilées, `<pre>` terminal). Le PDF via `/pdf` ignorait `?niveau=` et exportait toujours au plafond d’habilitation.

## Correctif

- UI : hero, bandeau habilitation compact, niveaux colorés, aperçus feuille, barres noires, formulaire filtré.
- Bouton PDF au niveau affiché.
- `casePdf` / `casePdfStream` : clamp de `?niveau=` ; nom de fichier `SSE-…-expurge-…`.

## Fichiers touchés

- `views/atak/sse/case_declassify.php`
- `public/assets/css/sse_portal.css`
- `app/Controllers/Web/SsePortalController.php`
- `app/Services/Sse/SseCasePdfService.php`

## Vérification

- Déclassification niveau interne → PDF cohérent.
- Forcer un niveau trop haut → rabattu + journal.
- Builds Arma SSE + Overwatch OK (2026-08-16).

## Statut

Corrigé
