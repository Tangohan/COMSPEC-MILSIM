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
// Tables d'objets et de marqueurs de la partie précédente : le relais web (MkSnap) supprimait côté Athena les marqueurs
// de l'ancienne mission, la liaison allié (LinkAlly) se coupait au démarrage sur un porteur disparu.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_MkSnap", "COMSPEC_ATAK_GlowLights", "COMSPEC_ATAK_LinkAlly", "COMSPEC_ATAK_DroneAlerted", "COMSPEC_ATAK_MapSearchKnown", "COMSPEC_ATAK_MapSearch", "COMSPEC_ATAK_DroneStatic"];
// États d'apps de la partie précédente, tous lus avec une valeur par défaut ("" ou table vide) : code de la salle de
// l'app Plan (resté affiché), cible et formulaires des apps Feux, Guerre électronique, Logistique et BDA (la cible
// « TGT » restait dessinée sur la carte). Numéro de la boucle de relevé de l'app Admin : lu par isNil, il supprimait
// le relevé automatique (à l'ouverture et toutes les 10 s) dans les missions suivantes.
{ uiNamespace setVariable [_x, nil]; } forEach ["COMSPEC_ATAK_PlanRoomId", "COMSPEC_ATAK_Fires", "COMSPEC_ATAK_Ew", "COMSPEC_ATAK_Logi", "COMSPEC_ATAK_BdaForm", "COMSPEC_ATAK_AdminPfh"];
// Drapeaux « en cours » de l'app Admin, lus avec false par défaut : une fin de mission pendant l'attente (lecture du
// parc Athena, redessin différé) les laissait levés, et cette lecture ou ce redessin restaient bloqués jusqu'au
// redémarrage d'Arma.
{ uiNamespace setVariable [_x, false]; } forEach ["COMSPEC_ATAK_AdminFleetBusy", "COMSPEC_ATAK_AdminRenderQ"];
uiNamespace setVariable ["COMSPEC_ATAK_State",_state];
uiNamespace setVariable ["COMSPEC_ATAK_Data",_data];
missionNamespace setVariable ["COMSPEC_ATAK_MapTool","SELECT",false];
true
