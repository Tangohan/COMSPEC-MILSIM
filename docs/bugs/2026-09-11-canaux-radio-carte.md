# Canaux radio + purge poste + calques carte

## Contexte

11 septembre 2026. Livraison canaux radio partagés, nettoyage local, purge commandement, Wave / itinéraire / viewshed sur la carte.

## Comportement

- Canaux système + custom (créables par tout opérateur lié ou le poste).
- « Effacer mon affichage » = local.
- « Effacer l’historique » = purge serveur, session poste uniquement (`POST /api/chat/purge`).
- Carte : pastilles Wave, ETA itinéraire, calques viewshed jeu→poste.

## Fichiers principaux

- `bootstrap/atak_chat_channels_migration.php`, `app/Support/AtakChatChannel.php`
- API chat channels / purge / viewshed
- `public/assets/js/atak-chat.js`, `atak-viewshed.js`, `atak-units.js`
- Overwatch 1.5.34 + liaison 2.0.26

## Vérification

- Build Overwatch OK (1.5.34 · liaison 2.0.26)
- Projection viewshed via `ATAKMap.latLngFromWorld`
- UI tablette : sélecteur / Nouveau / Vider mon affichage → `chat:clear`
- Pastilles Wave + filtre Wave ; ETA / Point atteint ; calque viewshed

## Statut

livré
