# Athena téléphone — journal entièrement noir

## Contexte

Application Athena sur le téléphone ATAK (onglets Journal, Alerter, Rapporter, Poste).

## Symptôme

Le cadre, le titre et les onglets s’affichent. Le journal est un grand rectangle noir. Le filtre ne montre que la flèche. Aucune ligne, aucun texte d’absence.

## Cause

1. La taille du texte de la liste et du détail était calée sur une grille trop petite (taille d’écran, pas taille du panneau). Le texte existait, illisible.
2. Après l’animation du tiroir, la liste était encore posée comme si le panneau faisait toute la largeur du téléphone : hors zone visible, ou trop bas.

## Correctif

Liste et détail utilisent une taille de caractère lisible. La mise en page se calcule sur la taille réelle du panneau (comme les comptes-rendus). Même vide, une phrase l’indique.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.62)
- `tests/Unit/AtakAthenaPanelLayoutTest.php`

## Vérification

Pack Athena 1.0.62, relancer Arma. Téléphone → Athena → Journal : filtre lisible, liste ou « Le journal est vide pour le moment ».

## Statut

Corrigé (rebuild du pack jeu requis)
