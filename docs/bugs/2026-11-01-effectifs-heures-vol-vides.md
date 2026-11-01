# Tableur des effectifs : heures de vol toujours vides

## Contexte

1er novembre 2026. Back-office › Ressources › Effectifs, onglet Tableau (vue générale et vue « Heures de vol »).

## Symptôme

La colonne « Heures de vol » affichait « — » pour tout le monde, même pour des pilotes dont la fiche montre des heures. Le tri « Heures de vol » ne changeait rien.

## Cause

Le tableur demandait le relevé des heures au conteneur de services (`Container::get(AtakDistanceRepository::class)`). Ce service n’y était pas déclaré : le conteneur levait « Unknown service », l’erreur était avalée par un `try/catch` et la liste des heures restait vide. La fiche du membre, elle, crée le relevé directement : c’est pourquoi elle montrait les heures.

## Correctif

Déclaration de `AtakDistanceRepository` dans `App\Core\Container`. Le tableur lit de nouveau `atak_pilot_time` (heures relevées en jeu).

Remarque : comme avant, le tableur ne compte que les heures relevées en jeu. Les vols saisis à la main et contresignés dans le carnet de vol n’y sont pas ajoutés (le carnet reste la référence pour ceux-là).

## Fichiers touchés

- `app/Core/Container.php`
- `tests/Unit/EffectifsRosterPilotHoursContainerTest.php`

## Vérification

- Test unitaire : le conteneur rend bien le relevé des heures.
- Sur le site : ouvrir le tableur, vérifier qu’un pilote connu affiche ses heures, puis la vue « Heures de vol » (colonnes par appareil, dernier vol) et le tri « Heures de vol ».

## Statut

Corrigé.
