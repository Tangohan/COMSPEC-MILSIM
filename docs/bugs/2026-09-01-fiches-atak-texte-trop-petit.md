# 2026-09-01 — Texte trop petit des fiches ATAK en jeu

## Contexte

Capture du volet **Contexte de la fiche** (date 01/09/2026 15:34, thèmes TERROR / INSURG / CRIME). L’opérateur signale que le texte est trop petit.

## Symptôme

Libellés (DATE, LIEU, TYPE, THÈMES), pastilles de thème et phrase du bas illisibles. Beaucoup de vide sous le formulaire, contrôles trop bas.

## Cause

Les intitulés sont du texte structuré à `size='0.40'` / `0.38`, hors du réglage « Texte et boutons » (qui ne touche que `sizeEx`). Les bascules de thème avaient une hauteur de 1,4 % de l’écran.

## Correctif

Titres, libellés, champs, thèmes, bandeau et messages agrandis. Phrase du bas plus contrastée. Réglage « Texte et boutons » : défaut un peu plus haut, plafond relevé.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_intel_note.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteRefresh.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteSubmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteApplyGeometry.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `tests/Unit/SseFieldNoteThemeTaxonomyAssetTest.php`

## Vérification

Assets : plus de `size='0.40'` sur le dialog, titre à `0.82`, libellés à `0.68`. Recette : pack reconstruit, relancer Arma, ouvrir une fiche → Contexte.

## Statut

Corrigé (pack jeu à reconstruire)
