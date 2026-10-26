private _state = createHashMapFromArray [
 ["activePage","LAUNCHER"], ["chatPeer","ATHENA"], ["seenAthena",0], ["selectedTaskKey",""], ["selectedEntity",createHashMap], ["selectedMarker",""], ["selectedUnit",objNull], ["selectedTask",""],
 ["selectedLayer","ALL"], ["mapMode","SELECT"], ["mapFollow",false], ["mapMeasure",[]], ["cursorPos",[]], ["networkState","OFFLINE"], ["userProfile",createHashMap],
 ["notifications",[]], ["filters",createHashMap], ["panels",createHashMapFromArray [["right",true]]], ["history",[]],
 ["display",displayNull], ["scheduler",-1], ["lastFast",0], ["lastSecond",0], ["lastRemote",0], ["lastSlow",0], ["dirty",createHashMap]
];
private _data = createHashMapFromArray [
 ["units",createHashMap], ["remoteUnits",createHashMap], ["markers",createHashMap], ["remoteMarkers",createHashMap], ["tasks",createHashMap], ["legacyTasks",createHashMap], ["messages",[]], ["inbox",[]], ["outbox",[]], ["p2p",[]], ["intel",createHashMap], ["photos",[]],
 ["zones",[]], ["routes",[]], ["events",[]], ["briefing",createHashMapFromArray [["index",0],["total",0],["path",""]]],
 ["revisions",createHashMap], ["signatures",createHashMap], ["lastNetworkUpdate",-1]
];
// uiNamespace survit d'une mission à l'autre, pas les boucles CBA : un numéro de boucle resté d'une partie
// précédente empêchait de relancer l'animation ou la pompe d'envoi Bluetooth (BtPump : envois de fichiers bloqués à
// « 0 % » jusqu'au redémarrage d'Arma), ou en retirait une autre. Remis à zéro à chaque mission.
uiNamespace setVariable ["COMSPEC_ATAK_PhotoMode", false];
{ uiNamespace setVariable [_x, -1]; } forEach ["COMSPEC_ATAK_AarPfh", "COMSPEC_ATAK_DronePfh", "COMSPEC_ATAK_SpecPfh", "COMSPEC_ATAK_EcgPfh", "COMSPEC_ATAK_PlayerVizPfh", "COMSPEC_ATAK_PhotoPFH", "COMSPEC_ATAK_Vibrating", "COMSPEC_ATAK_BtPump"];
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_BftLast", "COMSPEC_ATAK_DevCache", "COMSPEC_ATAK_Flir", "COMSPEC_ATAK_MarkerTagPool", "COMSPEC_ATAK_HeightCache", "COMSPEC_ATAK_FriendList", "COMSPEC_ATAK_NearCache", "COMSPEC_ATAK_ForeignNames"];
// État Zeus de la partie précédente (annonces, alarme, écran qui bugue et numéro de sa boucle) : remis à zéro ici, au
// preInit, et non plus au premier appel de fn_zeusClient (jusqu'à 2 s après l'arrivée). Entre-temps, fn_zeusOverlay
// pouvait reprendre l'écran qui bugue de l'ancienne mission, ou lancer une seconde boucle si sa poignée était effacée
// après coup par cette remise tardive.
uiNamespace setVariable ["COMSPEC_ATAK_ZeusGlitchPfh", -1];
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_ZeusSeen", "COMSPEC_ATAK_ZeusSig", "COMSPEC_ATAK_ZeusNoise", "COMSPEC_ATAK_ZeusNoiseAt", "COMSPEC_ATAK_ZeusGlitch"];
// Tables d'objets et de marqueurs de la partie précédente : le relais web (MkSnap) supprimait côté Athena les marqueurs
// de l'ancienne mission, la liaison allié (LinkAlly) se coupait au démarrage sur un porteur disparu.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_MkSnap", "COMSPEC_ATAK_GlowLights", "COMSPEC_ATAK_LinkAlly", "COMSPEC_ATAK_DroneAlerted", "COMSPEC_ATAK_MapSearchKnown", "COMSPEC_ATAK_MapSearch", "COMSPEC_ATAK_DroneStatic"];
// Liaison montante publiée (fn_feedLink) et curseur du chat Athena natif (fn_pollChat) : propres à la mission.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_NetUpSig", "COMSPEC_ATAK_NetUpPend", "COMSPEC_ATAK_ChatAfter", "COMSPEC_ATAK_ChatSeen"];
// Dernière MPU5 vue (fn_mpu5) et terminal de bord (XEH_postInitClient) : sans remise à zéro, faux « MPU5 retirée » ou message
// « Tablette du véhicule disponible » manqué au début de la mission suivante.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_Mpu5Was", "COMSPEC_ATAK_TermKind"];
// États d'apps de la partie précédente, tous lus avec une valeur par défaut ("", liste ou table vide) : code de la
// salle de l'app Plan (resté affiché) et son historique ANNULER / RÉTABLIR (ANNULER pouvait renvoyer au plan partagé du
// serveur des tracés de la mission précédente), cible et formulaires des apps Feux, Guerre électronique, Logistique et
// BDA (la cible « TGT » restait dessinée sur la carte). Numéro de la boucle de relevé de l'app Admin : lu par isNil, il
// supprimait le relevé automatique (à l'ouverture et toutes les 10 s) dans les missions suivantes.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_PlanRoomId", "COMSPEC_ATAK_PlanUndo", "COMSPEC_ATAK_PlanRedo", "COMSPEC_ATAK_Fires", "COMSPEC_ATAK_Ew", "COMSPEC_ATAK_Logi", "COMSPEC_ATAK_BdaForm", "COMSPEC_ATAK_AdminPfh"];
// Drapeaux « en cours » de l'app Admin, lus avec false par défaut : une fin de mission pendant l'attente (lecture du
// parc Athena, redessin différé) les laissait levés, et cette lecture ou ce redessin restaient bloqués jusqu'au
// redémarrage d'Arma.
{ uiNamespace setVariable [_x, false]; } forEach ["COMSPEC_ATAK_AdminFleetBusy", "COMSPEC_ATAK_AdminRenderQ"];
uiNamespace setVariable ["COMSPEC_ATAK_State",_state];
uiNamespace setVariable ["COMSPEC_ATAK_Data",_data];
missionNamespace setVariable ["COMSPEC_ATAK_MapTool","SELECT",false];
// Alerte du téléphone jamais vue de la partie précédente (fn_fullAlert peut attendre jusqu'à 30 min) : elle ne doit
// pas s'afficher dans la mission suivante.
uiNamespace setVariable ["COMSPEC_ATAK_FullAlert", nil];
true
