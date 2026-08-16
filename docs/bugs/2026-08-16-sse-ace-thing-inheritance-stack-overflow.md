# Crash STACK_OVERFLOW ACE — héritage incohérent Biometrics

## Contexte

Crash Arma du **16/08/2026** (`C00000FD STACK_OVERFLOW`) encore présent après le correctif « sans Thing / inherit=false ».

Preuve `.rpt` juste avant le crash :

```text
[ACE] (interact_menu) ERROR: Failed to add action - action (COMSPEC_SSE_Bio)
to parent ["ACE_MainActions","COMSPEC_SSE"] on object B_support_MG_F
… puis SeekOpen / FP / IR / Face / DNA / BioAll / Identify
… même série sur O_support_MG_F
C00000FD STACK_OVERFLOW
```

## Symptôme

PostInit ACE plante pendant l’enregistrement du sous-menu Biométrie.

## Cause

- `fn_initACE.sqf` avait mis `COMSPEC_SSE` en **inheritance=false** (diagnostic anti-Thing).
- `fn_initBiometricsACE.sqf` greffait encore `COMSPEC_SSE_Bio` et ses enfants en **inheritance=true**.
- Sur les classes concrètes (`B_support_MG_F`, …) le parent `COMSPEC_SSE` est absent → ACE échoue en boucle → stack overflow.
- Overwatch `sse_ace` greffait aussi `OpenAthena` en `true` sous le même parent, et pouvait créer un second parent de repli.

## Correctif

Stratégie unique : **même bool d’héritage pour tout l’arbre** (défaut `true`), jamais `Thing`.

- Interaction / Biometrics / Digital / Overwatch sse_ace : `_inherit = missionNamespace getVariable ["COMSPEC_DEBUG_ACE_INHERIT", true]`
- Overwatch : greffe Athena uniquement sous `COMSPEC_SSE` existant (pas de second parent)
- Logs POSTINIT BEGIN/END + breadcrumbs BIO REGISTER
- Flags isolation `COMSPEC_DEBUG_DISABLE_*`

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_initBiometricsACE.sqf`
- `mod/@COMSPEC_SSE/addons/interaction/functions/fn_initACE.sqf`
- `mod/@COMSPEC_SSE/addons/digital/functions/fn_initDigitalACE.sqf`
- XEH postInit : interaction, biometrics, digital, network, intel, compat_ace, compat_bii
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_initSseAce.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/XEH_postInitClient.sqf`

## PBO rebuild

`interaction`, `biometrics`, `digital`, `intel`, `network`, `compat_ace`, `compat_bii`, `sse_ace` — OK (16/08 ~18:30).

## Vérification

1. Relancer mission avec ACE.
2. `.rpt` : présence de `[SSE][POSTINIT][*] BEGIN/END` et `[BIO][REGISTER-END]`.
3. **Aucune** ligne `Failed to add action` pour `COMSPEC_SSE_Bio` / SeekOpen / FP / …
4. Isolation : `missionNamespace setVariable ["COMSPEC_DEBUG_DISABLE_BIOMETRICS", true];`

## Statut

Correctif appliqué + PBO rebuild. Validation in-game restante.
