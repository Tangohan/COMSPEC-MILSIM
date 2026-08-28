# Bug — Formations en double (toute la plateforme + communauté)

## Contexte

Tableau de bord, bandeau « Nos formations ». Un parcours publié à l’échelle de toute la plateforme existe aussi en copie pour la communauté (même adresse courte, seeds officiels : portail, postes, recrutement).

## Symptôme

Chaque carte concernée apparaît deux fois d’affilée. Les parcours qui n’existent que dans un seul périmètre (ATAK, Task Force Radio, etc.) restent uniques.

## Cause

Le carrousel et le catalogue unionnaient « parcours de cette organisation » et « parcours proposés à toute la plateforme » sans regrouper le même slug. Une copie communauté et l’exemplaire plateforme étaient donc deux lignes distinctes.

## Correctif

Regrouper par adresse courte : la copie de la communauté l’emporte. Le parcours plateforme n’est listé que s’il n’existe pas déjà pour l’organisation.

## Fichiers touchés

- `app/Repositories/TrainingCourseRepository.php`
- `app/Services/Training/TrainingService.php`
- `tests/Unit/TrainingCatalogDedupeAssetTest.php`

## Vérification

- Test unitaire : slug jumeau plateforme + communauté → une seule carte, identifiant communauté.
- Test unitaire : plateforme seule → la carte reste.
- Recharger le tableau de bord : plus de paires identiques dans « Nos formations ».

## Statut

corrigé
