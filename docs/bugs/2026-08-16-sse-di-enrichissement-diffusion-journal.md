# SSE — fiche dossier d’intérêt trop maigre (détail, diffusion, journal)

## Contexte

Page `Athena / SSE / Dossiers d’intérêt / DI-…` : lecture utile pour l’instruction, mais sans description éditable, sans journal horodaté, sans destinataires / interdits nominatifs, sans délais sur les actions sensibles, et avec une présentation trop plate (peu de « tampons » métier).

## Symptôme

- Peu de contenu exploitable en tête de fiche (pas de synthèse dédiée).
- Impossible de restreindre ou d’interdire l’accès à des membres nommés.
- Pas de fil de mises à jour datées.
- Actions sensibles (état, constitution, rapprochements, investigation) sans pause humaine.
- Esthétique moins « dossier classifié » que le reste du portail SSE.

## Cause

Le modèle `sse_interest_cases` et la vue `interest_case_show` couvraient l’instruction analytique, mais pas la diffusion nominative, le journal ni les délais d’action. Aucune table ACL / updates / cooldowns n’existait.

## Correctif

- Migration d’enrichissement : colonne `description`, tables `sse_interest_case_acl`, `sse_interest_case_updates`, `sse_interest_case_cooldowns`.
- Contrôles d’accès : destinataires (liste fermée si renseignée) + interdits nominatifs ; contournement réservé à une habilitation forte.
- Délais serveur + libellés métier sur changement d’état, soumission à validation, constitution, rapprochements, ouverture d’investigation.
- UI enrichie : tampons d’état / priorité / validation humaine, description, journal, diffusion par cases à cocher.

## Fichiers touchés

- `bootstrap/atak_sse_interest_case_enrichment_migration.php`
- `run-migrations.php`
- `app/Repositories/SseInterestCaseRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`
- `views/atak/sse/interest_case_show.php`
- `views/atak/sse/interest_case_form.php`
- `public/assets/css/sse_portal.css`

## Vérification

1. Ouvrir un DI existant : tampons visibles, blocs Description / Mises à jour / Diffusion.
2. Cocher des destinataires → un autre membre hors liste ne voit plus la fiche ; un interdit non plus.
3. Changer l’état deux fois de suite → message de délai en français.
4. Ajouter une mise à jour → entrée horodatée dans le journal.

## Statut

corrigé
