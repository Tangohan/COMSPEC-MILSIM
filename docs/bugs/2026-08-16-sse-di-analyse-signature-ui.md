# SSE DI — UI Analyse / Traçabilité + signature numérique

## Contexte

Formulaire d’ouverture d’un dossier d’intérêt : blocs « Analyse » et « Traçabilité » trop plats (grilles de textarea sans hiérarchie), sans signature numérique alors que les Documents SSE en ont une.

## Symptôme

- Lecture difficile des rubriques d’analyse.
- Pas de confirmation d’auteur / horodatage de signature à l’enregistrement.

## Cause

Maquette minimale initiale (`grid-2` + textarea) sans composant signature ni persistance dédiée.

## Correctif

- Cartes d’analyse A–G avec intitulés et aides.
- Bloc Traçabilité : source / fiabilité + signature numérique (style Documents), case à cocher obligatoire.
- Colonnes `signed_by_label` / `signed_at` (migration enrichissement DI).
- Affichage signature + grille d’analyse sur la fiche dossier.

## Fichiers touchés

- `views/atak/sse/interest_case_form.php`
- `views/atak/sse/interest_case_show.php`
- `public/assets/css/sse_portal.css`
- `app/Controllers/Web/SsePortalController.php`
- `app/Repositories/SseInterestCaseRepository.php`
- `bootstrap/atak_sse_interest_case_enrichment_migration.php`

## Vérification

- [ ] Ouvrir `/atak/sse/interet/nouveau` — blocs Analyse structurés, signature à droite
- [ ] Enregistrement sans case cochée → message d’erreur
- [ ] Enregistrement signé → fiche avec tampon « Original signé »
- [ ] Migration : colonnes présentes après `run-migrations`

## Statut

corrigé
