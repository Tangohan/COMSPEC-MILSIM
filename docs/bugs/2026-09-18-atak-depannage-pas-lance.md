# Dépannage liaison jamais lancé (journal 18/09)

**Date :** 2026-09-18  
**Statut :** corrigé

## Contexte

Outil de dépannage livré en Overwatch 1.5.80. L’opérateur ouvre Échap → COMSPEC Overwatch. Le journal de session `COMSPEC_2026-09-18_083748_395.log` est collé tel quel.

## Symptôme

Le pack 1.5.80 est bien chargé. L’entrée ACE « Dépannage liaison » est installée. Le panneau Gestion du mod s’ouvre. Aucune ligne `Dépannage liaison démarré`. Le bandeau 55 s n’apparaît pas.

La session du matin tourne sans téléphone ATAK. Vers 12:16 le poste est injoignable (manifeste de vol). Relance Eden à 12:20, même fichier journal. À 12:21 : ouverture du panneau, puis plus rien.

## Cause

1. Le bouton HTML était tout en bas du panneau, hors écran sans défilement.
2. Un clic lançait une seconde fenêtre par-dessus la pause : dans Arma, deux dialogues empilés peuvent fermer le jeu avant le premier journal d’étape.
3. Après une relance Eden sans quitter Arma, l’arbre ACE n’était pas réinstallé (version de menu déjà en mémoire).

## Correctif

- Bouton orange **Dépannage liaison** en haut à gauche du menu Échap.
- Même bouton en bas du panneau Gestion du mod, visible hors HTML.
- Lancement direct : fermeture des fenêtres, puis bandeau, sans seconde fenêtre.
- Arbre ACE forcé à se réinstaller.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateLaunch.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onInterruptLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pauseManagerJSDialog.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_pause_manager.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/web/pause_manager.html`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`

## Vérification

Journal attendu après clic : `Dépannage liaison demandé`, puis `Dépannage liaison démarré`. En jeu : Overwatch 1.5.81, bouton orange sur Échap.

## Statut

corrigé
