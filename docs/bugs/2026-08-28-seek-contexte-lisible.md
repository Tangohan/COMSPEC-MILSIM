# Bug — Terminal SEEK : page Contexte trop petite et trop sombre

## Contexte

Terminal biométrique SEEK, page **Contexte** (statut, circonstances,
affiliation, signes distinctifs).

## Symptôme

Les libellés et les zones de saisie tiennent en haut de la vitre, trop petits
pour se lire à bout de bras. Le bas de l’écran reste vide. Les champs se
fondent dans le fond sombre (filets cyan trop fins, texte gris).

## Cause

La grille interne du terminal utilisait des lignes trop basses. Statut et
circonstances étaient collés en demi-largeur. Les couleurs des champs étaient
trop proches du fond de la vitre.

## Correctif

Une ligne par champ, pleine largeur. Les zones et le texte sont agrandis. Les
signes distinctifs occupent le reste de la vitre. Fond, champs et libellés
passent à un contraste plus net (vert d’écran, texte quasi blanc).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseTerminalPage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonRefreshPanels.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogOnLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

Tests `SseSeekContexteLayoutAssetTest`. Pack Overwatch 1.4.92, relancer Arma
complètement, ouvrir le terminal SEEK → page Contexte.

## Statut

corrigé
