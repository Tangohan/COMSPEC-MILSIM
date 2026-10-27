# Écran TOC noir ; diapositives absentes via ACE

## Contexte

Retour opérateur : grand écran TOC (trépied) reste noir ; les diapositives du Briefing ne peuvent pas y être poussées depuis ACE. Demande : PIP du poste et diapositives sur n’importe quel écran du jeu via ACE.

## Symptôme

- La dalle du TOC reste noire alors que l’objet est désigné.
- ACE ne propose le TOC / PIP / diapositives que sur un objet déjà enregistré (module Zeus), pas sur une TV quelconque.
- Diffuser les diapositives et le réseau TOC sur le même moniteur n’est pas possible.

## Cause

1. L’index de texture TOC prenait souvent la sélection 0 (châssis) au lieu de la dalle nommée `screen` / `display` (même bug que les écrans de briefing, déjà corrigé côté `screenCast`).
2. Les actions ACE étaient liées uniquement aux objets déjà dans le registre TOC.
3. Les pipelines TOC et diapositives étaient séparés ; la texture TOC restait posée et bloquait la dalle.

## Correctif

- Remappage de la dalle (`_face`) à l’enregistrement et à l’affichage TOC.
- ACE « Écran COMSPEC » sur les classes d’écran du jeu : afficher le réseau TOC, le PIP du poste, diffuser / piloter / arrêter les diapositives.
- Pendant une diffusion, le TOC libère la dalle ; à l’arrêt, il la reprend.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocScreen.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_screenCast.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_aceMenu.sqf`

## Vérification

1. Approchez un grand écran (trépied / TV). ACE → Écran COMSPEC → Afficher le réseau TOC : la carte et le flux apparaissent sur la dalle.
2. Ouvrez le PC du poste, puis ACE → Passer en PIP du poste : l’écran suit la vue du PC.
3. Sur le téléphone, ouvrez Briefing / DIAPOSITIVES. ACE → Diffuser mes diapositives : la diapo remplace le TOC ; Suivante / Précédente / Arrêter fonctionnent. Après Arrêter, le TOC revient.

## Statut

Corrigé dans les sources. Visible après reconstruction du pack.
