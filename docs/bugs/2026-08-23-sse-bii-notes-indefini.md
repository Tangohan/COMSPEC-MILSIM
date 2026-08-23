# Zeus SSE — variable `_notes` indéfinie (export BII)

## Contexte

Passerelle `@COMSPEC_SSE` / BII Identifi, panneau Zeus SSE Control. L’export des variables d’entité vers BII s’exécute via `ensureGenerated` → `comspec_sse_fnc_biiExportEntityVars`.

## Symptôme

Erreur script overlay Arma :

```
Error Variable indéfinie dans une expression: _notes
File z\comspec_sse\addons\compat_bii\functions\fn_biiExportEntityVars.sqf, line 38
```

Extrait moteur : `if (|#|_notes isNotEqualTo "") then {` juste après l’export de `_leads`.

## Cause

Dans `fn_biiExportEntityVars.sqf`, `_notes` (et les champs intel voisins) étaient déclarés en `private` **à l’intérieur** du `then {}` de `if (_intel isEqualType createHashMap)`. En SQF, ce `private` est limité à ce bloc ; après les `if (...) then { ... };` one-line, le moteur évalue `_notes isNotEqualTo ""` hors de ce scope → variable indéfinie.

Second piège : `getSection` peut renvoyer `nil` (section absente). Un `if (_intel isEqualType createHashMap)` sans `isNil` plante de la même façon.

## Correctif

- Déclarer `_family`, `_associates`, `_leads`, `_notes`, `_bk` au scope de la fonction, initialisés à `""`.
- Tester `!isNil` avant `isEqualType createHashMap`.
- Neutraliser un `getOrDefault` qui renverrait `nil`, et n’appeler `isNotEqualTo` que sur une chaîne.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/compat_bii/functions/fn_biiExportEntityVars.sqf`
- Rebuild : `mod/@COMSPEC_SSE/addons/comspec_sse_compat_bii.pbo`

## Vérification

1. Rebuild `compat_bii` (`build_pbo.bat` ou AddonBuilder ciblé).
2. Fermer Arma, copier le PBO vers `!Workshop\@COMSPEC_SSE\addons` (le launcher charge souvent le Workshop).
3. Rouvrir une mission avec BII + Zeus SSE Control, examiner / générer une entité.
4. Plus d’overlay `_notes` indéfini.

## Statut

Corrigé (source) — déploiement PBO à confirmer in-game.
