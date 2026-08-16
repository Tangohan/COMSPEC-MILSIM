# SSE Intelligence Workspace — LOT 4 (Cycle de renseignement)

Date : 2026-08-16

## Objectif

Couvrir le cycle analyste dans le workspace :

- exigences de collecte (priorité de renseignement / besoin spécifique / élément essentiel) ;
- ordres de collecte (tasking) ;
- composition de comptes rendus ;
- validation ;
- sanitisation (caviardage selon niveau de diffusion) ;
- diffusion vers des destinataires nommés.

Sans casser le portail legacy (`/operations`, dossiers, DI) ni les lots 1–3.

## Schéma

Migration : `bootstrap/atak_sse_intel_cycle_lot4_migration.php` (branchée dans `run-migrations.php`).

| Table | Rôle |
|-------|------|
| `sse_intel_requirements` | Exigences PIR / SIR / EEI |
| `sse_intel_taskings` | Ordres de collecte liés à une exigence / un dossier |
| `sse_intel_products` | Produits (flash, initial, mise à jour, synthèse) |
| `sse_intel_product_recipients` | Destinataires de diffusion |

Optionnel : `sse_case_intel_gaps.requirement_id` si la table gaps existe.

## Catalogue

`App\Support\SseIntelCycleCatalog` — libellés FR métier ; clés techniques uniquement côté code.

Niveaux de diffusion **alignés** sur `SseCaseRepository` / `SseRedactionService` :
`interne` · `encadrement` · `confidentiel` · `tres_restreint`.

## Service

`App\Services\Sse\SseIntelCycleService` :

1. créer / suivre une exigence ;
2. émettre un ordre de collecte ;
3. générer un produit via `SseReportService` (+ niveau de diffusion) ;
4. valider ;
5. sanitiser (recomposition caviardée) ;
6. diffuser (liste de destinataires) ;
7. journaliser via événements `INTEL_CYCLE` / fondation intel.

## API

- `GET /api/sse/v1/cycle`
- `POST /api/sse/v1/cycle/requirements`
- `POST /api/sse/v1/cycle/requirements/{id}/statut`
- `POST /api/sse/v1/cycle/taskings`
- `POST /api/sse/v1/cycle/taskings/{id}`
- `POST /api/sse/v1/cycle/products/generer`
- `POST /api/sse/v1/cycle/products/{id}/valider|sanitiser|diffuser`

## UI portail

Onglet **Cycle** du workspace (`#cycle`) :

- liste des exigences / ordres / produits ;
- formulaires métier (pas de slugs ni JSON) ;
- actions Valider / Sanitiser / Diffuser sur chaque produit ;
- résumé des compteurs dans la chemise dossier + lien vers l’onglet.

Routes web : `POST /atak/sse/workspace/cycle/exigences|ordres|produits/generer|produits/{id}`.

## Vérification

1. Lancer les migrations (LOT 4 cycle).
2. Ouvrir le workspace avec une chemise (`?case=…`).
3. Créer une priorité de renseignement → émettre un ordre → composer un flash.
4. Valider → sanitiser en « Encadrement » → diffuser à un destinataire nommé.
5. Contrôler la timeline : événements de cycle visibles.
6. `GET /api/sse/v1/cycle?case_id=…` renvoie le même tableau de bord.

## Hors LOT 4 (reporté)

Accusés de réception automatiques, modèles de produit hors flash/initial, export PDF cycle dédié (réutiliser `SseCasePdfService` si besoin).
