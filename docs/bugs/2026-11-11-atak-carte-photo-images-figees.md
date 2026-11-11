# Téléphone qui rame : carte en fond photo figée au zoom

## Contexte
ATAK natif 4.4.0.AG (pack local de test), SSE 1.9.0, Altis, fond de carte PHOTO (mod @COMSPEC_ATAK_Photo, tuiles JPG).
Retour de TTRD le 11/11 : « ATAK bug on dirait, il a du mal à s'ouvrir, il lag, ça rame ».

## Symptôme
RPT du 11/11 (11:09) : 9 « Image figée » téléphone ouvert sur la carte. À la première ouverture 7,3 s (dont 0,47 s
mesurées dans le mod) ; ensuite, à chaque nouveau zoom, 1,3 à 1,9 s mesurées dans le dessin de la carte
(`carte (image)` max 1860 ms) alors que toutes ses sous-mesures restaient sous 3 ms. Une fois les zooms déjà vus, la
carte retombe à 2,2 ms par image et plus rien ne fige.

## Cause
`fn_photoMap` dessine jusqu'à 64 tuiles du fond photo par image. Une tuile jamais affichée est lue dans la PBO et
décodée (JPEG) par le jeu au premier `drawIcon` : un nouveau niveau de zoom en chargeait jusqu'à 64 d'un coup.
Code inchangé depuis ATAK 3.1.0 : pas une régression de 4.4.0.
La première ouverture (7,3 s, surtout hors des scripts du mod) n'est pas expliquée par le RPT : aucune étape de
l'ouverture n'était chronométrée.

## Correctif
- `fn_photoMap` : 2 tuiles neuves au plus par image (1 si l'image précédente a ramé), chargées à 1 % d'opacité ; la vue
  garde le niveau le plus fin déjà chargé en entier jusqu'à ce que le niveau visé soit prêt.
- `fn_mapOnDraw` : clés `[PERF]` pour le fond photo, les unités et les noms des repères.
- Réglage « Mesure des performances (journal) » (`fn_perfLog`) : étapes de l'ouverture du téléphone, apps, carte,
  bilan 10 s et appels de DLL lents dans le RPT (`[COMSPEC perf]`).
- `fn_zeusCentre` : erreur « call : Type Chaîne » de l'onglet Outils (traduction `_T` écrasée par `_t`).

## Fichiers touchés
`functions/map/fn_photoMap.sqf`, `functions/map/fn_mapOnDraw.sqf`, `functions/core/fn_perfLog.sqf` (nouveau),
`functions/core/fn_perfAcc.sqf`, `functions/ui/fn_open.sqf`, `fn_displayLoad.sqf`, `fn_navigate.sqf`, `fn_hudToggle.sqf`,
`fn_interactToggle.sqf`, `functions/network/fn_extensionCall.sqf`, `fn_extAwait.sqf`, `functions/zeus/fn_zeusCentre.sqf`,
`XEH_preInit.sqf`, `config.cpp`, `i18n/en.txt`, `localisation/template/xx.txt`.

## Vérification
Lecture du code, `hemtt check`, test `AtakPerfLogAssetTest`. Rien n'a tourné en jeu.
À tester : zoom et dézoom rapides sur le fond PHOTO, première ouverture en main sur la carte, réglage coché puis RPT relu.
Piste à part : refaire le pack photo en PAA (HEMTT dans le PATH pour `build_photo_pack.bat`) au lieu de JPG, plus rapide à
charger.

## Statut
corrigé à vérifier en jeu
