# Bug / évolution — PDF dossier SSE complet

## Contexte

Demande d’un export PDF **complet** d’un dossier SSE (pas seulement synthèse + listes).

## Symptôme

L’export `/atak/sse/dossiers/{id}/pdf` ne contenait que synthèse, personnes (noms), notes et libellés de preuves — sans flash, compte rendu, sites, saisies, corrélations ni images.

## Cause

`SseCasePdfService` était un export minimal.

## Correctif

Regénération multi-pages : couverture, flash, compte rendu initial, personnes (biométrie / croisement), sites (pièces + saisies), corrélations, notes, preuves + images jointes. Respect du niveau d’habilitation (`gatherForRelease`).

## Fichiers touchés

- `app/Services/Sse/SseCasePdfService.php`
- `app/Controllers/Web/SsePortalController.php` (journal)
- `views/atak/sse/case_show.php`
- `views/atak/sse/case_report.php`
- `views/atak/sse/reports.php`

## Vérification

- Ouvrir un dossier avec sites / preuves / notes
- Cliquer « Exporter le dossier complet (PDF) »
- Contrôler les sections et le bandeau d’habilitation

## Statut

corrigé
