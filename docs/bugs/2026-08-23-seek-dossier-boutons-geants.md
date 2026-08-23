# SEEK — page Dossier illisible (boutons géants, champ minuscule)

## Contexte

L’écran LCD du terminal SEEK (page Dossier) affiche le code, la signature et la transmission.

## Symptôme

- **Transmettre** / **Annuler** occupent une grande partie de l’écran, texte débordant.
- Le champ du code dossier est trop petit par rapport aux boutons.
- « Signé … » flotte sans cadre.
- La barre du haut est illisible (`0 éch.` se lit comme « 24h »).
- La flèche **<<** est minuscule et déconnectée des actions.

## Cause

Les boutons Overwatch héritent d’une taille de texte prévue pour un dialogue plein écran. Sur le petit verre du SEEK, le texte déborde. Les actions étaient calées trop haut (`ROW(4)`), loin de la barre de navigation.

## Correctif

- Taille de texte LCD dédiée.
- Champ code plus haut ; signature dans un encadré.
- **Transmettre** (plus large) et **Annuler** en pied d’écran, juste au-dessus de **<<** / **>>**.
- Barre d’état : libellés plus grands, « Sans dossier », plus de « 0 éch. ».

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseTerminalPage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonRefreshPanels.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogSubmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

Rebuild `connect.pbo`. Ouvrir le SEEK → tuile Dossier : champ lisible, statut encadré, boutons d’action en bas, barre d’état lisible.

## Statut

corrigé
