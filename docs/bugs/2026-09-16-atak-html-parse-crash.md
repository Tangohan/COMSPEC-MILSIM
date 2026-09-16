# Téléphone ATAK — rendu IceMan (RscStructuredText)

## Contexte

Athena 1.0.133–134 avait remplacé l’affichage IceMan par du texte simple
pour arrêter les fermetures brutales. Les pages et cartouches perdaient
couleurs, alignement et sauts de ligne.

## Symptôme

Téléphone « plat » : plus de cyan IceMan, plus de retours à la ligne
dans les cartouches, pages sans mise en forme.

## Cause

Le correctif anti-crash vidait les balises puis écrivait du texte brut
dans des cases `RscText`. IceMan, lui, utilise `RscStructuredText` +
`parseText` après échappement `& < >`, et seulement dans un cadre de
largeur / hauteur valides. Le plantage venait des cadres à largeur nulle
et des noms non échappés, pas du rendu IceMan lui-même.

## Correctif

- Helper unique : échappement IceMan, `ctrlSetStructuredText parseText`,
  ignoré si largeur < 0,02 ou hauteur < 0,008.
- Pages : toujours `RscStructuredText` (hpp IceMan / COMSPEC).
- Cartouches carte, barre de liaison, alerte : recréés en
  `Iceman_ReportsDetailText` ou `RscStructuredText`.
- Identité Indicatif / Nom / Rôle : cases natives 2620–22, `ctrlSetText`.

Athena 1.0.135.

## Fichiers touchés

- `connect/functions/fn_setPlainText.sqf`
- `atak_athena/functions/fn_athena_updateMapHud.sqf`
- `atak_athena/functions/fn_athena_updateLinkStrip.sqf`
- `atak_athena/functions/fn_athena_paintFullscreenAlert.sqf`
- `atak_athena/ui/atak_theme.hpp`
- `atak_athena/config.cpp`

## Vérification

1. Quitter Arma complètement, recharger le pack (Athena 1.0.135).
2. Ouvrir le téléphone : couleurs et alignement IceMan sur messagerie / connexion.
3. Carte : cartouches grille / identité lisibles, identité native en bas à droite.
4. Ouvrir / fermer le menu plusieurs fois : le jeu reste ouvert.

## Statut

Corrigé côté sources (à valider in-game après relance Arma).
