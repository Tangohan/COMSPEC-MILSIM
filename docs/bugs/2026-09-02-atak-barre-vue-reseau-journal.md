# Barre Vue sous Outils, Réseau / Journal illisibles

## Contexte

Poste ATAK, vue relief. Barre droite N / 2D / 3D / Zoom. Bande
Réseau / Journal en bas de carte.

## Symptôme

- La barre de vue passe sous la bande **Outils** et sous la carte.
- **Réseau** et **Journal** s’affichent collés, sans cadre, comme du
  texte brut.

## Cause

L’interface cartographique recouvrait toute la colonne carte, y compris
Outils, et se plaçait trop bas dans l’ordre d’affichage. Les libellés
Réseau / Journal n’avaient pas de présentation (blocs côte à côte sans
espacement).

## Correctif

- La couche d’interface commence sous Outils et passe au-dessus de la
  carte.
- Réseau et Journal : bande basse, libellé au-dessus de la valeur,
  séparés.

## Fichiers touchés

- `public/assets/css/atak-map-c2-live.css`
- `public/assets/css/atak-map-c2-v2.css`
- `public/assets/js/map/atak-c2-bridge.js`
- `tests/Unit/AtakC2UiCspAssetTest.php`

## Vérification

Tests d’assets + UPDATE 368. Recharger le poste (Ctrl+F5) : barre de
vue entière à droite sous Outils ; Réseau et Journal lisibles en bas.

## Statut

corrigé (recharger la page du poste)
