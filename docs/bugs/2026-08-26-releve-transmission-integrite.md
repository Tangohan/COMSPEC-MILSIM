# Relevé de carte — transmission incomplète vers le poste

## Contexte

Fenêtre Zeus / éditeur « Relevé de la carte ». Un parcours de 8 min 57 s affiche 25 611 bâtiments, 81 870 forêts, 400 portions de relief, 2704 / 2704 secteurs. Rien n’indique si tout est arrivé au poste.

## Symptôme

Le relevé est marqué terminé en jeu. La carte web peut rester incomplète (refus d’accès, file saturée, coupure). Aucun bouton pour comparer ni renvoyer le manque.

## Cause

Les envois bâtiments / forêts / relief sont mis en file et comptés dès la collecte, pas à l’arrivée au poste. Un refus ultérieur n’était pas rapproché des totaux affichés.

## Correctif

- Le poste indique combien de bâtiments, forêts et relief il a déjà.
- Bouton **Vérifier et renvoyer** : compare avec le dernier relevé local, affiche l’écart, renvoie seulement ce qui manque (ou tout si les deux manquent).

## Fichiers touchés

- `app/Controllers/Api/AtakSceneApiController.php`
- `app/Repositories/AtakSceneObjectRepository.php`
- `app/Repositories/AtakTerrainRepository.php`
- `routes/web.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_theater_survey.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyVerify.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyRefresh.sqf`

## Vérification

Tests unitaires (fenêtre, liaison, catalogue). Recette : pack 1.4.83, relance Arma, ouvrir le relevé, Vérifier et renvoyer — si le poste a tout, message de confirmation ; sinon un second parcours ciblé.

## Statut

corrigé (sources) — pack Overwatch 1.4.83 à reconstruire pour Zeus
