# Bug — fiche FRS/FRM illisible et hors du téléphone

## Contexte

1er septembre 2026. Dans RENS, l’écran Contexte / Valider et transmettre a un texte trop petit. La fenêtre de rédaction s’ouvre en overlay, pas dans l’écran du téléphone.

## Symptôme

- Le bouton Valider et transmettre et les libellés du contexte (date, lieu, thèmes) sont trop petits pour se lire sur le téléphone.
- La rédaction flotte hors du cadre ATAK. L’opérateur ne voit pas la fiche comme une application du téléphone.
- Pas de tuile FRS/FRM sur le bureau du téléphone.

## Cause

Le rédacteur était défini plein écran. Recadré, le volet contexte restait une colonne étroite avec des libellés en taille 0,40. Un repli ouvrait une fenêtre séparée au lieu de rester enfant du téléphone. Le panneau d’application ATAK (zone droite de l’écran) n’était pas utilisé comme cadre.

## Correctif

- Le rédacteur épouse le panneau d’application du téléphone.
- Contexte en pleine largeur de ce panneau, texte et bouton Valider et transmettre agrandis.
- Tuile FRS/FRM sur le bureau ; le tiroir affiche FRS/FRM. Même fenêtre de rédaction, à la taille de l’écran du téléphone.

## Fichiers touchés

- `connect/display_intel_note.hpp`
- `connect/functions/fn_intelNoteApplyGeometry.sqf`
- `connect/functions/fn_intelNoteShow.sqf`
- `connect/functions/fn_intelNoteRefresh.sqf`
- `atak_athena/functions/fn_athena_installDesktopShortcut.sqf`
- `atak_athena/config.cpp`
- `atak_athena/ui/note_page.hpp`
- `atak_athena/XEH_postInitClient.sqf`

## Vérification

Tests d’assets FRS. Rebuild du pack. En jeu : bureau → tuile FRS/FRM ; tiroir FRS/FRM ; rédaction dans l’écran du téléphone ; Valider et transmettre lisible.

## Statut

corrigé (pack à recharger, quitter Arma complètement)
