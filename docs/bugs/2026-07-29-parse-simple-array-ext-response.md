# parseSimpleArray sur réponse extension

## Contexte

Erreur Arma au parsing des retours extension ATAK.

## Symptôme

```
Error parseSimpleArray: Format SimpleArray
fn_parseAtakExtResponse.sqf ligne 13
```

Souvent après évolution des journaux / chemins Windows dans les réponses.

## Cause

1. Toute chaîne commençant par `[` (ex. ligne de journal `[INFO]…`) déclenchait `parseSimpleArray` sans validation.
2. `FormatAtakExtArray` utilisait l’échappement JSON (`\"`, `\\`) incompatible avec `parseSimpleArray` Arma (chemins `C:\Users\…`, guillemets).

## Correctif

- SQF : parser uniquement si le texte commence par `["`, avec `try/catch` + repli format `OK|detail`.
- DLL : `EscapeForSimpleArray` (guillemet doublé) à la place de `EscapeJson` dans `FormatAtakExtArray`.

## Fichiers touchés

- `mod/.../connect/functions/fn_parseAtakExtResponse.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

1. Rebuild DLL + PBO connect.
2. Lancer Arma, ouvrir menu ATAK / signalement / sync — plus d’erreur parseSimpleArray en jeu.

## Statut

Corrigé
