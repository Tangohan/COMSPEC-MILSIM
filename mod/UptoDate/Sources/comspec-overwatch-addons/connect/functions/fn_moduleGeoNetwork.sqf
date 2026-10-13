/*
    Module « Relever routes et villes » (Eden et Zeus).
      Eden (mission lancée) : si « Au début de la mission », un seul joueur lié envoie routes et villes
                              dès qu'Athena est liée (attente 3 min max). Le Zeus ou l'admin passe en premier ;
                              sinon n'importe quel joueur lié prend le relais au bout d'une minute
                              (serveur dédié sans admin connecté : le relevé partait jamais).
      Zeus (ZEN / ACE Zeus)  : ["zeus"] → envoi immédiat, même si déjà transmis, avec un retour visible
                              dans l'interface Zeus (le journal du téléphone n'y est pas affiché).
    Le relevé lui-même : fn_sampleGeoNetwork (lieux Arma + segments routiers, Geo.Ingest).
*/
if ((_this param [0, ""]) isEqualTo "zeus") exitWith {
    if (!hasInterface) exitWith { false };
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
        ["COMSPEC Overwatch est désactivé sur ce poste : routes et villes ne peuvent pas partir.", true] call comspec_overwatch_connect_fnc_zeusFeedback;
        false
    };
    if ((missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isNotEqualTo "linked") exitWith {
        ["Athena n'est pas liée sur ce poste : routes et villes ne peuvent pas partir. Liez Athena (menu Échap > Dépannage liaison) puis reposez le module.", true] call comspec_overwatch_connect_fnc_zeusFeedback;
        false
    };
    if (!(["geo_places"] call comspec_overwatch_connect_fnc_isModModuleEnabled) && {!(["geo_roads"] call comspec_overwatch_connect_fnc_isModModuleEnabled)}) exitWith {
        ["Les modules « Lieux » et « Routes » sont désactivés pour cette communauté (réglages Athena).", true] call comspec_overwatch_connect_fnc_zeusFeedback;
        false
    };
    if (missionNamespace getVariable ["COMSPEC_GeoSampling", false]) exitWith {
        ["Un relevé des routes et villes est déjà en cours : il se termine tout seul.", false] call comspec_overwatch_connect_fnc_zeusFeedback;
        false
    };
    ["INFO", "Geo", "Relevé routes et villes demandé depuis Zeus"] call comspec_overwatch_connect_fnc_log;
    missionNamespace setVariable ["COMSPEC_GeoZeusNotify", true, false];
    ["Relevé des routes et villes lancé : les villes partent tout de suite, les routes suivent bande par bande.", false] call comspec_overwatch_connect_fnc_zeusFeedback;
    [0, true] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
    true
};

params [["_logic", objNull], ["_units", []], ["_activated", true]];
if (!(_logic isEqualType objNull) || {isNull _logic}) exitWith { false };
if (!(_activated isEqualType true) || {!_activated}) exitWith { false };
if (is3DEN) exitWith { true };
if (!hasInterface) exitWith { false };

private _mode = _logic getVariable ["RunAtStart", "start"];
private _resend = _logic getVariable ["Resend", false];
if (!(_resend isEqualType true)) then { _resend = false; };
private _run = if (_mode isEqualType "") then { (toLower (trim _mode)) in ["start", "1", "true", "oui", "auto"] } else { _mode isEqualTo true };
if (!_run) exitWith { true };

[_resend] spawn {
    params ["_resend"];
    private _linked = { (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked" };
    // Zeus et admin ne sont souvent connus qu'après l'initialisation : on les regarde une fois lié.
    private _priority = {
        (!isMultiplayer)
        || {serverCommandAvailable "#kick"}
        || {!isNull player && {!isNull (getAssignedCuratorLogic player)}}
    };
    private _claimed = { (missionNamespace getVariable ["COMSPEC_GeoNetworkRunner", ""]) isNotEqualTo "" };

    private _t0 = diag_tickTime;
    waitUntil { uiSleep 5; (call _linked) || {(diag_tickTime - _t0) > 180} };
    if !(call _linked) exitWith {
        ["WARN", "Geo", "Relevé routes et villes abandonné : Athena non liée après 3 min"] call comspec_overwatch_connect_fnc_log;
    };

    // Un seul envoi pour tout le serveur : priorité au Zeus / admin, sinon le premier joueur lié après 60 s.
    private _t1 = diag_tickTime;
    waitUntil { uiSleep 2; (call _claimed) || {call _priority} || {(diag_tickTime - _t1) > (60 + random 20)} };
    if (call _claimed) exitWith {
        ["INFO", "Geo", format ["Relevé routes et villes : déjà pris en charge par %1", missionNamespace getVariable ["COMSPEC_GeoNetworkRunner", "?"]]] call comspec_overwatch_connect_fnc_log;
    };
    private _me = if (isNull player) then { "?" } else { getPlayerUID player };
    if (_me isEqualTo "") then { _me = name player; };
    missionNamespace setVariable ["COMSPEC_GeoNetworkRunner", _me, true];
    uiSleep (1 + random 2);
    if ((missionNamespace getVariable ["COMSPEC_GeoNetworkRunner", ""]) isNotEqualTo _me) exitWith {};

    ["INFO", "Geo", format ["Relevé routes et villes au début de la mission (renvoi : %1)", _resend]] call comspec_overwatch_connect_fnc_log;
    [0, _resend] call comspec_overwatch_connect_fnc_sampleGeoNetwork;
};
true
