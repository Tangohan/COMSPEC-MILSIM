# Arma 3 — `CfgVehicles: Member already defined` (module SSE)

## Contexte

Chargement du mod `@COMSPECOverwatch` (addon `connect`) après ajout des modules Zeus/Eden SSE.

## Symptôme

Dialogue d’erreur Arma au démarrage :

> File z\comspec_overwatch\addons\connect\modules\module_sse.hpp, line 221: .CfgVehicles: Member already defined.

Le jeu refuse de charger la config de l’addon.

## Cause

`config.cpp` incluait successivement :

1. `modules/module_roleplay_zone.hpp` — ouvre `class CfgVehicles { ... };`
2. `modules/module_sse.hpp` — rouvre un second `class CfgVehicles { ... };`

En config Arma, une même classe racine ne peut pas être redéfinie ainsi dans le même addon → « Member already defined ». La ligne citée pointe vers la fin du second bloc, pas vers la vraie cause (le second `class CfgVehicles` en tête de fichier).

## Correctif

- Un seul `class CfgVehicles` dans `connect/config.cpp`, avec `class Module_F;` une fois.
- Les deux fichiers modules ne contiennent plus que les classes véhicules (sans wrapper `CfgVehicles`).
- Rebuild de `connect.pbo` via `mod/UptoDate/build_mod.bat` pour déployer le fix in-game.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_roleplay_zone.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_sse.hpp`

## Vérification

- [x] Rebuild `connect.pbo` via AddonBuilder — Build Successful
- [x] Deploy sync (SHA256) : repo `@COMSPECOverwatch` = `!Workshop` = `workshop\content\107410\3684656708` = `Arma 3\@COMSPECOverwatch` (2026-08-06)
- [ ] Relancer Arma avec `@COMSPECOverwatch` — plus d’erreur `CfgVehicles`
- [ ] Modules roleplay + SSE visibles dans Eden/Zeus

## Statut

corrigé — PBO reconstruit et déployé Workshop ; confirmation in-game à faire
