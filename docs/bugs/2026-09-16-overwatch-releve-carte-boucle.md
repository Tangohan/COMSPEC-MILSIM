# Relevé de carte renvoyé en boucle (bâtiments, forêts, routes)

## Contexte

Le pack envoyait régulièrement au poste les volumes du théâtre autour de l’opérateur (bâtiments, forêts) et, sur action Zeus, tout le réseau villes / routes. Un opérateur en liaison voyait le même quartier partir encore et encore. Sur Overwatch, la carte restait vide (« aucune couverture ») tant qu’aucun relevé n’avait vraiment abouti, sans moyen clair de le lancer et de contrôler l’arrivée.

## Symptôme

Charge croissante au poste et sur la liaison : les mêmes bâtiments, arbres et routes revenaient toutes les quelques secondes dès qu’un joueur restait lié. À l’inverse, sans ce flux automatique, le poste pouvait n’afficher aucune couverture.

## Cause

Une boucle relançait le relevé local toutes les 45 secondes tant que la carte n’était pas marquée complète. Les trois actions ACE (relief, bâtiments, villes) envoyaient aussi sans fenêtre de suivi. La vérification d’intégrité renvoyait tout de suite le manque, sans laisser l’opérateur décider.

## Correctif

- Plus aucun envoi automatique des volumes de carte.
- Un seul bouton **Relevé de la carte** dans le menu Cartographie : fenêtre avec barre de progression et totaux (bâtiments, forêts, relief, villes, routes).
- À la fin du parcours, vérification d’intégrité auprès du poste, sans renvoi automatique.
- Bouton **Renvoyer les données manquantes** pour ne renvoyer que ce qui n’est pas arrivé.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleGeoNetwork.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyVerify.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyResend.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyRefresh.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_theater_survey.hpp`
- `app/Controllers/Api/AtakSceneApiController.php`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Tests d’assets : plus de boucle d’envoi ; ACE ouvre la fenêtre unique ; la vérification ne relance pas toute seule ; le renvoi est un bouton séparé. Recette : pack mis à jour, relance Arma, menu Cartographie → Relevé de la carte → Lancer le relevé.

## Statut

corrigé
