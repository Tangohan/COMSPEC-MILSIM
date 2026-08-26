# Bug — `isNull` sur l’événement ACE `newControllableObject`

## Contexte

Sans le pack SSE terrain, Overwatch greffe le menu Athena via l’événement ACE `ace_interact_menu_newControllableObject`. Overlay script Arma à l’ouverture d’une interaction ACE.

## Symptôme

```
Error isnull: Type … attendu
File z\comspec_overwatch\addons\sse_ace\functions\fn_initSseAce.sqf, line 169
if (isNull _entity) exitWith {};
```

Le menu ACE se casse ; le script `_graft` s’arrête.

## Cause

ACE n’envoie **pas un objet** sur `newControllableObject` : le paramètre est un **nom de classe** (`"B_Soldier_F"`, etc.). `isNull` n’accepte pas une chaîne → erreur de type.

## Correctif

- Si le paramètre est une chaîne : installer une fois la racine Athena + l’action sur `CAManBase` (`addActionToClass`, héritage).
- Si c’est un objet : conserver la greffe per-entité, après un test `isEqualType objNull`.
- Ignorer tout autre type.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_initSseAce.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/config.cpp` (1.4.16)

## Vérification

1. Rebuild `sse_ace.pbo` (Overwatch 1.4.16).
2. Quitter Arma, recopier vers `!Workshop\@COMSPECOverwatch\addons`.
3. Mission **sans** `@COMSPEC_SSE` : ouvrir ACE sur une unité → plus d’erreur `isNull`, entrée « Renseignement SSE » / fiche Athena présente.
4. Mission **avec** SSE terrain : pas de double menu (la greffe fallback ne s’exécute pas).

## Statut

`corrigé à rebuild`
