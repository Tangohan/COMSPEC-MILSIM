# Écran « État ATAK » — RscControlsGroup non défini

## Contexte

Ouverture du mod / chargement de l’addon `atak_athena` au démarrage Arma 3.

## Symptôme

```
status_page.hpp, line 50: /COMSPEC_ATAK_Status/controls.BodyViewport: Undefined base class 'RscControlsGroup'
```

Le jeu refuse de charger les extensions COMSPEC.

## Cause

`BodyViewport` hérite de `RscControlsGroup` (scroll du corps de page statut) mais `config.cpp` ne déclarait pas cette classe contrairement à `connect/ui_base.hpp`.

## Correctif

Ajout de `class RscControlsGroup;` dans les forward decls de `atak_athena/config.cpp`.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

Recompiler le mod (`build_mod.bat`) et relancer Arma 3 : plus d’erreur au chargement ; app « État ATAK » ouvrable avec scroll du détail liaison.

## Statut

Corrigé
