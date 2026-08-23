# Balises GPS ATAK invisibles

## Contexte

Module Zeus « Balise GPS véhicule » + carte ATAK web. Overwatch 1.4.51.

## Symptôme

La balise ne s’active pas, ou le véhicule n’apparaît pas sur la carte / les effectifs.

## Cause

1. Même erreur Zeus `isNull` (sélection ZEN) : le module ne pose pas le drapeau.
2. Le client relais ne rescannait les véhicules qu’une fois par minute : en multijoueur, délai trop long.
3. Le suivi allait surtout vers la table véhicules ; sans calque véhicules, rien sur la carte effectifs.

## Correctif

- Sélection Zeus aplatie (voir note `zeus-isnull-selection-atak`).
- Rescan à chaque cycle de 8 s.
- Remontée aussi comme contact carte (`gps_beacon`, indicatif `GPS-…`), sans Steam du relais.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initGpsBeacons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportGpsBeacon.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Contrôle du code. Retest Zeus : activer une balise, le contact GPS doit rester à côté du joueur sans le faire disparaître.

## Statut

Corrigé (sources) — rebuild PBO + DLL si Extension.cs est recompilée
