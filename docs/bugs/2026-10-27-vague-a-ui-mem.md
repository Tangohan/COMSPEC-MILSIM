# Vague A — hygiène carte (UI) et files musique / Bluetooth

## Contexte

Suite à la réconciliation A4–A7 : vague A (pas de LOT-NET). Tickets UI-002/004/005/007/008 et MEM-C02.

## Symptôme

Sessions longues sur la carte : téléchargements de dalles après panoramique, dictionnaire `kept` et anneaux de ping retenus, centre de liaison qui recharge toutes les tuiles pour un compteur, blobs de diapositives non révoqués. Files musique / Bluetooth sans plafond.

## Cause

Voir `reports/engineering/04-webview-ui.md` et `02-native-dll.md` (DLL-009/010 résiduels).

## Correctif

- `map-tiles.js` : arrêt du failover à `_removeTile`, `inflight` à la fin du fetch, `kept` plafonné + `clearKept`.
- `map-store.js` : `count()`, trim tuiles sur `at`.
- `map-bus.js` : résumé via `count("tiles")`.
- `live-map.js` : splice des pings ; `clearKept` au destroy.
- `slide.html` : revoke du blob avant chaque image.
- `MusicPlayer.cs` : file 32 + vidage à l’arrêt.
- `BtTransfer.cs` : TTL 30 min, max 32 entrées.

## Fichiers touchés

- `mod/UptoDate/.../atak-v2/map-tiles.js`, `map-store.js`, `map-bus.js`, `live-map.js`
- `mod/COMSPEC_ATAK_Native/.../data/web/slide.html`
- `mod/COMSPEC_ATAK_Native/COMSPECATAKNativeExtension/MusicPlayer.cs`, `BtTransfer.cs`
- `reports/engineering/issues.json`, `09-final-summary.md`
- `app/Support/DevDispatchCatalog.php` (UPDATE #1187)

## Vérification

- `dotnet build` native : OK.
- Arma / mémoire carte : non mesurés cette session.

## Statut

Corrigé dans les sources. UI-006 et MapBus.ack restent ouverts / optionnels. Visible après republish pack + Overwatch connect (atak-v2).
