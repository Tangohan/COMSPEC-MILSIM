# ATAK Interface V2 (tablette web)

## Emplacement

`mod/UptoDate/Sources/comspec-overwatch-addons/connect/web/atak-v2/`

## Ouvrir en jeu

1. Rebuild `connect.pbo` puis relancer Arma.
2. Ouvrir la tablette Athena / Overwatch.
3. Dans **État** : bouton **Interface V2 (bêta)** — bascule et recharge.
4. Depuis V2 : **Tablette** (bandeau) ou **Plus → Interface tablette (V1)**.

Persistance : `profileNamespace` clé `COMSPEC_TabletUiVersion` (`v1` | `v2`).

```sqf
profileNamespace setVariable ["COMSPEC_TabletUiVersion", "v2"];
saveProfileNamespace;
```

## Aperçu navigateur

Ouvrir `atak-v2.html?preview=1` (tuiles publiques). Chat / ordres / marqueurs réels nécessitent Arma.

## Pont

- `COMSPEC_BOOT` : indicatif, statut, world, effectifs, chat, ordres
- `COMSPEC|…` : même protocole que `tablet.html`
- MapBus / MapStore : scène locale ; `marker.created` → `marker:place`
