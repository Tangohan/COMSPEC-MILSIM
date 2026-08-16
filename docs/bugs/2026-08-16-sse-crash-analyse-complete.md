# Analyse complète — crashes STACK_OVERFLOW `@COMSPEC_SSE`

Date : 2026-08-16  
Statut : causes cartographiées + correctifs anti-réentrée / Eden différé appliqués en sources

## Verdict

Le crash `C00000FD STACK_OVERFLOW` / `tbb4malloc` observé en session **n’est pas une seule cause**. Plusieurs chemins SSE ont déjà provoqué (ou aggravé) la pile ; le scénario le plus crédible **avec PBO à jour** est :

1. pose d’unités Eden `sse_enabled` → `generateData` **synchrone** (InitPost)
2. pendant ce temps, wrap ACE dogtag appelle `ensureGenerated` → **réentrée**
3. pic de pile + hooks ACE Medical externes (« Was unit a player? ») → overflow natif

L’erreur soft `applyModel: modèle introuvable (builtin_iq_2010_2020_hvt)` est un **symptôme corrélé**, pas la récursion elle-même.

## Inventaire des causes (docs bugs)

| # | Cause | Doc | Sources |
|---|--------|-----|---------|
| 1 | Héritage ACE Bio parent `COMSPEC_SSE` absent | `sse-ace-thing-inheritance-stack-overflow.md` | Corrigé (héritage unifié) |
| 2 | Override `compileFinal` BII | `sse-bii-stack-overflow-compilefinal.md` | Corrigé (poll) |
| 3 | `toJsonApprox` cycles | `sse-tojsonapprox-circular-stack-overflow.md` | Corrigé (depth 32) |
| 4 | Modèle HVT registre partiel | `sse-applymodel-hvt-stack-overflow.md` | Corrigé (fusion + fallback) |
| 5 | **Réentrée `generateData` / dogtag** | ce fichier | **Corrigé maintenant** |
| 6 | **Eden InitPost synchrone massif** | ce fichier | **Corrigé maintenant** (stagger) |
| 7 | Overwatch photo `AllDirectories` | `overwatch-stack-overflow-photo-upload.md` | Hors SSE (corrigé OW) |
| 8 | Parse SQF compat_bii | `sse-compat-bii-sqf-parse-crash.md` | Séparé |

## Chaîne critique (encore ouverte avant ce correctif)

```text
Extended_InitPost CAManBase (sse_enabled)
  → edenInitEntity (SYNC)
       → edenApplyAttributes
            → applyModel / generateData
                 → setVariable dogtag + aceDogtagSync
ACE Medical / dogtags (externe ou wrap)
  → getDogtagData WRAP
       → ensureGenerated
            → generateData   ← RÉENTRÉE
                 → …
C00000FD / tbb4malloc
```

## Correctifs appliqués (2026-08-16 soir + suite)

| Fichier | Changement |
|---------|------------|
| `generator/fn_ensureGenerated.sqf` | Exit si `comspec_sse_generating` |
| `generator/fn_generateData.sqf` | Flag generating ; **setData local** puis publish + dogtag différés |
| `generator/fn_applyModel.sqf` | Garde generating + abort si generateData échoue |
| `generator/fn_generateSite.sqf` | File CBA 0,12 s / entité |
| `generator/fn_queueEntityJobs.sqf` | **Nouveau** helper anti-burst |
| `generator/fn_loadModel.sqf` | 2e retry = rebuild forcé du registre |
| `intel/fn_attachIntelLayers.sqf` | setData local si generating ; event différé |
| `intel/fn_generateFromBrief.sqf` | File + plus de double attachIntelLayers |
| `zeus/fn_applyGenerateDialog.sqf` + moduleGenerate / openGenerate / saveModel | Batches via `queueEntityJobs` |
| `digital/fn_initDigitalACE.sqf` | Enfants ACE **étalés** (plus de 45 regs sync) |
| `compat_ace/*` | getDogtagData / sync / checkDogtag différés |
| `core/fn_setIdentity.sqf` | Dogtag sync différé |
| `interaction/fn_canInspect.sqf` | false pendant generating |
| `eden/fn_edenInitEntity.sqf` | Génération différée + stagger |
| `intel/fn_emitEvent.sqf` | Garde profondeur |

Lazy gen dogtag reste dans `checkDogtag` (action joueur), pas pendant Init.

## Rebuild PBO obligatoire

Arma fermé, puis rebuild / redeploy :

- `comspec_sse_generator` (incl. `queueEntityJobs`)
- `comspec_sse_eden`
- `comspec_sse_compat_ace`
- `comspec_sse_intel`
- `comspec_sse_core`
- `comspec_sse_interaction`
- `comspec_sse_digital`
- `comspec_sse_zeus`
- (+ si pas encore : `biometrics`, `compat_bii`)

Sans rebuild, le Workshop continue de crasher avec l’ancien code.

## Protocole de validation in-game

1. Mission Altis SP, SSE + ACE + Overwatch (même pack qu’avant le crash).
2. RPT boot : `registerBuiltinModels: N modèles` ; **aucune** `Failed to add action` Bio.
3. Poser 10+ unités `sse_enabled` + modèle HVT → plus d’ERROR « introuvable » ; pas de crash dans les 30 s.
4. Isolation si crash persiste :
   - `COMSPEC_DEBUG_DISABLE_BIOMETRICS = true`
   - `COMSPEC_DEBUG_DISABLE_COMPAT_ACE = true`
   - puis sans pack ACE tiers / S.O.A.R.

## Si ça crashe encore après rebuild

Alors la pile native ACE Medical externe reste plausible **en combinaison** avec SSE (charge Init). Isoler sans S.O.A.R. / ACE Medical custom. Ce n’est plus une récursion SQF COMSPEC identifiable dans le code actuel.
