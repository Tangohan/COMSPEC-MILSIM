# Crédits — fonctionnalités portées (audit Iceman / Athena)

Ce dépôt intègre des **inspirations** et ports partiels issus de mods tiers.
Aucun code BCE / WaveRelay n’est requis. COMSPECExtension reste le bridge natif.

## Athena Remastered (`mod/Sources/@Athena Remastered/`)

- Handshake backend avant sync lourde → `fn_waitAthenaReady.sqf`, `fn_startSyncLoops.sqf`
- Réglages d’affichage camps (EAST / GUER / CIV) → `fn_sendFactionSettings.sqf`, CBA + params mission `ATH_show*`

## ATAK Enhancements by Iceman (`mod/Sources/@ATAK Enhancements by Iceman/`)

- Viewshed / heatmap (`ATAK_Elevation`) → `public/assets/js/tacmap-terrain-tools.js`
- Itinéraire + ETA (`ATAK_Route`) → `public/assets/js/tacmap-route-tools.js` (waypoints manuels ; pas de graphe `nearRoads` Arma côté web)
- Taxonomie alertes (`ATAK_Alerts` : TIC / CLEAR / FRAGO / SALUTE / Eagle Down)
  → `fn_sendTacticalAlert.sqf`, `app/Support/TacticalAlertParser.php`, panneau Tacmap

## Athena Web Edition

- Patterns d’UI carte / calques consultés pour aligner Tacmap ; pas de copie directe du bundle React minifié.

## Licence / usage

Respecter les licences d’origine des sources sous `mod/Sources/`. Les ports COMSPEC
sont adaptés (UI FR métier, pas de dépendance cTab/BCE) et documentés dans les commentaires courts des fichiers concernés.
