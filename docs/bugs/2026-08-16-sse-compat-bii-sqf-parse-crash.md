# Crash SSE — parse SQF + forEach BII (2026-08-16)

## Contexte

Le mod `@COMSPEC_SSE` crashait au chargement / post-init dès que la compat BII Identifi et les addons SSE étaient présents (sessions du 16/08/2026).

Dernier RPT observé : `Arma3_x64_2026-08-16_17-12-11.rpt` — crash ~17:34:24, environ 1,7 s après « Menu SSE installé » (timing compatible avec `compat_bii` client `uiSleep 1.5` puis hooks). Les PBO Workshop datés ~17:03 contenaient encore le bug `generateTechint`.

## Symptôme

- Crash client / session Arma peu après le chargement des fonctions SSE / Menu SSE.
- Erreurs SQF au compile, notamment :
  - `fn_generateTechint.sqf` — `Error ] manquant` ligne ~18 autour de `format ["LOT-%1", 100 + (_hLot mod 900))],`
  - `fn_generateWeapon.sqf` — même famille (`getOrDefault` / `format` imbriqués)
  - `fn_toJsonApprox.sqf` — `Error } manquante` (échappement `"\""` invalide) — corrigé au 1er passage
  - `fn_uiFillDigital.sqf` — `getOrDefault` imbriqué dans les args de `format` — corrigé au 1er passage
- Warning historique : `Addon 'comspec_sse_evidence' requires addon 'cba_misc'`.
- Bug distinct : `forEach allUnits + vehicles + ...` / scan `ThingX` trop lourd au postInit serveur si BII est présent.

## Cause

1. **Parseur SQF** : un `]` ferme le `[` le plus proche. Donc :
   - `getOrDefault ["a", obj getOrDefault ["b", x]]` est invalide ;
   - un `format ["...", ...]` (ou `getOrDefault [`) **encore à l’intérieur** de `createHashMapFromArray [...]` casse aussi le matching — même après extraction des hash (`_hLot`, etc.) si le `format [` reste dans le littéral array.
2. **Échappement JSON** : en SQF, `\` n’est pas un escape. `"\""` laisse une guillemet orpheline → `} manquante`.
3. **forEach / scan BII** : priorité SQF + `allMissionObjects "ThingX"` au démarrage (missions S.O.A.R lourdes).
4. **cba_misc** : plus de PBO `cba_misc` dans CBA 3.19 — `CBA_MiscItem` vit dans `cba_common`.
5. Tiret long Unicode `—` dans des strings SQF peut corrompre le packing AddonBuilder.

## Correctif

### 1er passage (partiel)

- Extraire des variables intermédiaires à la place des `getOrDefault` / arrays imbriqués (generator, intel, ui, digital, network, compat_bii…).
- Réécrire l’échappement JSON avec `toString [92]`.
- `requiredAddons` : `cba_misc` → `cba_common`.

### 2e passage (2026-08-16 soir) — format-dans-array

- **`fn_generateTechint.sqf`** : précalculer `_uid`, `_lot`, `_serial`, `_category`, etc. **avant** `createHashMapFromArray` ; littéral final = variables / littéraux simples uniquement ; tiret ASCII `-`.
- Même règle appliquée aux générateurs / media : `fn_generateWeapon`, `fn_generateData`, `fn_generateVehicle`, `fn_generateBuilding`, `fn_generateDocument`, `fn_generateComputer`, `fn_generateRadio`, `fn_generatePhone`, `fn_generatePerson`, `fn_generateOpticalMedia`.
- **compat_bii** :
  - Client : `try/catch` autour register + hooks, log sans planter.
  - Serveur : plus de scan `allMissionObjects "ThingX"` ; import différé limité à `allUnits` avec variables BII présentes ; preuves via hooks seulement.
  - `fn_biiInstallHooks` : garde anti-réentrée `ensureGenerated` conservée ; wrap assignable via `try/catch` (skip si compileFinal / non assignable).

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/intel/functions/fn_generateTechint.sqf`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_generateOpticalMedia.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateWeapon.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateDocument.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateBuilding.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateVehicle.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateData.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateComputer.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generateRadio.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generatePhone.sqf`
- `mod/@COMSPEC_SSE/addons/generator/functions/fn_generatePerson.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/XEH_postInitClient.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/XEH_postInitServer.sqf`
- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiInstallHooks.sqf`
- (+ correctifs 1er passage : network/ui/digital/evidence/main — voir historique)
- Rebuild + déploiement Workshop des `comspec_sse_*.pbo`

## Vérification

- Rebuild via `mod/@COMSPEC_SSE/build_pbo.bat` → **Build OK** (14 PBO) ; `comspec_sse_intel.pbo` local ~17:39:38.
- Copie locale `mod/@COMSPEC_SSE/addons/` : OK (faite par le build).
- Copie Workshop `F:\SteamLibrary\...\@COMSPEC_SSE\addons\` : **bloquée** tant qu’`arma3_x64` verrouille les PBO (encore datés ~17:03). Relancer la copie après fermeture d’Arma.
- À retester in-game (après déploiement Workshop) : chargement mission SSE + CBA + BII Identifi :
  - RPT **sans** `generateTechint` / `] manquant`
  - pas de crash ~1–2 s après « Menu SSE installé »

## Statut

Corrigé (2e correctif format-dans-array + durcissement BII — en attente validation in-game).
