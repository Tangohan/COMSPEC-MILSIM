# Overlay vert « TERMINAL SSE — TERRAIN » encore ouvert

## Contexte

Le terminal SEEK Overwatch (châssis `seek_chassis.paa`, idd 9991) est l’appareil de terrain. Le mod `@COMSPEC_SSE` ouvrait encore un overlay vert plein écran (`COMSPEC_SSE_TerminalDialog`, idd 93200) via ACE « Ouvrir terminal SSE ».

## Symptôme

Sur une personne au sol, l’action ACE affiche un panneau vert « TERMINAL SSE — TERRAIN » (Digital, SEEK II, Site, Graph, Preuves, Mission) au lieu de l’appareil SEEK.

## Cause

`comspec_sse_fnc_uiOpenTerminal` faisait `createDialog "COMSPEC_SSE_TerminalDialog"` sans passer par le SEEK PAA, déjà fourni par Overwatch (`sseOpenTerminal`).

## Correctif

Si Overwatch est chargé, le hub et SEEK II s’ouvrent dans le châssis SEEK. L’accueil a une tuile **TERRAIN** (digital, site, graphe, preuves, mission) sur l’écran LCD. Zeus reste un écran à part (outil MJ).

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/ui/functions/fn_uiOpenTerminal.sqf`
- `mod/@COMSPEC_SSE/addons/ui/functions/fn_uiOpenScreen.sqf`
- `mod/@COMSPEC_SSE/addons/ui/functions/fn_uiOpenSeekHost.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_openSeek.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseTerminalPage.sqf`

## Vérification

ACE sur une personne : l’appareil SEEK s’ouvre, pas le rectangle vert. Tuile TERRAIN : record + boutons DIG / SITE / GRAPH / PREV / MISS dans l’écran.

## Statut

Corrigé (rebuild `connect` + `comspec_sse_ui` / `comspec_sse_interaction` / `comspec_sse_biometrics` requis)
