# Appui aérien vide sans déclaration de vol

## Contexte

Sur ATAK web, le panneau **Appui aérien** restait vide alors qu’un groupe
aérien existait déjà en session (exemple : Alpha 1-4, Chinook HC5,
équipage de pilotes IA, waypoint de déplacement).

## Symptôme

Message : « Aucun aéronef — Les pilotes enregistrent un vol depuis le
menu Overwatch en jeu (touche K). » Aucune pastille aérienne sur la
carte, alors que l’appareil est occupé.

## Cause

Seule une déclaration de vol (Overwatch, touche K) remplissait la liste.
Les véhicules aériens occupés (joueur ou IA) n’étaient pas remontés
d’eux-mêmes. Les unités déjà visibles sur ATAK assises dans un aéronef
n’étaient pas non plus regroupées en fiche d’appui.

## Correctif

- Un client relais envoie chaque aéronef occupé (au moins un vivant à
  bord), avec le nom du groupe ou du modèle comme libellé.
- Le poste fusionne cette occupation avec une déclaration de vol s’il y
  en a une : l’indicatif et les passagers de la déclaration l’emportent ;
  on ne montre pas deux fois le même appareil.
- Les unités déjà sur ATAK dans un hélicoptère, un avion ou un drone
  complètent la liste si besoin.
- Texte à vide : la touche K n’est plus obligatoire, elle enrichit la
  fiche (indicatif, passagers, mission).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportCrewedAirAssets.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initGpsBeacons.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportAllyPosition.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `app/Services/Tactical/AtakAirAssetMergeService.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Repositories/AtakDataRepository.php`
- `views/atak.php`
- `public/assets/js/atak-air-assets.js`

## Vérification

Tests unitaires de fusion (manifeste + occupation, deux appareils
distincts, quatre sièges d’un même Chinook). Rebuild du module connect.
À retester en mission : Chinook occupé sans déclaration de vol → la
fiche apparaît ; une déclaration ensuite enrichit la même fiche.

## Statut

Corrigé (sources) — rebuild Overwatch requis
