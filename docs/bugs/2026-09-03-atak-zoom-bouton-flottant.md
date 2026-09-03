# Carte ATAK — bouton zoom flottant

## Contexte

Carte du téléphone ATAK Enhanced. Boutons zoom plus / moins ajoutés par le HUD COMSPEC.

## Symptôme

Un bouton « − » se décale en haut à droite, à cheval entre la carte et le tiroir d’applications.

## Cause

Les boutons zoom étaient calés sur le bord droit de la carte visible. Quand le tiroir s’ouvre ou se referme, ce bord bouge : le bouton suit et flotte.

## Correctif

Les boutons zoom sont masqués. L’identité passe dans une bande sous météo / heure (indicatif, rôle, grille, radio).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `tests/Unit/AtakIcemanHudAssetTest.php`

## Vérification

Ouvrir la carte : pas de bouton moins en haut à droite ; bandeau d’identité sous l’heure.

## Statut

corrigé (Athena 1.0.79)
