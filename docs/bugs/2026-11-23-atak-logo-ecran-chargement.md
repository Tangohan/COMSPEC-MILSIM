# ATAK : logo du mod sur l'écran de chargement

## Contexte
Écrans de chargement de mission d'Arma (RscDisplayLoadMission, RscDisplayLoading).

## Symptôme
Le logo ATHENA du mod (loading_logo_ca.paa, ou la marque du coin) s'affiche pendant le chargement. Retour : le retirer.

## Cause
Branchement Extended_DisplayLoad de `fn_loadingLogo` dans `config.cpp` (4.14.0).

## Correctif
Les deux gestionnaires sont retirés de `config.cpp`. Le menu Échap et l'intro de l'écran de démarrage (désactivée par défaut) ne changent pas.

## Fichiers touchés
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp`
- `tests/Unit/AtakLoadingLogoAssetTest.php`

## Vérification
Test unitaire mis à jour. À vérifier en jeu : aucun logo du mod au chargement d'une mission.

## Statut
corrigé à vérifier en jeu
