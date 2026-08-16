# Arma 3 — `.RscText: Member already defined` (dialogues SSE)

## Contexte

Chargement du mod `@COMSPEC_SSE` après l’ajout du dialogue « Modèles » (atelier de modèles Zeus).

## Symptôme

Dialogue d’erreur Arma au démarrage, avant l’écran d’initialisation des extensions :

> File z\comspec_sse\addons\zeus\dialogs\modelDialog.hpp, line 1: .RscText: Member already defined.

La config de l’addon `zeus` ne se charge pas.

## Cause

`zeus/config.cpp` inclut deux fichiers de dialogue à la suite :

1. `dialogs/generateDialog.hpp` — déclare `class RscText;`, `class RscButton;`, etc.
2. `dialogs/modelDialog.hpp` — redéclare `class RscText;` en ligne 1.

Les déclarations anticipées de classes de base sont valables pour tout l’addon : la seconde déclaration dans le même `config.cpp` est un doublon, d’où « Member already defined ». La ligne 1 citée est bien la vraie cause ici.

Le même montage existait dans l’addon `ui` : `resultDialog.hpp` puis `screens.hpp` → `common.hpp`, tous deux déclarant `RscText` / `RscButton` / `RscStructuredText`. L’erreur ne s’était pas encore manifestée parce que le chargement s’arrêtait avant.

## Correctif

- Les déclarations anticipées sont regroupées une seule fois dans chaque `config.cpp`, juste avant les `#include` de dialogues.
- Les fichiers de dialogue ne déclarent plus rien : ils se contentent d’hériter des classes de base.
- Même traitement préventif pour l’addon `biometrics`, qui n’a qu’un dialogue aujourd’hui mais suivait le même schéma.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/zeus/config.cpp`
- `mod/@COMSPEC_SSE/addons/zeus/dialogs/generateDialog.hpp`
- `mod/@COMSPEC_SSE/addons/zeus/dialogs/modelDialog.hpp`
- `mod/@COMSPEC_SSE/addons/ui/config.cpp`
- `mod/@COMSPEC_SSE/addons/ui/dialogs/resultDialog.hpp`
- `mod/@COMSPEC_SSE/addons/ui/dialogs/common.hpp`
- `mod/@COMSPEC_SSE/addons/biometrics/config.cpp`
- `mod/@COMSPEC_SSE/addons/biometrics/dialogs/seekDialog.hpp`

## Vérification

- [x] Plus aucune déclaration `class Rsc…;` dans les fichiers de dialogue (recherche sur `mod/@COMSPEC_SSE`)
- [x] Rebuild des PBO `zeus`, `ui` et `biometrics` (`build_pbo.bat` 2026-08-16 — Build OK)
- [ ] Relancer Arma avec `@COMSPEC_SSE` — plus d’erreur au chargement
- [ ] Ouvrir les dialogues Modèles, Génération, Résultat et Seek en jeu

## Statut

corrigé dans les sources — rebuild PBO effectué ; confirmation in-game à faire
