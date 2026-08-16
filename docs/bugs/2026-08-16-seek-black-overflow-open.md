# SEEK — bandeau noir hors appareil + ouverture intermittente

## Contexte

Terminal biométrique Overwatch (`COMSPEC_SsePerson_Dialog`, idd 9991).
Capture : fond noir semi-transparent qui dépasse le châssis ; ouverture ACE / menu
qui ne répond que « par moments ».

## Symptôme

1. Zone noire autour / au-dessus de l’appareil, hors du cadre LCD.
2. « Ouvrir la fiche Athena » / SEEK : parfois rien, sans message clair.

## Cause

1. **Chassis `RscText`** plein-cadre (`SEEK_X/Y/W/H`) avec alpha 0.55 derrière
   `seek_chassis.paa` : la texture a des bords **transparents** → le fond noir
   déborde de la silhouette de l’appareil.
2. **Écran LCD** un peu trop large / haut vs le verre gravé sur la PAA.
3. **Ouverture** : `fn_ssePersonDialogShow` sortait silencieusement si
   `COMSPEC_SsePerson_Display` était encore non-null (référence stale), et
   `createDisplay` sur le display 46 peut échouer sans bascule sur `createDialog`.

## Correctif

- Fond Chassis alpha `0` ; écran légèrement rentré dans le verre.
- Show : purge stale + message si déjà ouvert + fallback `createDialog`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ssePersonDialogShow.sqf`

## Vérification

1. Rebuild / recopier le PBO `connect` Overwatch (Workshop).
2. Ouvrir SEEK : plus de bandeau noir hors châssis ; tuiles dans le verre.
3. Fermer / rouvrir plusieurs fois (ACE + menu) : ouverture fiable ou message explicite.

## Statut

corrigé en sources — **rebuild PBO Overwatch requis**
