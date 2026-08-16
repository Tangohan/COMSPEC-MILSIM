# Debug Instrumentation — COMSPEC SSE

Addon : `comspec_sse_debug` (`mod/@COMSPEC_SSE/addons/debug`)

## Format RPT

```text
[COMSPEC][LEVEL][MODULE][EVENT] message
```

## Helpers

| Fonction | Rôle |
|----------|------|
| `comspec_debug_fnc_log` | Logger unifié |
| `comspec_debug_fnc_enter` / `exit` | TRACE profondeur + durée |
| `comspec_debug_fnc_addACEActionToClass` | Wrapper ACE + registre doublons |
| `comspec_debug_fnc_registerEventHandler` | EH avec détection doublons |
| `comspec_debug_fnc_breadcrumb` | Dernier point avant crash |
| `comspec_debug_fnc_guardOnce` | Init unique |
| `comspec_debug_fnc_watchdog` | Alive +0.1…+5 s |
| `comspec_debug_fnc_snapshot` | Environnement PostInit |
| `comspec_debug_fnc_aceStats` | Compteurs ACE |
| `comspec_debug_fnc_isModuleEnabled` | Isolation |
| `comspec_debug_fnc_isSafeMode` | Safe mode |

## Isolation (CBA Settings → COMSPEC Debug)

- Enable SSE Core / ACE / Digital / Biometrics / Zeus / Markers / ATAK / Compat…
- Safe Mode : core + logger uniquement
- Block dangerous ACE inheritance (`Thing`, `All`, …)

## Rebuild

```bat
mod\@COMSPEC_SSE\build_pbo.bat
```

Inclut désormais le composant `debug` (avant `core`).
