# Crédits — fonctionnalités portées (audit Iceman / Athena)

Ce dépôt intègre des **inspirations** et ports partiels issus de mods tiers.
Le cœur Overwatch reste sans dépendance BCE / WaveRelay (`COMSPECExtension`).
L’addon optionnel `atak_athena` ajoute une app cTab et requiert cTab + BCE lorsqu’il est chargé.

## Athena Remastered (`mod/Sources/@Athena Remastered/`)

- Handshake backend avant sync lourde → `fn_waitAthenaReady.sqf`, `fn_startSyncLoops.sqf`
- Réglages d’affichage camps (EAST / GUER / CIV) → `fn_sendFactionSettings.sqf`, CBA + params mission `ATH_show*`

## ATAK Enhancements by Iceman (`mod/Sources/@ATAK Enhancements by Iceman/`)

- Viewshed / heatmap (`ATAK_Elevation`) → `public/assets/js/tacmap-terrain-tools.js`
- Itinéraire + ETA (`ATAK_Route`) → `public/assets/js/tacmap-route-tools.js` (waypoints manuels ; pas de graphe `nearRoads` Arma côté web)
- Taxonomie alertes (`ATAK_Alerts` : TIC / CLEAR / FRAGO / SALUTE / Eagle Down)
  → `fn_sendTacticalAlert.sqf`, `app/Support/TacticalAlertParser.php`, panneau Tacmap
- Pont runtime cTab (app native Athena + dual-send) → addon optionnel
  `mod/@COMSPECOverwatch/addons/atak_athena/` (deps cTab/BCE ; pas de WaveRelay)
  - Alertes, BDA (`Iceman_ATAK_BDA`), photos (`bce_took_screenshot` → UploadReconImage),
    messages de groupe (`Iceman_ATAK_GroupMessage`)
  - Panneau : onglets Tout / BDA / Photos / Ordres + lecture stores Iceman locaux

## Athena Web Edition

- Patterns d’UI carte / calques consultés pour aligner Tacmap ; pas de copie directe du bundle React minifié.

## Licence / usage

Respecter les licences d’origine des sources sous `mod/Sources/`. Les ports COMSPEC
Les ports COMSPEC sont adaptés (UI FR métier). Le cœur `connect` n’exige pas cTab/BCE ;
l’addon optionnel `atak_athena` oui (app native dans ATAK Enhanced).
