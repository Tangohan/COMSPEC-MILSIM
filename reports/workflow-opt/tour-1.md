# Workflow opt SQF, tour 1 : corrections

Branche `opt/workflow-sqf`. Rien n'est commité et aucun PBO n'est construit.
`hemtt check` (ATAK natif) : aucune erreur nouvelle. Il ne reste que les deux erreurs connues `ctrlWebBrowserAction` (fn_screenCast, fn_slideWebShow).
Pour connect, main et SSE : les crochets et accolades sont équilibrés dans tous les fichiers modifiés, et tous les fichiers restent en CRLF.

## Appliqués (du plus grave au moins grave)

| # | Fichier | Changement |
|---|---------|------------|
| 4 | natif toc/fn_tocFeed.sqf | Flux TOC posé en local, puis `publicVariableClient` vers les seuls joueurs distants du camp. Il ne part plus vers tous les clients et n'entre plus dans la file JIP. |
| 9 | connect fn_isAtakFunctional.sqf | Ajout de la clé `device_destroyed` (false par défaut, puis la vraie valeur). Les menus ACE Diagnostic / Réparer ne lèvent plus d'erreur. |
| 1 | natif map/fn_mapGameMarkers, fn_strokeDraw, fn_localDataRefresh | La relecture complète des marqueurs, qui sert de filet, passe de 30 s à 300 s. Les commentaires sont mis à jour. |
| 2 | natif garmin/fn_watchMapDraw.sqf | Suppression de l'appel `bftFilter` fait à chaque image : le magasin « units » est déjà filtré. Le cache MapDrawCache n'est pas utilisé. |
| 5 | connect fn_initProxyTrackServer.sqf | `COMSPEC_AllyTrackSnapshots` n'est plus public. Il est envoyé au seul relais élu (joueur vivant, lié, plus petit UID), sauf si ce relais est local. |
| 6 | natif core/fn_aiAtak.sqf | `hasDevice` n'est appelé que lorsqu'un point est dû. Une IA sans ATAK voit son échéance repoussée de `_rate`. Le plafond de 250 ne compte que les IA équipées, comme avant. |
| 10 | connect fn_isNetworkDisconnected.sqf | Le `exitWith { true }` est remonté au niveau de la fonction : le brouillage Zeus coupe de nouveau la liaison. |
| 11 | connect fn_ecotiIlluminateZone.sqf | En mode toggle, l'extinction sort bien de la fonction : plus de lumière recréée juste après. |
| 17 | connect XEH_preInit.sqf + natif i18n/en.txt | Suppression du réglage mort `comspec_overwatch_required_item`. Les infobulles de require_item et de required_item_custom sont corrigées, avec leur traduction EN. |
| 18 | SSE interaction | Suppression de fn_doInspect.sqf, de sa classe et du réglage `comspec_sse_timeInspect`. Commentaire de fn_canInspect corrigé. Guides FR et EN et Guide_COMSPEC_SSE.html : « Inspecter » retiré. |
| 3 | natif garmin/fn_watch.sqf | La montre relit le suivi au plus une fois par seconde (`>= 1` au lieu de `> 0.5`). |
| 7 | natif shell/fn_captureServer.sqf | `_publish` ne republie la liste des captures que si elle a changé. |
| 8 | natif screen/fn_distanceServer.sqf | Le bilan de distances n'est renvoyé que s'il a changé. Le mémo `COMSPEC_ATAK_DistMeSent` est local au serveur et posé sur l'unité. |
| 12 | connect fn_canShowWinMessageBox, fn_isPlayerSpawnStable | Le test de `getClientStateNumber` est remonté au niveau de la fonction. |
| 13 | connect fn_isSyncableMapMarker.sqf | Les deux `exitWith { true }` sont remontés : préfixe BCE et filet du losange OTAN. |
| 14 | connect fn_theaterSurveyShow.sqf | Le garde « ouverture en cours » est placé avant tout le reste. L'autostart ne se lance que si la fenêtre existe. |
| 15 | SSE biometrics/fn_captureAll.sqf | Sans SEEK ni kits, la fonction sort vraiment : la capture de 25 s ne démarre plus. |
| 16 | connect fn_sseFaceStudio.sqf | Suppression du `setVariable` public `comspec_sse_studioClone`, que personne ne lit. |
| 19 | connect XEH_preInit.sqf | Suppression du réglage mort `radio_proximity_interval`. En-tête de fn_initRadioMonitor corrigé. |
| 20 | connect | Suppression de fn_placeAtakRelay_refactored.sqf (orphelin). |
| 21 | SSE compat_ace | Suppression de fn_aceDogtagOnCheck.sqf, de sa classe et du drapeau `aceDogtagWrapsEnabled`. Aucun changement côté ACE. |
| 22 | SSE digital | Suppression des 13 relais fn_extract*.sqf et de leurs classes. |
| 23 | SSE intel | Suppression de 6 fonctions mortes et de leurs classes. |
| 24 | connect | Suppression de fn_medicalInboxShow.sqf et de sa classe. |

## Ignorés

Aucun.

## Points à surveiller

- #13 : davantage de marqueurs « _… » au losange OTAN partent vers Athena. C'est le comportement documenté. Les marqueurs propres à Overwatch restent exclus par `isOwnedMapMarker` et par les listes de préfixes.
- #10 : le brouillage Zeus bloque de nouveau `canTransmit` pendant 45 s, ce qui est voulu.
- #12 : en MP, si `getClientStateNumber` renvoie autre chose que 10 en jeu (hôte non dédié à vérifier), les alertes médicales ou MessageBox de cet hôte seraient bloquées.
- Les entrées i18n et template orphelines (Item requis, détection radio, inspection SSE, confidenceLabel) restent en place. Elles sont sans danger et `tools/i18n.py` peut les nettoyer.
