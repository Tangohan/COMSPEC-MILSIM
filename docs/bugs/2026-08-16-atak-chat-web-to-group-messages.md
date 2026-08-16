# Messages TOC web → Group Messages Arma (sens inverse cassé)

## Contexte

Les messages de groupe remontent bien jeu → Athena (badge MESSAGE DE GROUPE).
Les envois depuis le journal radio web n’apparaissaient pas dans l’UI Iceman
« Group Messages ».

## Cause

1. Le poll `fn_pollChatMessages.sqf` poussait le web vers l’inbox Athena uniquement,
   pas vers `Iceman_ATAK_Group_messages` (seule source de l’écran Group Messages).
2. Sur la branche `GROUPE|`, `_timeStr` était utilisé avant définition.
3. Le web envoyait un corps `[SQUAD]…` au lieu du format `GROUPE|…`.

## Correctif

- Poll : injection locale Iceman (sans événement CBA global, anti-boucle) + fix horodatage.
- `atak-chat.js` : envoi au format `GROUPE|groupId|indicatif|grille|texte`.

## Fichiers

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf`
- `public/assets/js/atak-chat.js`

## Vérification

1. Rebuild / redéployer le PBO `connect` Overwatch.
2. Hard-refresh Athena (JS).
3. Envoyer depuis le journal web → ouvrir Group Messages en jeu sous ~6 s.
4. Envoyer depuis le jeu → toujours visible sur le web, sans doublon local.

## Statut

corrigé en sources — rebuild PBO `connect` requis
