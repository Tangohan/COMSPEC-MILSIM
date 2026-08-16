# SSE Intelligence Workspace — LOT 5 (Calques ATAK)

Date : 2026-08-16

## Objectif

Projeter le renseignement SSE sur la carte ATAK / Tacmap :

- calques (dossiers, priorités, ordres, photos, historique) ;
- tracés et tracés fantômes ;
- géolocalisation des exigences et ordres de collecte ;
- historique d’événements cartographiés.

Sans remplacer les pings mission live ni casser le portail legacy.

## Schéma

Migration : `bootstrap/atak_sse_map_layers_lot5_migration.php` (branchée dans `run-migrations.php`).

| Élément | Enrichissement |
|---------|----------------|
| `sse_intel_requirements` | `pos_x`, `pos_y`, `visible_on_atak` |
| `sse_intel_taskings` | `pos_x`, `pos_y`, `visible_on_atak` |
| `sse_intel_events` | `pos_x`, `pos_y` (historique carte) |
| `sse_atak_tracks` | tracés `live` / `ghost` / `history` (polyline JSON) |

## Service

`App\Services\Sse\SseAtakLayersService` :

- agrège dossiers (features + sites), PIR, taskings, photos terrain, tracks, historique ;
- payload rétro-compatible : `points` + `layers[]` + `counts` ;
- enregistrement de tracés via `saveTrack`.

## API

- `GET /api/atak/sse-case-overlay?mapId=` — overlay unifié LOT 5
- `POST /api/atak/sse-tracks` — créer / mettre à jour un tracé

## UI

### Carte ATAK (`/atak`)

Panneau Affichage → **Calques renseignement** :

- dossiers, priorités, ordres, photos SSE ;
- tracés / tracés fantômes / historique ;
- traces d’unités + traces retardées (pointillés).

Script : `public/assets/js/atak-sse-layers.js`.

### Tacmap / Overwatch

`renderSseCaseOverlay` consomme aussi `layers` + polylines (fantômes en tirets).

### Workspace cycle

Formulaires exigence / ordre : coordonnées terrain optionnelles + case « Afficher sur la carte tactique ».

## Vérification

1. Lancer les migrations (LOT 5).
2. Créer une priorité avec X/Y → cocher affichage carte.
3. Ouvrir `/atak` → activer les calques renseignement → voir le point.
4. Composer un ordre géolocalisé → calque « Ordres de collecte ».
5. Photo terrain avec position → calque photos.
6. `GET /api/atak/sse-case-overlay` renvoie `layers` + `counts`.
7. Tacmap : calque « Dossiers SSE » affiche aussi PIR/photos/tracés.

## Hors LOT 5 (reporté)

Éditeur de tracé interactif sur la carte, accusés de réception géolocalisés, replay AAR filtré par dossier SSE.
