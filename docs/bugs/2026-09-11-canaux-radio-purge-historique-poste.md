# Canaux radio — purge historique poste

## Contexte

Chantier canaux radio + carte (Wave / itinéraire / viewshed). Exigence ajoutée : le commandement doit pouvoir supprimer totalement l’historique radio, en plus du nettoyage d’affichage local.

## Symptôme (attendu avant correctif)

« Vider mon affichage » masquait seulement le fil localement ; les messages restaient en base et réapparaissaient pour les autres (jeu / poste).

## Cause

Aucun endpoint ne supprimait `atak_chat_messages` ; l’UI n’avait qu’une action locale.

## Correctif

- Migration `atak_chat_channels` + `channel_key` sur les messages
- API `GET/POST /api/chat/channels`, filtre `channel` sur le journal, `POST /api/chat/purge` réservé session web TOC (refus clé jeu)
- UI journal : onglets canaux, « Effacer mon affichage » vs « Effacer l’historique » (double confirmation `EFFACER_CANAL`)
- Tablette jeu : sélecteur / création de canal, envoi avec `channel_key`
- Extension : `SendChat` + `CreateChatChannel` / `GetChatChannels` / `PublishViewshed`
- Carte : pastilles Wave, ETA/distance itinéraire, calques viewshed

## Fichiers touchés

- `app/Support/AtakChatChannel.php`
- `bootstrap/atak_chat_channels_migration.php`, `bootstrap/atak_viewshed_overlays_migration.php`
- `app/Repositories/AtakDataRepository.php`
- `app/Controllers/Api/AtakApiController.php`, `routes/web.php`
- `public/assets/js/atak-chat.js`, `atak-viewshed.js`, `atak-units.js`
- `views/atak.php`, `public/assets/css/atak.css`
- Mod Overwatch (tablette, SendChat, viewshed) + Extension C#

## Vérification

- Syntaxe PHP OK sur contrôleur / dépôt / support
- JS chat sans erreur de syntaxe (purge sans doublon)
- UPDATE #496 dans `DevDispatchCatalog`

## Statut

corrigé
