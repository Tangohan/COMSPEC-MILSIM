# Carte ATAK — Map Tools IceMan et cartouche identité

## Contexte

Carte du téléphone ATAK Enhanced. Bouton natif « Toggle Map Tools » et cartouche Indicatif / Rôle / Groupe / Grille.

## Symptôme

- Le bouton des outils carte apparaît puis disparaît tout de suite.
- Un menu au clic droit (Poser un marqueur, Signaler, 9-Line…) recouvre la carte.
- Une colonne de boutons Mes / Gril / Iti / Zone / Couc / Sign encombre le bord droit.
- Le cartouche d’identité (indicatif, rôle, groupe, grille) n’apparaît pas ; un petit roster de groupe s’affiche à la place.

## Cause

1. Le HUD restylait toutes les demi-secondes le bouton natif des outils carte (fond et couleur), ce qui le faisait clignoter pendant que IceMan l’animait.
2. Une barre d’outils et un menu contextuel COMSPEC recouvraient la carte et le cartouche d’identité.
3. Le cartouche d’identité était masqué ou coincé sous ces bandeaux.

## Correctif

- Ne plus restyler ni masquer le bouton des outils carte.
- Détruire et masquer la barre d’outils et le menu au clic droit.
- Reposer le cartouche Indicatif / Rôle / Groupe / Grille en bas à gauche, au-dessus des outils carte.
- Ne plus retoucher le pied d’application IceMan (46600) depuis TASK.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_taskOnOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_mapUIUpdate.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_mapUIDestroy.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/ui/fn_mapContextMenu.sqf`
- `tests/Unit/AtakIcemanHudAssetTest.php`
- `tests/Unit/AtakMapUiArchitectureAssetTest.php`

## Vérification

Ouvrir la carte du téléphone : cartouche identité lisible, outils carte stables, pas de menu au clic droit, pas de boutons Mes/Gril.

## Statut

corrigé (Athena 1.0.78)
