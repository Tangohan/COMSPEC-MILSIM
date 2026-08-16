# Emport / import scénarios SSE

## Contexte

Besoin de générer des dossiers fictifs complets (IA), les importer en mode gestion Athena, et les emporter vers Arma 3.

## Symptôme

Pas de parcours unifié : prompts dispersés, pas d’import de pack dossier, pas d’emport terrain depuis une affaire.

## Cause

Seul l’atelier de modèles Arma (`comspec_sse_model`) proposait un export JSON/SQF ; les dossiers d’affaire n’avaient pas de bundle.

## Correctif

- Format `comspec_sse_case_bundle` + pack Arma `comspec_sse_mission_pack`
- Service `SseCaseBundleService` (import / export / SQF)
- UI : `/atak/sse/dossiers/importer` + boutons d’emport sur la fiche dossier
- Docs prompts GPT/Claude : `docs/sse/prompts-dossiers-fictifs-json.md`

## Fichiers touchés

- `config/sse_case_bundle.php`
- `app/Services/Sse/SseCaseBundleService.php`
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`
- `views/atak/sse/case_import.php`, `case_show.php`, `cases.php`, `dev/*`, `guide/_part_*`
- `docs/sse/prompts-dossiers-fictifs-json.md`, `README.md`, `examples/case-bundle-exemple.json`

## Vérification

1. Droits gestion → Importer l’exemple JSON → dossier peuplé.
2. Emport athena / arma / sqf depuis la fiche.
3. Réimport du pack arma (contient athena_bundle).

## Statut

corrigé
