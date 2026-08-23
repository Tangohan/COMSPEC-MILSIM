# Bug — ACE `collectActiveActionTree` select 10 (menu interaction)

## Contexte

Ouverture du menu ACE (interaction / self) en jeu. Overlay script Arma.

## Symptôme

```
Error Erreur générique dans une expression
File z\ace\addons\interact_menu\functions\fnc_collectActiveActionTree.sqf, line 28
(_origActionData select 10)
```

Le menu ACE se casse (actions COMSPEC / SSE).

## Cause

ACE attend, pour chaque enfant `insertChildren`, un **triplet** `[action, enfants, cible]` dont `action` a **11 cases**. L’index 10 est la fonction modificatrice.

`createAction` renvoie `_this` (les arguments fournis). Sans le 11e argument, le tableau n’a que 6 ou 10 cases.

Si `insertChildren` renvoie un tableau `createAction` brut, ACE prend l’identifiant (chaîne) comme `_origActionData` puis fait `select 10` → plantage.

**Suite 23/08 (journal + overlay terminal ouvert / blessé) :**
- Les racines SSE (`COMSPEC_SSE` / `COMSPEC_SSE_OBJ`) étaient enregistrées **sans** index 10 — le wrap des enfants ne s’exécute jamais, ACE plante dès la collecte.
- Les sous-menus ATAK (rapports / POI / appui) ne faisaient **qu’un** `pushBack {}` : une action à 6 cases passait à 7, toujours trop courte. ACE collecte ces enfants dès que le menu self est ouvert (terminal ATAK / SEEK ouvert = condition vraie).

## Correctif

- `acePadAction` : complète jusqu’à 11 cases (types ACE), conserve un modificateur déjà présent.
- `aceWrapMenuChildren` : détecte triplet vs action brute, pad récursif.
- Racines SSE + self + bio/digital : 11e argument `{}` **et** pad avant `addActionToObject`.
- Self-actions Overwatch / Zeus / saisir ATAK : même pad.
- Sous-menus ATAK `insertChildren` : pad **complet** (plus un seul `pushBack`).

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_acePadAction.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_aceWrapMenuChildren.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_initACE.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_installEntityAceMenus.sqf`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_initBiometricsACE.sqf`
- `mod/@COMSPEC_SSE/addons/digital/functions/fn_initDigitalACE.sqf`
- `mod/@COMSPEC_SSE/addons/main/script_mod.hpp` (0.7.7)
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_acePadAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_aceAddSelfAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initATAKMenu.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenAtakPlayerActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.23)
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_initSseAce.sqf`

## Vérification

1. PBO rebuilds dans le dépôt :
   - `mod/@COMSPEC_SSE/addons/comspec_sse_interaction.pbo` (+ main / bio / digital)
   - `mod/UptoDate/@COMSPECOverwatch/addons/connect.pbo` (+ `sse_ace.pbo`)
2. **Quitter Arma**, recopier vers `!Workshop\@COMSPEC_SSE\addons` et `!Workshop\@COMSPECOverwatch\addons` (le launcher charge souvent le Workshop, pas le dossier local).
3. Relancer : ACE self (y compris blessé, terminal SSE ouvert) + ACE sur une unité SSE → plus d’erreur `select 10`.
4. Journal / RPT : SSE `0.7.7`, connect `v1.4.23`.

## Statut

`corrigé à rebuild`
