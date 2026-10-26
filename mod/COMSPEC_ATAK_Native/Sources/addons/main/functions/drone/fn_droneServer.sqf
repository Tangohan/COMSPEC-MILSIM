/*
    Mode drone, côté serveur : toute demande d'un téléphone sur un drone (ordre de vol, réglage, appairage,
    désappairage, transfert, reprise de l'équipage IA) arrive ici par remoteExecCall [..., 2] (CfgRemoteExec :
    allowedTargets = 2, jip = 0). Elle n'est relayée au drone (fn_droneCmd, remoteExecCall vers le drone, donc là où il
    est local) que si elle vient de son pilote. Le pilote est tenu par le serveur seul : localNamespace
    COMSPEC_ATAK_DroneOwners ([[drone, uid]...], jamais diffusé). La variable publique COMSPEC_DroneOwner n'en est que
    l'affichage pour les téléphones : un client peut l'écrire, elle n'est jamais lue ici comme un droit.
    Params : [drone, demande, arguments]
      "pair"       []                                appairage au pied (fn_droneAction "pair") : demandeur vivant à moins
                                                     de 25 m d'un drone (unitIsUAV) libre, déjà à lui, ou dont le pilote
                                                     a quitté la partie ;
      "release"    []                                désappairage par le pilote ; ou drone d'un pilote parti, libéré par
                                                     le joueur qui y connecte son terminal (XEH_postInitClient) ;
      "transferIn" [nom, uid cible, point ASL, "HELLO" | "DONE"]   transfert par le pilote au joueur de cet UID, vivant à
                                                     moins de 60 m de lui ; "DONE" en fait le pilote (refusé s'il pilote
                                                     déjà un autre drone) ; le nom annoncé est celui que voit le serveur ;
      "relocal"    [ordre, arguments]                équipage IA ramené sur la machine du drone, puis "force" ;
      ordre de fn_droneCmd (vol, réglage, "force")   relayé au drone.
    Appel local au serveur (script de mission, serveur dédié) : pas de demandeur, la demande est relayée telle quelle
    (sauf "pair" et "transferIn", qui demandent un joueur). Hôte d'une partie locale et solo : le demandeur est le
    joueur, jugé comme les autres.
    Refus : journal du serveur, message au demandeur (fn_droneCmd "denied") et COMSPEC_DroneOwner remis au pilote que
    tient le serveur.
    Lot mpauth3 (SEC-022) : avant, l'événement CBA comspec_atak_native_droneCmd, qui ne dit pas qui l'a levé, faisait
    exécuter n'importe quel ordre à n'importe quel drone (frappe, atterrissage, désappairage, transfert vers soi,
    « relocal » sur le serveur), à la demande de n'importe quel client.
*/
params [["_d", objNull], ["_cmd", ""], ["_args", []]];
if (!isServer) exitWith {};
if (isRemoteExecutedJIP) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DRONE] Demande « %1 » refusée (rejouée depuis la file JIP)", _cmd];
};
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
private _req = [objNull, player] select hasInterface;
if (_remote) then { _req = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
if (_remote && {isNull _req}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DRONE] Demande « %1 » refusée au poste %2 (demandeur introuvable)", _cmd, remoteExecutedOwner];
};
if (!(_d isEqualType objNull) || {isNull _d} || {!(_cmd isEqualType "")} || {!(_args isEqualType [])}) exitWith {};
// Ordres relayés au drone tels quels (fn_droneCmd) ; "force" rejoue l'un d'eux.
private _orders = ["takeoff", "hover", "follow", "home", "land", "manual", "stick", "rth", "alt", "speed", "cqb", "strike", "hunt", "goto", "loiter", "observe", "escort", "route", "standby"];
private _uid = if (isNull _req) then { "" } else { getPlayerUID _req };
private _db = (localNamespace getVariable ["COMSPEC_ATAK_DroneOwners", []]) select { !isNull (_x select 0) && {alive (_x select 0)} };
private _i = _db findIf { (_x select 0) isEqualTo _d };
private _pilot = if (_i >= 0) then { (_db select _i) select 1 } else { "" };
// Pas de demandeur : appel du serveur lui-même.
private _isPilot = isNull _req || {_pilot isNotEqualTo "" && {_pilot isEqualTo _uid}};
private _online = { params ["_u"]; (allPlayers findIf { (getPlayerUID _x) isEqualTo _u }) >= 0 };
private _set = {
    params ["_u"];
    if (_i >= 0) then { _db deleteAt _i; };
    if (_u isNotEqualTo "") then { _db pushBack [_d, _u]; };
    localNamespace setVariable ["COMSPEC_ATAK_DroneOwners", _db];
    _d setVariable ["COMSPEC_DroneOwner", _u, true];
};
private _deny = {
    params ["_why", ["_msg", ""]];
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][DRONE] Demande « %1 » refusée à %2 (%3) sur %4 : %5", _cmd, [name _req, "serveur"] select (isNull _req), _uid, _d, _why];
    // Variable publique remise à ce que tient le serveur (le demandeur a pu l'écrire avant sa demande).
    if ((_d getVariable ["COMSPEC_DroneOwner", ""]) isNotEqualTo _pilot) then { _d setVariable ["COMSPEC_DroneOwner", _pilot, true]; };
    if (!isNull _req && {_msg isNotEqualTo ""}) then { [_d, "denied", [_msg]] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _req]; };
};
switch (_cmd) do {
    case "pair": {
        if (isNull _req) exitWith { ["appairage sans joueur demandeur"] call _deny; };
        if (!unitIsUAV _d || {!alive _d}) exitWith { ["objet qui n'est pas un drone en état"] call _deny; };
        if (!alive _req || {(_req distance _d) > 25}) exitWith { ["demandeur à plus de 25 m du drone", "Posez le drone à vos pieds pour l'appairer"] call _deny; };
        if (_pilot isNotEqualTo "" && {_pilot isNotEqualTo _uid} && {[_pilot] call _online}) exitWith { [format ["drone de %1, toujours en partie", _pilot], "Ce drone est déjà appairé à un autre téléphone"] call _deny; };
        [_uid] call _set;
    };
    case "release": {
        if (_pilot isEqualTo "") exitWith { ["drone appairé à personne"] call _deny; };
        if (!_isPilot && {[_pilot] call _online}) exitWith { [format ["drone de %1, toujours en partie", _pilot], "Ce drone est déjà appairé à un autre téléphone"] call _deny; };
        [""] call _set;
        _d setVariable ["COMSPEC_DroneLock", nil, true];
        if (alive _d) then { [_d, "release", []] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _d]; };
    };
    case "transferIn": {
        _args params [["_from", ""], ["_to", ""], ["_home", []], ["_stage", "DONE"]];
        if (isNull _req) exitWith { ["transfert sans joueur demandeur"] call _deny; };
        if (!_isPilot) exitWith { ["transfert demandé par un autre que le pilote", ["Ce drone est déjà appairé à un autre téléphone", "Ce drone n'est plus appairé au téléphone du pilote"] select (_pilot isEqualTo "")] call _deny; };
        if (!alive _d || {!(_to isEqualType "")} || {!(_stage in ["HELLO", "DONE"])}) exitWith { ["transfert mal formé"] call _deny; };
        private _k = allPlayers findIf { (getPlayerUID _x) isEqualTo _to };
        private _t = if (_k >= 0) then { allPlayers select _k } else { objNull };
        if (isNull _t || {!alive _t} || {(_t distance _req) > 60}) exitWith { ["destinataire absent ou à plus de 60 m du pilote", "Transfert annulé : destinataire hors de portée"] call _deny; };
        if ((_db findIf { (_x select 0) isNotEqualTo _d && {(_x select 1) isEqualTo _to} }) >= 0) exitWith { ["destinataire déjà pilote d'un autre drone", format ["%1 pilote déjà un drone", name _t]] call _deny; };
        if !(_home isEqualTypeArray [0, 0, 0]) then { _home = []; };
        if (_stage isEqualTo "DONE") then {
            [_to] call _set;
            _d setVariable ["COMSPEC_DroneLock", "", true];
        };
        [_d, "transferIn", [name _req, _to, _home, _stage]] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _t];
    };
    case "relocal": {
        if (!_isPilot) exitWith { ["reprise demandée par un autre que le pilote"] call _deny; };
        if (!alive _d || {!((_args param [0, ""]) in _orders)}) exitWith {};
        private _drv = driver _d;
        if (isNull _drv || {(groupOwner group _drv) isEqualTo (owner _d)}) exitWith {};
        (group _drv) setGroupOwner (owner _d);
        [{ [_this select 0, "force", _this select 1] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _this select 0]; }, [_d, _args], 1.5] call CBA_fnc_waitAndExecute;
    };
    default {
        if (!(_cmd in (_orders + ["force"])) || {_cmd isEqualTo "force" && {!((_args param [0, ""]) in _orders)}}) exitWith { [format ["ordre inconnu %1", _cmd]] call _deny; };
        if (!_isPilot) exitWith { ["ordre d'un autre que le pilote", ["Ce drone est déjà appairé à un autre téléphone", "Ce drone n'est plus appairé au téléphone du pilote"] select (_pilot isEqualTo "")] call _deny; };
        if (!alive _d) exitWith {};
        [_d, _cmd, _args] remoteExecCall ["comspec_atak_native_fnc_droneCmd", _d];
    };
};
