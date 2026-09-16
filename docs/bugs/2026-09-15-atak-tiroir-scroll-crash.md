# Téléphone ATAK — menu d’applications cassé et arrêt brutal

## Contexte

Menu d’applications ouvert (chevron). Pack Athena 1.0.129 / 1.0.130 après les
calages anti-fermeture et pied de tiroir. Session 21:55, arrêt peu après.

## Symptôme

- Plus de défilement dans la liste d’applications.
- Plus de bouton retour.
- Photos / recherche / radio restent sur la carte, à gauche du menu.
- Le jeu se ferme (Arrêt anormal).

## Cause

Le calage de l’écran :

1. Recalculait la taille du menu à partir de la carte, puis additionnait carte
   et tiroir alors qu’ils se recouvrent.
2. Écrasait la **hauteur** du menu (le moteur d’animation applique les 4
   valeurs d’un coup). Le bandeau retour et la zone de défilement disparaissaient.
3. Envoyait encore une largeur quasi nulle, interpolée : même plantage
   qu’auparavant (`can't resize AutoArray to negative size!`).

## Correctif

- Taille du menu lue sur le fond du tiroir, plus sur la carte.
- Hauteur du menu intacte.
- Largeur jamais nulle ; plus d’animation à ressort.
- Pied de menu collé sous le tiroir.

Athena 1.0.131.

## Fichiers touchés

- `atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.131).
2. Ouvrir le téléphone, déployer le menu : défilement et retour présents.
3. Photos, recherche, radio sous le menu, pas sur la carte.
4. Ouvrir / fermer le menu plusieurs fois : le jeu reste ouvert.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
