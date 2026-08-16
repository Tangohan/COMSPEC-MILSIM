# Transmissions terrain — contenu complet + version des mods

**Date :** 2026-08-16  
**Statut :** corrigé

## Contexte

Le journal `/atak/sse/transmissions` et la fiche TX ne montraient qu’un résumé
(`person_id` / canal), sans le contenu réellement envoyé ni la version du pack
Arma.

## Symptôme

Impossible de relire « tout ce qu’ATAK / le terminal a transmis », ni de savoir
quelle version Overwatch / SSE était chargée.

## Correctif

- Instantané des champs transmis + empreinte client dans le payload d’événement.
- Overwatch envoie `mod_name`, `mod_version` (`CfgPatches` `versionStr`, ex. `1.4.17`),
  `mod_cfg`, `sse_addon_version` / `sse_addon_cfg`.
- Affichage bureau : sections « Logiciel terrain » / « Données transmises »,
  libellé réaliste `COMSPEC Overwatch v1.4.17 · SSE 0.7.5`.
- Colonne Logiciel sur le journal.

## Fichiers touchés

- `app/Controllers/Api/SseApiController.php`
- `app/Services/Sse/SseIntelFoundationService.php`
- `app/Repositories/SseIntelEventRepository.php`
- `app/Controllers/Web/SsePortalController.php`
- `views/atak/sse/transmissions.php`
- `views/atak/sse/transmission_show.php`
- `mod/.../fn_ssePersonDialogSubmit.sqf`

## Vérification

1. Rebuild / redéployer Overwatch (`connect.pbo`).
2. Envoyer une fiche personne depuis Arma.
3. Journal : colonne Logiciel renseignée.
4. Fiche TX : blocs identité + logiciel avec version type Workshop / CfgPatches.

## Statut

Livré — anciennes TX restent sommaires ; les nouvelles sont complètes après rebuild mod.
