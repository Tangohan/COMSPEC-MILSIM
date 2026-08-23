# `athena_noteOnOpened` déjà défini

## Contexte

Addon ATAK Athena (`atak_athena`) après fusion des PR #184 / #185 (FRS / RENS) avec la branche feature. Le joueur relance Arma 3 après un rebuild des mods.

## Symptôme

Erreur au chargement du PBO :

```text
File z:\comspec_overwatch\addons\atak_athena\config.cpp, line 99
/CfgFunctions/comspec_overwatch_atak_athena/atak_athena.athena_noteOnOpened: Member already defined.
```

Le mod ne se charge pas.

## Cause

Dans `CfgFunctions`, les classes RENS étaient déclarées deux fois (une fois avant le bloc TASK, une fois après) :

- `class athena_noteOnOpened {}`
- `class athena_updateNote {}`
- `class athena_openNote {}`

Le même merge avait aussi inclus `ui\note_page.hpp` deux fois, ce qui aurait produit le même type d’erreur sur les contrôles UI (`COMSPEC_ATAK_Note`, etc.).

Les deux `class AtakNote` restantes sont volontaires : une dans `ATAK_APPs` (tablette) et une dans `RscTitles` (HUD). Ce n’est pas un doublon.

## Correctif

Garder une seule définition CfgFunctions, alignée sur les SQF existants :

- `fn_athena_noteOnOpened.sqf`
- `fn_athena_updateNote.sqf`
- `fn_athena_openNote.sqf`

Retirer le second `#include "ui\note_page.hpp"`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/@COMSPECOverwatch/addons/atak_athena.pbo` (rebuild)
- `mod/UptoDate/@COMSPECOverwatch/addons/comspec_overwatch_atak_athena.pbo` (alias, si présent)

## Vérification

- Plus aucun nom de classe répété dans `CfgFunctions` / `atak_athena`.
- Un seul include de `note_page.hpp`.
- Rebuild AddonBuilder de `atak_athena` avec le préfixe `z\comspec_overwatch\addons\atak_athena`.
- Copie du PBO dans le dossier addons local du repo.

## Statut

Corrigé
