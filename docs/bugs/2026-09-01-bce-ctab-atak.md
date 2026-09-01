# 2026-09-01 — Tablette ATAK Enhanced : dépendance manquante

## Contexte

Au lancement d’Arma, le pack Overwatch affiche un avertissement : l’addon Athena de la tablette exige un composant BCE qui n’existe plus sous l’ancien nom.

## Symptôme

Boîte de dialogue : *Addon 'comspec_overwatch_atak_athena' requires addon 'BCE_cTab'*. La tablette ATAK Enhanced ne se charge pas.

## Cause

`CfgPatches` de `atak_athena` déclarait encore `BCE_cTab`. Les packs BCE récents exposent `BCE_cTab_ATAK`.

## Correctif

Remplacer `BCE_cTab` par `BCE_cTab_ATAK` dans les addons requis.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `docs/technique/overwatch-mod/bibliotheques-et-dependances.md`
- `docs/technique/overwatch-mod/independance-couche-interoperabilite-api.md`

## Vérification

- Le fichier ne contient plus `"BCE_cTab"` comme addon requis, seulement `"BCE_cTab_ATAK"`.
- Tests d’assets Overwatch (versions 1.4.96 / 1.0.57).

## Statut

corrigé (pack Overwatch 1.4.96)
