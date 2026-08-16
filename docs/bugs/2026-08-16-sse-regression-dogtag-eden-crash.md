# Régression crash SSE — wraps ACE dogtag + gen Eden InitPost

Date : 2026-08-16  
Statut : corrigé (régression SSE, pas MRH)

## Contexte

Le crash `C00000FD` apparaissait après des évolutions SSE (compat ACE plaque, Eden AUTO generate, menus ACE larges). « Ça ne le faisait pas avant » → bug **introduit dans SSE**.

## Causes SSE (régression)

1. **Wrap `ace_dogtags_fnc_getDogtagData` / `checkDogtag`**  
   Entré dans la pile ACE Medical pendant Init / Check alors que `generateData` écrivait la plaque → récursion / overflow.

2. **Eden `AUTO` → `generateData` / `applyModel` dans InitPost**  
   Pose d’unités `sse_enabled` → `generateCluster` immédiat (vu dans le RPT juste avant le crash).

3. **`canInspect` = true pour tout `CAManBase` / véhicule**  
   Menus SSE évalués partout → surface ACE énorme + lazy gen involontaire.

## Correctif

| Zone | Avant (bugué) | Après |
|------|---------------|--------|
| Dogtag | Wrap fonctions ACE | Sync one-way seulement + action SSE « Lire la plaque » |
| Eden AUTO | generateData à l’init | `makeSearchable` + `pendingModelId` / dataset |
| Premier examen | — | `ensureGenerated` applique modèle/dataset pending |
| canInspect | tous les hommes/véhicules | enabled / data / searchable uniquement |

## Fichiers

- `compat_ace/fn_aceDogtagInstallHooks.sqf`
- `eden/fn_edenApplyAttributes.sqf`
- `generator/fn_ensureGenerated.sqf`
- `interaction/fn_canInspect.sqf`
- `interaction/fn_initACE.sqf`

## Vérification

1. Rebuild PBO : `compat_ace`, `eden`, `generator`, `interaction` (+ deploy Workshop).
2. Mission avec unités SSE + modèle HVT → **pas** de generateCluster au spawn ; pas de crash.
3. ACE Inspect SSE → génération lazy OK ; plaque via sync ou action « Lire la plaque (SSE) ».
4. ACE Check Dog Tag natif : affiche le nom SSE si sync déjà fait après inspect.

## Statut

corrigé — rebuild requis
