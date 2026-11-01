# Mode local : messages des canaux perdus et faux messages « Athena »

## Contexte
ATAK natif 2.9.25, serveur en mode full local (`comspec_atak_native_local_only`) ou joueur qui a répondu « Non » au
service Athéna. Inventaire du mode local fait pour la 2.9.26.

## Symptôme
- Messagerie, canaux général / commandement / groupe : le message n'apparaît que chez l'expéditeur, avec
  « Athena non connecté : message affiché ici seulement ». Seuls les SMS et les messages du poste TOC passaient.
- Chaque marqueur posé : « Liaison Athena pas prête : envoi au site en attente ».
- Compte rendu : « transmis · enregistré sur Athena » alors que rien n'est parti au site.
- MEDEVAC : « Impossible de transmettre la demande d'évacuation — vérifiez la liaison Athena » après « MEDEVAC envoyé au camp ».
- App CAS : le 9-line repartait en ordre Overwatch, qui n'arrive que comme notification (pas dans l'app Tâches).
- App Briefing : aucune diapositive possible sans Athena.

## Cause
`fn_bridge` reste vrai en mode local quand Overwatch connect est embarqué : les apps prenaient la branche Athena, puis
l'appel à la DLL était coupé (`fn_uplinkHold`) après coup. Seul `fn_orderLocal "local"` tenait compte du mode.

## Correctif
Test du mode (`fn_athenaMode` différent de « on ») dans chaque app concernée :
- `fn_chatSend` : message relayé au camp par le serveur (`comspec_atak_native_chatLocal`, rejoué aux arrivants, canal
  groupe limité au groupe), affiché aussi dans le flux du PC TOC (`fn_tocFeed`) ;
- `fn_markerWeb`, `fn_bdaAction`, `fn_medicalAction` : plus d'envoi au site ni de message d'échec ;
- `fn_reportAction` / `fn_pageReports` : état « LOCAL », message « transmis au camp » ;
- `fn_firesAction` : 9-line, 5-line et appels de feu en ordre local (`fn_orderLocal`, app Tâches, poste TOC) ;
- `fn_briefingAction "mission"`, `fn_briefingSignature`, `fn_briefingLive`, `fn_pageBriefing` : diapositives de la
  mission (`COMSPEC_ATAK_MissionSlides`).

## Fichiers touchés
`functions/pages/fn_chatSend.sqf`, `fn_bdaAction.sqf`, `fn_medicalAction.sqf`, `fn_firesAction.sqf`, `fn_briefing*.sqf`,
`fn_pageBriefing.sqf`, `functions/map/fn_markerWeb.sqf`, `functions/reports/fn_reportAction.sqf`, `fn_pageReports.sqf`,
`functions/toc/fn_tocFeed.sqf`, `XEH_postInitServer.sqf`, `XEH_postInitClient.sqf` (ATAK natif).

## Vérification
`hemtt check` sans erreur nouvelle. En jeu : serveur dédié en mode full local, deux clients et un arrivant (JIP).

## Statut
corrigé à vérifier en jeu (2.9.26)
