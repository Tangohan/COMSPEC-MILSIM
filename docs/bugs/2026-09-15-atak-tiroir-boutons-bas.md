# Téléphone ATAK — boutons du bas sur la carte

## Contexte

Téléphone ouvert, menu d’applications déployé (chevron en haut à droite). Pack Overwatch 1.5.78 / Athena 1.0.127, après le calage anti-fermeture brutale.

## Symptôme

Les trois boutons du bas (photos, recherche, radio) restent sur la carte, à gauche du tiroir, au lieu de rester collés sous le menu d’applications.

## Cause

Le calage de l’écran déplace la carte et le tiroir, mais plus la barre du bas. Celle-ci reste à sa place d’origine (bas de tout l’écran). Le tiroir glisse à droite : photos et recherche recouvrent la carte, la radio reste à droite.

Cette barre n’était plus recalé volontairement, pour éviter une largeur nulle (même famille que la fermeture brutale). Le tiroir bougeait, pas les boutons.

## Correctif

La barre du bas suit à nouveau le tiroir (même bord gauche, bas du cadre). Largeur minimale conservée : jamais une taille nulle.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (Athena 1.0.128)
- `tests/Unit/AtakLayoutClampAssetTest.php`
- `tests/Unit/AtakIcemanHudAssetTest.php`
- `tests/Unit/AtakMapUiArchitectureAssetTest.php`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.128).
2. Ouvrir le téléphone, déployer le menu d’applications.
3. Photos, recherche et radio restent sous le tiroir, pas sur la carte.
4. Refermer le menu : la carte reprend toute la largeur, sans fermeture du jeu.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
