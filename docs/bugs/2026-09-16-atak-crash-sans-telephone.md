# Arrêt brutal sans ouvrir le téléphone ATAK

## Contexte

Aperçu Eden Altis, liaison Athena déjà ouverte, opérateur sans téléphone
en poche et sans avoir ouvert l’écran ATAK. Athena 1.0.140 / liaison 2.0.43.

## Symptôme

Le jeu se ferme tout seul au bout d’environ une minute. Journal moteur :

`Error: can't resize AutoArray to negative size!`

Adresse `0CD02B58` dans `Arma3_x64.exe`, même famille que les plantages
carte / menu. Les journaux COMSPEC s’arrêtent proprement. La position
n’était pas envoyée (`pas de terminal`). L’écran carte du téléphone
n’a jamais été détecté.

## Cause

Le calage IceMan / BCE à ressort peut encore écrire une largeur nulle
ou négative, y compris sur le mini-overlay du téléphone (écran 3D),
même si l’opérateur n’a pas ouvert le grand écran et n’a pas l’objet.

Athena traitait ce mini-overlay comme un téléphone ouvert : cartouches,
calage du menu, alerte. Le ressort IceMan, lui, n’était pas borné :
un dépassement sous zéro ferme le moteur.

## Correctif

- Athena 1.0.141 : le téléphone n’est considéré ouvert que lorsque
  l’interface l’est vraiment. Mini-overlay ignoré tant qu’on ne l’ouvre pas.
- Le ressort IceMan / BCE refuse toute largeur ou hauteur nulle.
- Les accroches carte ne parcourent plus des milliers de cadres à la recherche
  d’une carte.

## Fichiers touchés

- `atak_athena/functions/fn_athena_phoneDisplay.sqf`
- `atak_athena/functions/fn_ATAK_Anim_Type.sqf`
- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/functions/fn_athena_installMapHud.sqf`
- `atak_athena/functions/fn_athena_installPhoneGeolocMap.sqf`
- `atak_athena/functions/fn_athena_installReachMap.sqf`
- `atak_athena/XEH_postInitClient.sqf`
- `atak_athena/config.cpp` (Athena 1.0.141)

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.141).
2. Entrer en aperçu **sans** téléphone en poche, **sans** ouvrir l’ATAK.
3. Rester au moins deux minutes, carte Arma ouverte ou fermée.
4. Le jeu reste ouvert. Ensuite seulement, ouvrir le téléphone : le jeu
   reste ouvert.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
