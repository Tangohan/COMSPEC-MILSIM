# Fieldwatch RF hits — Lot 1

Inspiration [Fieldwatch](https://github.com/OffGridPete/Fieldwatch) (scan Wi‑Fi / BLE passif Android) adaptée au milsim COMSPEC : **émissions simulées en Arma**, remontée Athena, calque poste.

## Flux

```text
Zeus / Eden — Émetteur RF
  → objet mission (COMSPEC_RfEmitters)
  → app Fieldwatch (téléphone ATAK) scanRfNearby
  → DLL SendRfHit
  → POST /api/atak/rf-hits
  → table atak_rf_hits
  → Overwatch Beta (Veille RF) + onglet Identification ATAK
```

## Surfaces

| Surface | Détail |
|--------|--------|
| Migration | `bootstrap/atak_rf_hits_lot1_migration.php` |
| API | `POST/GET /api/atak/rf-hits` (`mode=markers` = dernier hit / émetteur) |
| DLL | `SendRfHit` (uid, label, band, signature, x, y, dBm, sensor [, mac]) |
| Zeus | COMSPEC Roleplay → Émetteur RF Fieldwatch |
| Eden | Module `COMSPEC_Module_RfEmitter` |
| Téléphone | App tiroir **Fieldwatch** |
| Web | Overwatch `#ow-rf-list` / `#ow-rf-layer` · ATAK `#atak-rf-list` |

## Bandes

`wifi` · `ble` · `tracker` · `camera` · `phone` · `unknown`

## Hors Lot 1

- Vrai scan radio téléphone Android
- Direction finding multi-capteurs / ellipse RF
- Catalogue de signatures extensible côté serveur
- Rebuild PBO + NativeAOT DLL (Windows `build_mod.bat`)

## Capacité

`CAP-RF-001` · ICD `ICD-RF-001`
