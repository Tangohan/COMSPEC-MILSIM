# SSE — barre de recherche globale inactive / vide

## Contexte

Barre du header Bureau SSE (« Rechercher identités, sites… »).

## Symptôme

Saisie sans résultats utiles, ou impression que la recherche ne fait rien.

## Cause

`SseWorkspaceService::globalSearch` chargeait les identités/sites via `listForContext(..., context_id = 1)` puis filtrait en PHP. Hors contexte 1, ou au-delà de la limite, les objets étaient invisibles. Documents absents de la recherche.

## Correctif

- `searchForTenant` SQL (LIKE) sur personnes et sites, tous contextes
- Recherche élargie : dossiers, DI, investigations, documents
- Endpoint suggestions + suggestions live dans le header
- Page résultats regroupée par type

## Fichiers touchés

- `app/Services/Sse/SseWorkspaceService.php`
- `app/Repositories/SsePersonRepository.php`
- `app/Repositories/SseSiteRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `routes/web.php`
- `views/atak/sse/_layout.php`, `search.php`
- `public/assets/js/sse-global-search.js`
- `public/assets/css/sse_workspace.css`

## Vérification

- [ ] Taper ≥ 2 caractères → suggestions
- [ ] Entrée → page résultats groupés
- [ ] Clic suggestion → fiche correspondante

## Statut

corrigé
