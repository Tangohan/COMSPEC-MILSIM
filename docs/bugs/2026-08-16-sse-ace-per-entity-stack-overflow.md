# Crash STACK_OVERFLOW — menus ACE SSE en héritage class-wide

## Contexte

Crash du **16/08/2026 21:11:57** (`Arma3_x64_2026-08-16_21-01-38.rpt`) pendant une session Zeus avec le dialogue « Générer profil SSE » ouvert. Module S.O.A.R + ACE chargés.

## Symptôme

- `Exception code: C00000FD STACK_OVERFLOW` dans `tbb4malloc_bi_x64.dll`
- Juste avant : placement unité Zeus + logs ACE Medical `"Was unit a player?"`
- **Aucune** ligne `generateData` dans le RPT → le crash n’était pas forcément le clic GÉNÉRER
- Instrumentation debug SSE très verbeuse (TRACE ENTER/EXIT) + `comspec_sse_debug.pbo` chargé

## Cause

1. **Menus ACE enregistrés via `addActionToClass` sur `CAManBase` / véhicules** (interaction + biométrie + digital + Overwatch) : à chaque spawn / InitPost médical, ACE reconstruit un arbre d’actions énorme → saturation de pile.
2. **`comspec_sse_debug`** forçait le log RPT (`COMSPEC_DEBUG_TRACE` / `FORCE_RPT` à true par défaut) et était en `requiredAddons` de core/interaction → toujours actif.
3. Couches intel + publish dogtag encore trop proches de la pile de génération.

## Correctif

- Menus ACE **per-entité** (`addActionToObject`) via `fn_installEntityAceMenus` à l’activation SSE (`setData` / `makeSearchable` / event `comspec_sse_entityEnabled`).
- Plus d’`addActionToClass` massif sur `CAManBase` / LandVehicle / Air / Ship.
- Overwatch Athena greffe sur `comspec_sse_entityAceReady` (même modèle).
- `attachIntelLayers` + publish / dogtag **différés** après `generateData`.
- Debug : plus en requiredAddons ; instrumentation inactive sauf `COMSPEC_DEBUG_FORCE=true` ; retiré du build PBO par défaut ; **supprimer `comspec_sse_debug.pbo` du Workshop**.

## Fichiers touchés

- `interaction/fn_initACE.sqf`, `fn_installEntityAceMenus.sqf`
- `biometrics/fn_initBiometricsACE.sqf`, `digital/fn_initDigitalACE.sqf`
- `core/fn_setData.sqf`, `fn_makeSearchable.sqf`, `fn_ensureDebugApi.sqf`, `XEH_preInit.sqf`
- `generator/fn_generateData.sqf`
- `UptoDate/.../sse_ace/fn_initSseAce.sqf`
- `debug/XEH_preInit.sqf`, `fn_log.sqf`, `fn_enter.sqf`
- `build_pbo.bat` (debug optionnel)

## Vérification

1. Fermer Arma → copier les PBO rebuild vers `!Workshop\@COMSPEC_SSE\addons`
2. **Supprimer** `comspec_sse_debug.pbo` du Workshop
3. Rebuild / copier `sse_ace.pbo` Overwatch
4. Relancer : placer une unité Zeus → **pas** de crash
5. Module Générer profil → GÉNÉRER → menus SSE présents **seulement** sur la cible
6. RPT : pas de flood `[COMSPEC][DEBUG][TRACE][ENTER]`

## Correctif (suite 21:20)

- `generateCluster` **light** : plus d’appel à `generatePerson` (supprime la double génération)
- `generatePhone` : plus de `generatePerson` de secours lourd
- `setData` : pas d’install ACE pendant `comspec_sse_generating` ; install différée
- Menus ACE enfants **étalés** (`waitAndExecute`)
- PC DETAILED différé hors frame person+phone
- File Zeus / site : intervalle ~0.28 s

## Correctif (suite — duplication ACE menus)

**Symptôme** : menu SSE avec Biométrie / SEEK / Empreintes / Photo faciale / Exploitation numérique ×3–5.

**Cause** : `installEntityAceMenus` posait `aceBioInstalled` / `aceDigInstalled` seulement *après* le `waitAndExecute`. Chaque `entityEnabled` (setData + makeSearchable + pending bio/digital) replanifiait une install complète. `initACE` écrasait aussi le cache et effaçait bio/digital.

**Fix** :
- Verrous `aceBioQueued` / `aceDigQueued` / `aceReadyFired` posés **immédiatement**
- `initACE` fusionne le cache au lieu de le remplacer
- `makeSearchable` ne re-émet pas si menus déjà installés
- pending bio/digital appellent `installEntityAceMenus` directement

## Correctif (suite — erreur ACE `fnc_render` 0/4)

**Symptôme** : `Error 0 éléments fournis, 4 attendus` dans `ace/.../fnc_render.sqf` (select 9 → select 3) en ouvrant le terminal SSE.

**Cause** : menus self (`COMSPEC_SSE_SELF`, Journal, etc.) créés avec params ACE `[]` au lieu de `[showDisabled, enableInside, canCollapse, runOnHover, doNotCheckLOS]`.

**Fix** : réutiliser `_aceParams` (5 booléens) pour toutes les self-actions.

## Correctif (suite — spam setData + ACE 0/4)

**Spam journal** : `setData` INFO à chaque `setSection` / republish → tampon rempli en 1 s.
- Log INFO seulement si nouvel uid ou >5 s
- Plus de `entityEnabled` si menus ACE déjà installés

**ACE 0/4** : toujours le fix self-params ; si l’erreur reste, le PBO Workshop n’est **pas** à jour (Arma verrouille les fichiers) — fermer Arma + recopier `interaction` + `core`.

## Correctif (suite — duplication ACE menus encore)

**Symptôme (16/08 soir)** : sous SSE → Biométrie ×2, Exploitation numérique ×2, SEEK / Empreintes / Photo faciale / Capture ×2–3.

**Cause** : enfants encore posés par `addActionToObject` étalés ; courses setData / entityEnabled / pending bio-digital malgré les verrous `queued`.

**Fix définitif** :
- Racine SSE avec `insertChildren` : bio, digital et Athena lus dans le cache à chaque ouverture
- `installEntityAceMenus` n’ajoute **qu’une** racine (verrou `aceInstalling`)
- Bio / Digital : cache only, plus de re-install sur les unités
- Overwatch : plus de greffe `addActionToObject` si SSE terrain présent (Athena via insertChildren)

**Rebuild** : `interaction`, `biometrics`, `digital` (+ `sse_ace` Overwatch) → Workshop. Relancer mission / régénérer le profil sur l’unité déjà dupliquée.
