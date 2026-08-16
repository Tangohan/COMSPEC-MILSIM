# SEEK — coordonnées non remontées sur toutes les données

## Contexte

Transmission terminal SEEK (fiche personne + armes, équipement, médical, échantillons, photo).

## Symptôme

La grille / position terrain n’apparaissait pas de façon fiable sur l’événement intel ni sur les sous-éléments (armes, échantillons, photo). Les documents SSE générés n’avaient souvent de grille que sur la première pièce.

## Cause

1. SEEK prenait la position de l’opérateur, pas celle de la cible.
2. Les sous-objets JSON partaient sans `grid_reference` / `pos_*`.
3. L’API ne regardait que `capture_pos_*` pour l’événement intel alors que le terrain envoie surtout `pos_*`.
4. Générateur documents : grille uniquement sur le document d’index 0.

## Correctif

- SEEK : coords cible → fiche + armes / équipement / médical / signature / échantillons / requête d’identité ; alias `capture_pos_*` ; photo avec grille.
- Extension : champ formulaire `grid_reference` sur upload photo.
- API : normalisation / tamponnage des sous-données + `lat`/`lng` depuis `pos_*` ; résumé de position dans le journal de transmission.
- Générateur : grille du pack sur tous les documents.

## Fichiers touchés

- `mod/UptoDate/Sources/.../fn_ssePersonDialogSubmit.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Controllers/Api/SseApiController.php`
- `app/Repositories/SseIntelEventRepository.php`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateDocument.sqf`

## Vérification

1. Rebuild Overwatch (`connect.pbo`) + Extension si photo avec grille.
2. Rebuild `@COMSPEC_SSE` pour les documents.
3. Transmettre une fiche SEEK près d’une unité → journal transmission : grille + position ; armes / échantillons portent la même grille.

## Statut

Corrigé (rebuild client requis pour le terrain).
