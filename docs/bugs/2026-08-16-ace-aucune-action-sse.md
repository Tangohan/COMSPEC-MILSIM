# ACE — aucune action SSE dans le menu

## Contexte

Sur un corps / blessé, le menu ACE montre Inventaire, Menu médical, Traîner, Porter, mais **aucune** entrée SSE (Inspecter, Fouiller, Empreintes, Renseignement…).

## Symptôme

Radial ACE sur `CAManBase` sans nœud « SSE » / « Renseignement SSE ».

## Cause

Deux couches distinctes :

1. **Actions terrain** (Inspecter, Photographier, Fouiller, biométrie, digital…) → mod **`@COMSPEC_SSE`** (`comspec_sse_interaction`). Absentes si le mod n’est pas dans `-mod=`.
2. **Fiche Athena** → PBO Overwatch **`sse_ace`**. Ancienne condition trop stricte (KO / menotté / non armé) : un blessé encore armé = menu masqué. De plus, Overwatch enregistrait un parent `COMSPEC_SSE` qui pouvait coexister / confondre avec le menu terrain.

## Correctif

- `sse_ace` : si `@COMSPEC_SSE` est chargé → greffe « Ouvrir la fiche Athena » sous le menu SSE existant (pas de second parent restrictif).
- Sinon → parent autonome `COMSPEC_SSE_ATHENA`.
- Condition ACE assouplie : toute personne (hors soi, &lt; 4 m), matériel vérifié à l’ouverture.
- Rebuild `sse_ace.pbo` (1.4.15).

## Fichiers touchés

- `mod/UptoDate/Sources/.../sse_ace/functions/fn_initSseAce.sqf`
- `fn_sseCanExploit.sqf`, `fn_sseExploitTargetLabel.sqf`

## Vérification

1. Lancer avec `-mod=...;@COMSPEC_SSE;@COMSPECOverwatch`.
2. Console : `isClass (configFile >> "CfgPatches" >> "comspec_sse_interaction")` → true.
3. Sur un PNJ / joueur à &lt; 4 m : menu ACE → **SSE** (Inspecter, Fouiller…) + **Ouvrir la fiche Athena**.
4. Sans `@COMSPEC_SSE` : seulement **Renseignement SSE** → fiche Athena.

## Statut

corrigé (sources + PBO à recharger)
