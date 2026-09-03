# Carte ATAK — Map Tools IceMan et cartouche identité

## Contexte

Carte du téléphone ATAK Enhanced. Bouton natif « Toggle Map Tools » (IDC 46600) et cartouche Indicatif / Rôle / Groupe / Grille.

## Symptôme

Le bouton des outils carte apparaissait comme un gros carré mal positionné. Le cartouche d’identité n’était plus visible.

## Cause

COMSPEC animait et restylait le bouton IceMan 46600 (largeur nulle, fond charbon). Le cartouche de remplacement était calé en bas à gauche, pile sur les outils carte. Les infos natives IceMan étaient masquées.

## Correctif

Ne plus toucher au bouton 46600. Cartouche identité en haut à gauche, sous la boussole. Cartouche curseur en bas à droite. Overlay informatif, clics carte libres.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installMapHud.sqf`
- `tests/Unit/AtakIcemanHudAssetTest.php`

## Vérification

Ouvrir la carte du téléphone : outils carte d’aspect natif, cartouche identité lisible, dix ouvertures sans doublon.

## Statut

corrigé
