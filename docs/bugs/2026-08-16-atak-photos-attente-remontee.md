# Photos Athena — « En attente de remontée » bloqué

## Contexte

Journal Athena : clichés (ex. `2026_07_22_*.jpg`) en **En attente de remontée**,
bouton **Renvoyer photo** sans effet sur le web.

## Cause

1. Fichiers Photo Library morts → `PhotoDead` (profil) après `file_not_found`.
2. Le panneau ne lisait pas `PhotoDead` → faux « en attente ».
3. **Renvoyer** appelait la DLL hors bridge : pas de `PhotoPending`, pas de purge Dead.
4. Match Failed/Pending sur chemin absolu seulement ≠ basename du callback.

## Correctif

- Panneau : Dead/Failed (path **ou** basename) → échec clair « capturez à nouveau ».
- Renvoyer : purge Dead/Failed/Seen puis bridge avec `_force`.
- Bridge : param `_force` pour retenter un renvoi manuel.

## Fichiers

- `atak_athena/functions/fn_athena_updatePanel.sqf`
- `atak_athena/functions/fn_athena_sendPhoto.sqf`
- `atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`

## Vérification

1. Rebuild PBO `atak_athena`.
2. Ancien JPG mort : détail rouge « fichier introuvable », pas « en attente ».
3. Nouvelle capture récente : Envoi… → reçue sur ATAK web + onglet Photos.
4. Renvoyer sur mort : message d’échec clair (ne ressuscite pas le fichier).

## Statut

corrigé en sources — rebuild PBO `atak_athena` requis
