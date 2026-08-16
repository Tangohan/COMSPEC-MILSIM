# Pack S.O.A.R / MRH — erreurs config et lien avec le crash

Date : 2026-08-16  
Statut : diagnostiqué (MRH tiers, hors `@COMSPEC_SSE`)

## Contexte

Session crash `Arma3_x64_2026-08-16_20-04-01.rpt` avec pack **`@# S.O.A.R - FN`** (MRH embarqué) + SSE + Overwatch.

## Symptômes RPT (MRH)

### 1. Typo Eden `objectobject` (critique côté MRH Satellite)

```text
Error Variable indéfinie dans une expression: objectobject
Error in …/Land_Laptop_*/Attributes/MRH_isSatelitteConsole.condition
Error in …/MRH_BioScanner/Attributes/UseDefaultAceActions.condition
… (dizaines de répétitions, ~99× `objectobject` dans le RPT)
```

Preuve disque — PBO Workshop S.O.A.R :

`F:\SteamLibrary\…\@# S.O.A.R - FN\addons\MRHSatellite.pbo`

contient **littéralement** :

```text
condition = "objectobject";
```

(×10 dans le PBO, classes `MRH_isSatelitteConsole` / `MRH_isSatelitteScreen` — faute d’orthographe « Satelitte » incluse).

La source Git MRH « propre » (`MRHMilsimTools-master`) utilise `condition = "object"` pour le BioScanner. Le pack S.O.A.R **Satellite** est donc une build **corrompue / mal patchée**, pas le master upstream.

### 2. Configs MRH mal formées (bruit boot)

```text
MRHHaloGear\…\MRH_DetachAAD.statement: Missing ';'
MRHSatellite\DialogsHPP\… Missing ';'
MRHSoldierTab\… Missing ';'
```

### 3. Conflit ACE dangereux (MRHMilsimTools)

```text
Updating base class 'ACE_Actions'->'', by 'MRHMilsimTools\config.cpp/…
  cfgVehicles/Helicopter/ACE_Actions/
  cfgVehicles/Plane/ACE_Actions/
```

MRH **écrase** l’arbre `ACE_Actions` hélicoptère/avion → menus ACE instables, failures `Failed to add action` en cascade (même famille que le crash Bio SSE / ACM).

### 4. Autres

- Stringtable MRH en double (`STR_MRH_* listed twice`)
- XEH non supporté sur plusieurs objets MRH (`MRH_MapObject`, `MRH_117FRadioStation_Base`, …)
- PBOs listés `unknown` au fingerprint Workshop (repack S.O.A.R)

## Lien avec le STACK_OVERFLOW SSE ?

| Élément | Verdict |
|---------|---------|
| `objectobject` Eden | **Spam / charge Eden**, pas la pile native `tbb4malloc` à 20:13:14 |
| Wipe `ACE_Actions` MRH | **Aggrave** les `Failed to add action` ACE → peut contribuer au overflow ACE |
| Pic SSE `generateData` + dogtag | Cause SSE déjà traitée à part |
| Chaîne finale RPT | `generateCluster` SSE → immédiat `C00000FD` ; juste avant spam `cTabExtension` |

**Conclusion :** MRH (via S.O.A.R) n’est **pas** le `applyModel` HVT, mais c’est un **facteur aggravant majeur** (ACE cassé + spam config). Isoler MRH/S.O.A.R est obligatoire pour valider les correctifs SSE.

## Correctifs (côté utilisateur / pack)

1. **Test A** — lancer sans `@# S.O.A.R - FN` (ou sans les PBO `MRH*.pbo`) + SSE rebuild → si plus de crash, MRH/S.O.A.R confirmé co-responsable.
2. **Test B** — désactiver seulement `MRHSatellite.pbo` + `MRHMilsimTools.pbo` (wipe ACE_Actions).
3. **Remonter à S.O.A.R** : rebuild `MRHSatellite` avec  
   `condition = "1";` (ou `"objectSimulated"`) — **jamais** `"objectobject"`.
4. Ne pas attendre un fix dans `@COMSPEC_SSE` : ce n’est pas notre addon ; un patch compat COMSPEC ne corrigerait que les Attributes Satellite, pas le wipe ACE_Actions hélicoptère.

## Fichiers / preuves

- RPT : `Arma3_x64_2026-08-16_20-04-01.rpt` (20:06–20:09 MRH, 20:13 crash)
- PBO : `MRHSatellite.pbo` (typo), `MRHMilsimTools.pbo` (ACE_Actions)
- Probe repo : `mod/_tmp_mrh_probe/` (sources MRH, pas le pack S.O.A.R binaire)

## Statut

identifié — action : isolation S.O.A.R/MRH + signalement pack Satellite
