# Ordres TASK : boutons disparus et textes illisibles sur le téléphone

## Contexte

Sur le téléphone ATAK en jeu, un ordre « Remis » n’affichait plus Accepter / Refuser. L’écran Athena (onglets, alertes) était illisible : polices trop petites, bas de page coupé.

## Symptôme

- Liste d’ordres remplie, zone de détail vide, bouton Actualiser minuscule, pas d’Accepter ni de Refuser.
- Dans Athena : « Tout / Photos / Ordres » et « Contact / Fin contact » illisibles, « Comptes-rendus » tronqué.

## Cause

1. Les boutons partaient masqués. Vider la liste déclenchait une sélection vide qui les recachait, y compris pour un ordre déjà remis.
2. Les textes structurés utilisaient un coefficient 0,58 sans taille de base ; les boutons avaient une police trop petite pour l’écran du téléphone. Le panneau débordait sous la barre Retour.

## Correctif

- Accepter / Refuser restent visibles pour un ordre remis ou à traiter ; la reconstruction de liste n’efface plus la sélection.
- Polices agrandies sur TASK et Athena ; zone de détail un peu plus courte pour garder les actions à l’écran.

## Fichiers touchés

- `mod/.../atak_athena/ui/task_page.hpp`
- `mod/.../atak_athena/ui/athena_page.hpp`
- `mod/.../atak_athena/ui/atak_theme.hpp`
- `mod/.../atak_athena/functions/fn_athena_updateTask.sqf`
- `mod/.../atak_athena/functions/fn_athena_taskSelect.sqf`
- `mod/.../atak_athena/functions/fn_athena_taskSyncButtons.sqf`

## Vérification

Tests `AtakTaskButtonsFontAssetTest`. Pack Athena 1.0.55, relancer Arma, ouvrir TASK sur un ordre remis : Accepter et Refuser présents et lisibles.

## Statut

corrigé (pack Overwatch 1.4.95)
