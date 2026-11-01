# Tour 3 : corrections appliquées

Branche `opt/workflow-sqf`. Rien n'est commité et aucun PBO n'a été construit. Les fichiers SQF et config restent en UTF-8 CRLF.

## Constats et corrections

| # | Gravité | Fichier | Correction | État |
|---|---|---|---|---|
| 1 | moyen | `COMSPEC_ATAK_Native/.../ui/fn_foreignHud.sqf` | Le balayage de `allVariables uiNamespace` écarte d'abord les noms qui contiennent `_fnc_` (le cache des fonctions) et ne fait le `getVariable` qu'après. Il passe à 30 s (contre 10 s avant), avec le commentaire mis à jour. La remise à 0 dans "start" est conservée. | appliqué |
| 2 | faible | `COMSPEC_ATAK_Native/.../shell/fn_compromise.sqf` | `COMSPEC_ATAK_PhoneTag` n'est plus envoyé qu'au serveur et au joueur lui-même (`[2, clientOwner]`). Il ne part plus vers tous les clients ni dans la file JIP. L'IMEI et l'UID Athena ne sont donc plus visibles par les autres joueurs. | appliqué |
| 3 | moyen | `connect/functions/fn_initProxyTrackServer.sqf` | Le relais est élu avant les sorties anticipées sur la signature. Le serveur garde en mémoire le dernier destinataire (`COMSPEC_AllyTrackSnapTo`). Si le relais change, le tableau lui est envoyé même quand les IA ne bougent pas. | appliqué |
| 4 | moyen | `connect/XEH_postInit.sqf` | Suppression de la boucle `allMapMarkers`. Elle effaçait chez chaque client les marqueurs Eden ou joueurs dont le texte commence par « SSE ». | appliqué |
| 5 | faible | `@COMSPEC_SSE/addons/intel/functions/fn_createMapMarkers.sqf`, `intel/XEH_postInit.sqf` | La fonction devient un stub qui lit ses paramètres (`params`) et renvoie `[]`. Son appel au postInit est retiré. La déclaration dans `config.cpp` et l'appel de `fn_advanceExploitation` restent en place. | appliqué |
| 6 | faible | `@COMSPEC_SSE/addons/digital/` | Retrait du bloc `Extended_PostInit_EventHandlers` dans `config.cpp`. Suppression de `XEH_postInitClient.sqf`. `fn_initDigitalACE.sqf` devient un stub qui renvoie `true`. `class initDigitalACE {}` est conservée. | appliqué |
| 7 | faible | `connect/functions/fn_playerInHatchetVehicle.sqf`, `connect/config.cpp` | Suppression du fichier et de sa déclaration de classe. Personne n'appelait cette fonction. La version SSE est conservée. | appliqué |
| 8 | faible | `@COMSPEC_SSE/addons/interaction/functions/fn_installEntityAceMenus.sqf`, `fn_initACE.sqf` | Lecture directe de `objectRoot`. L'entrée `personRoot` n'est plus écrite dans le cache. La variable `_rootPerson` reste utilisée par `addClass CAManBase`. | appliqué |

## Ignorés

Aucun.

## Vérifications

- `hemtt check` (ATAK natif) ne remonte aucune erreur nouvelle. Les deux seules erreurs sont les SPE2 déjà connues (`ctrlWebBrowserAction` dans fn_slideWebShow et fn_screenCast). Aucun avertissement ne porte sur fn_foreignHud ni sur fn_compromise.
- connect et SSE : les crochets, accolades et parenthèses sont équilibrés et il ne reste aucun LF seul dans les fichiers modifiés. Le léger écart d'accolades de `connect/config.cpp` existe déjà à l'identique dans HEAD : il vient de l'analyse approximative, pas de la modification.
- Tests PHP : `AtakAllyDistanceAssetTest` lit la chaîne `COMSPEC_AllyTrackSnapshots` dans fn_initGpsBeacons, qui n'a pas été modifié. `HatchetCockpitAceGuardAssetTest` lit la version SSE, qui est conservée. Les tests n'ont pas été lancés.

## Point à surveiller

`mod/COMSPEC_ATAK_Native/localisation/template/sse.json` cite encore `@COMSPEC_SSE/digital/XEH_postInitClient.sqf` (entrée `c88d6d28683a54ac`) comme emplacement d'une phrase. Le modèle est généré par `tools/i18n.py` et il faut le régénérer. Aucun test ne vérifie que ces fichiers existent.
