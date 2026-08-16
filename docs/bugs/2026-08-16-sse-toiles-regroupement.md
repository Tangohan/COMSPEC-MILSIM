# Investigations SSE — registre amélioré et regroupement

**Date :** 2026-08-16  
**Statut :** corrigé / livré

## Contexte

La page `/atak/sse/toiles` listait les investigations (toiles) sans vue d’ensemble claire ni moyen de fusionner plusieurs pistes en une seule toile.

## Symptôme

- Registre peu informatif (pas de métriques, pas d’extrait d’objet, dossier lié peu visible).
- Impossible de regrouper plusieurs investigations dont les hypothèses convergent.

## Cause

Fonctionnalité absente côté service / routes ; UI limitée à une grille de cartes basique.

## Correctif

- Métriques + recherche / filtre d’état alignés sur les autres écrans Analyse.
- Cartes enrichies (extrait d’objet, dossier lié, libellé « Investigation »).
- Action **Regrouper** : sélection multi + intégration dans une toile existante **ou** création d’une toile regroupée.
- Fusion des nœuds / liens avec dédoublonnage par référence métier (`ref_type` + `ref_id`) ; sources archivées ; résumé consolidé.

## Fichiers touchés

- `app/Services/Sse/SseMeshService.php` — `mergeInto()`
- `app/Controllers/Web/SsePortalController.php` — `meshesIndex` enrichi, `meshMerge`
- `routes/web.php` — `POST /atak/sse/toiles/regrouper`
- `views/atak/sse/meshes.php`
- `views/atak/sse/mesh_form.php`
- `public/assets/css/sse_portal.css`

## Vérification

1. Ouvrir au moins deux investigations avec quelques entités / liens.
2. Sur le registre, cocher les sources, choisir une cible (ou créer une nouvelle), valider.
3. Contrôler la toile cible : entités ajoutées / réutilisées, liens présents, sources en état « Archivée ».
4. Recherche par intitulé / objet et filtre d’état restent opérationnels.

## Statut

Livré — à valider en environnement Athena.
