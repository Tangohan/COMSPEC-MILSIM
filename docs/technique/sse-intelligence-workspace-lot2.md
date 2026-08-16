# SSE Intelligence Workspace — LOT 2

Date : 2026-08-16

## Objectif

Transformer `/atak/sse/workspace` en surface analyste complète : Inbox actionnable, chemise dossier, timeline filtrable, graphe, recherche universelle, palette Ctrl+K.

## Surfaces

| Zone | Comportement |
|------|----------------|
| Inbox | DI + suggestions moteur + relations proposées + acquisitions + « depuis dernière visite » ; Valider / Rejeter |
| Chemise | `?case={id}` — cycle de vie, priorité, entités, pièces, notes, relations, timeline, audit |
| Chronologie | `sse_intel_events` + curseur « jusqu’à » |
| Graphe | SVG profondeur 1–3, filtre proposed/confirmed, sélection → contexte |
| Recherche | `/api/sse/v1/search` groupée (personnes, sites, dossiers, pistes, événements) |
| Palette | Ctrl+K — recherche live + actions rapides |

## API ajoutées

- `GET /api/sse/v1/inbox`
- `POST /api/sse/v1/inbox/decide`
- `GET /api/sse/v1/graph?root=&depth=&case_id=`
- `GET /api/sse/v1/search?q=`
- `GET|POST /api/sse/v1/cases/{id}/folder`
- `POST /api/sse/v1/relations/{id}/supprimer`

Web : `POST /atak/sse/workspace/inbox/decide`, `POST /atak/sse/workspace/dossiers/{id}/meta`

## Schéma

- `sse_analyst_cursors` (tenant_id, user_id, last_seen_at) — dans la migration fondation

## Fichiers clés

- `app/Services/Sse/SseIntelligenceWorkspaceService.php`
- `app/Controllers/Web/SseIntelligenceWorkspaceController.php`
- `app/Controllers/Api/SseIntelApiController.php`
- `views/atak/sse/intelligence_workspace.php` + `views/atak/sse/workspace/*`
- `public/assets/js/sse-iw-graph.js`, `sse-command-palette.js`, `sse-intelligence-workspace.js`

## Hors scope (lots suivants)

- Contradictions moteur (slot UI présent, vide)
- Heatmap / replay BFT
- Héritage ACE réactivé ciblé
- PIR / taskings / diffusion

## Vérification

1. Migrations (curseur analyste)
2. Ouvrir `/atak/sse/workspace` — onglets Inbox / Chronologie / Graphe / Recherche
3. Cliquer un dossier → chemise `?case=`
4. Valider/rejeter une suggestion depuis l’inbox
5. Ctrl+K → recherche + navigation
6. Portail legacy `/operations`, `/dossiers/{id}` intact
