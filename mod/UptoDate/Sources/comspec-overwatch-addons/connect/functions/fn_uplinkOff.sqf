/*
    Liaison Athena refusée pour ce joueur : réglage d'addon « comspec_overwatch_enabled » décoché, service sur « Non » ou
    mode full local. Lu par fn_uplinkHold et par les appels directs du SSE. Un serveur sans interface ne dépend pas du choix d'un joueur.
    Retour : true = rien ne doit partir.
*/
if (!hasInterface) exitWith { false };
!(missionNamespace getVariable ["comspec_overwatch_enabled", true])
    || {([] call comspec_overwatch_connect_fnc_athenaMode) isEqualTo "off"}
