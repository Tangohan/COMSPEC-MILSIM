# Atelier de préparation — modèles SSE (web)

Page portail : `/atak/sse/dev`  
Bibliothèque : `/atak/sse/dev/modeles`

## Rôle

Permet au commandement / concepteurs de mission de créer des **modèles dédiés** pour le module Arma `@COMSPEC_SSE` (profil, thème, listes narratives, options biométrie / téléphone / documents / ordinateur).

## Accès

- Consultation : session SSE active (code ou staff).
- Création / modification / archivage : droit de gestion dossier SSE (`atak.sse.case.manage`, grant ou admin).

## Catalogue d’ères

L’atelier propose des **modèles types** regroupés :

- **Irak 2010–2020** — caches d’armes, IED, HVT, courrier, financier, planque, civil bruit
- **Russie / Est 2020–2024** — recon, logistique, PC, drone, radio/EW, info ops, courrier, civil bruit
- **Générique** — points de départ classiques

Région web `RUSSIA` = « Russie / théâtre Est (2020+) ». En jeu, appliquer aussi les builtins `builtin_iq_*` / `builtin_ru_*`.

## Export vers Arma

Sur la fiche modèle :

1. **Script mission** (`.sqf`) — `createModel` + `saveModel`, à coller en init / console.
2. **Fichier d’échange** (`.json`) — structure `comspec_sse_model` + paires sérialisées pour import / archive.

## Schéma

Migration : `bootstrap/atak_sse_arma_models_migration.php` (table `sse_arma_models`, branchée dans `run-migrations.php` et `ensureSchema()` du dépôt).

## Fichiers clés

| Couche | Fichier |
|--------|---------|
| Contrôleur | `app/Controllers/Web/SseArmaModelsController.php` |
| Service | `app/Services/Sse/SseArmaModelService.php` |
| Dépôt | `app/Repositories/SseArmaModelRepository.php` |
| Vues | `views/atak/sse/dev/*` |
| Prompts packs | `docs/sse/prompts-packs-modeles-mission.md` |
