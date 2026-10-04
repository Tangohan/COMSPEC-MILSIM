/*
    Suivi drones pilotés par le joueur → carte Athena (Overwatch).
    Sources : drone appairé au téléphone natif (missionNamespace COMSPEC_ATAK_Drone)
              + UAV connecté au terminal (getConnectedUAV player)
              + UAV dont COMSPEC_DroneOwner = UID du joueur.
    Réutilise le chemin véhicule (UpdateVehicleTracking → /api/atak/vehicles),
    vehicle_class = "UAV" + bloc properties { kind = "uav", ... } pour la carte web.
    Delta : envoi si l’état change (≥ 3 s) ou battement toutes les 10 s.
    Retour : <NUMBER> nombre d’envois.
*/
if (!hasInterface) exitWith { 0 };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { 0 };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { 0 };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { 0 };
if (missionNamespace getVariable ["COMSPEC_HandshakeQuiet", false]) exitWith { 0 };
if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith { 0 };
private _backUntil = missionNamespace getVariable ["COMSPEC_ApiBackoffUntil", 0];
if ((_backUntil isEqualType 0) && {diag_tickTime < _backUntil}) exitWith { 0 };
if (isNull player) exitWith { 0 };

private _uid = getPlayerUID player;
private _now = diag_tickTime;
private _sendBack = missionNamespace getVariable ["COMSPEC_SendBackoffSec", 0];
if (!(_sendBack isEqualType 0)) then { _sendBack = 0; };
private _gap = 3 max _sendBack;
private _hb = 10 max _sendBack;

// --- Drones contrôlés maintenant ---
private _owned = [];
private _phoneDrone = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
if ((_phoneDrone isEqualType objNull) && {!isNull _phoneDrone} && {alive _phoneDrone}) then {
    _owned pushBackUnique _phoneDrone;
};
if (alive player) then {
    private _term = getConnectedUAV player;
    if (!isNull _term && {alive _term}) then { _owned pushBackUnique _term; };
};
if (_uid isNotEqualTo "") then {
    {
        if (alive _x && {(_x getVariable ["COMSPEC_DroneOwner", ""]) isEqualTo _uid}) then {
            _owned pushBackUnique _x;
        };
    } forEach allUnitsUAV;
};

// Indicatif pilote
private _pilotCs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _pilotCs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (!(_pilotCs isEqualType "") || {_pilotCs isEqualTo ""}) then { _pilotCs = name player; };

private _fnc_sideStr = {
    params ["_s"];
    switch (_s) do {
        case east: { "OPFOR" };
        case independent: { "INDEPENDENT" };
        case civilian: { "CIVILIAN" };
        default { "BLUFOR" };
    }
};

// Clé stable par drone (upsert Athena + coalescence lot DLL)
private _fnc_key = {
    params ["_d"];
    private _k = _d getVariable ["COMSPEC_UavTrackId", ""];
    if (_k isEqualTo "") then {
        private _nid = [_d] call BIS_fnc_netId;
        if (!(_nid isEqualType "")) then { _nid = str _nid; };
        _k = format ["UAV-%1", (_nid splitString ":") joinString "-"];
        _d setVariable ["COMSPEC_UavTrackId", _k, false];
        _d setVariable ["COMSPEC_UavTrackNid", _nid, false];
    };
    _k
};

private _fnc_send = {
    params ["_data"];
    private _json = [_data] call comspec_overwatch_connect_fnc_hashMapToJson;
    if (!(_json isEqualType "") || {_json isEqualTo ""}) exitWith { false };
    private _r = "COMSPECExtension" callExtension ["UpdateVehicleTracking", [_json]];
    (_r isEqualType []) && {(_r param [0, ""]) isEqualTo "OK"}
};

private _sent = 0;

// --- Drones relâchés / détruits depuis le dernier passage ---
private _prev = missionNamespace getVariable ["COMSPEC_DroneTrackSet", []];
if (!(_prev isEqualType [])) then { _prev = []; };
{
    if (!(_x isEqualType []) || {(count _x) < 3}) then { continue };
    _x params ["_d", "_key", "_name"];
    if (!isNull _d && {_d in _owned}) then { continue };
    private _st = if (isNull _d || {!alive _d}) then { "DESTROYED" } else { "ABANDONED" };
    [createHashMapFromArray [
        ["vehicle_callsign", _key],
        ["vehicle_id", _key],
        ["vehicle_name", _name],
        ["vehicle_class", "UAV"],
        ["status", _st]
    ]] call _fnc_send;
    _sent = _sent + 1;
} forEach _prev;

// --- Remontée des drones actifs ---
private _next = [];
{
    private _d = _x;
    private _key = [_d] call _fnc_key;
    private _nid = _d getVariable ["COMSPEC_UavTrackNid", ""];

    private _model = getText (configOf _d >> "displayName");
    if (_model isEqualTo "") then { _model = typeOf _d; };
    private _custom = _d getVariable ["COMSPEC_DroneName", ""];
    if (!(_custom isEqualType "")) then { _custom = ""; };
    private _icon = _d getVariable ["COMSPEC_DroneIcon", ""];
    if (!(_icon isEqualType "")) then { _icon = ""; };
    private _name = if (_custom isNotEqualTo "") then { _custom } else { format ["Drone de %1 — %2", _pilotCs, _model] };
    _next pushBack [_d, _key, _name];

    private _pos = getPosWorld _d;
    if ((abs (_pos select 0) < 1) && {abs (_pos select 1) < 1}) then { continue };
    private _agl = (getPos _d) select 2;
    private _asl = (getPosASL _d) select 2;
    private _dir = getDir _d;
    private _spd = speed _d;

    private _mode = _d getVariable ["COMSPEC_DroneMode", ""];
    if (!(_mode isEqualType "")) then { _mode = str _mode; };
    if (_mode isEqualTo "") then {
        _mode = if (isTouchingGround _d) then { "SOL" } else { "VOL" };
    };

    // Cible / tâche courante
    private _tgt = [];
    private _rawTgt = _d getVariable ["COMSPEC_DroneTgt", []];
    if (_rawTgt isEqualType objNull && {!isNull _rawTgt}) then { _rawTgt = getPosASL _rawTgt; };
    if ((_rawTgt isEqualType []) && {(count _rawTgt) >= 2} && {(_rawTgt select 0) isEqualType 0}) then {
        _tgt = [_rawTgt select 0, _rawTgt select 1];
    };
    private _taskKind = "";
    private _taskRad = 0;
    private _task = _d getVariable ["COMSPEC_DroneTask", []];
    if ((_task isEqualType []) && {(count _task) >= 2}) then {
        _taskKind = _task select 0;
        if (!(_taskKind isEqualType "")) then { _taskKind = str _taskKind; };
        private _tp = _task select 1;
        if (_tp isEqualType objNull) then { _tp = if (isNull _tp) then { [] } else { getPosASL _tp }; };
        _taskRad = _task param [2, 0];
        if (!(_taskRad isEqualType 0)) then { _taskRad = 0; };
        if (_tgt isEqualTo [] && {(_tp isEqualType [])} && {(count _tp) >= 2} && {(_tp select 0) isEqualType 0}) then {
            _tgt = [_tp select 0, _tp select 1];
        };
    };

    private _side = _d getVariable ["COMSPEC_DroneSide", sideUnknown];
    if (!(_side isEqualType sideUnknown) || {_side in [sideUnknown, civilian]}) then { _side = side group player; };

    // Delta (≈5 m, 2 m d’altitude, 5°)
    private _sig = format ["%1|%2|%3|%4|%5|%6|%7|%8|%9",
        round ((_pos select 0) / 5), round ((_pos select 1) / 5), round (_agl / 2),
        round (_dir / 5), _mode, _tgt apply { round (_x / 5) }, _taskKind, _custom, _icon];
    private _last = _d getVariable ["COMSPEC_UavTrackLastAt", -1e9];
    private _changed = _sig isNotEqualTo (_d getVariable ["COMSPEC_UavTrackSig", ""]);
    if ((_now - _last) < _gap) then { continue };
    if (!_changed && {(_now - _last) < _hb}) then { continue };
    private _seq = (_d getVariable ["COMSPEC_UavTrackSeq", 0]) + 1;
    _d setVariable ["COMSPEC_UavTrackSeq", _seq, false];
    _d setVariable ["COMSPEC_UavTrackLastAt", _now, false];
    _d setVariable ["COMSPEC_UavTrackSig", _sig, false];

    private _props = createHashMapFromArray [
        ["kind", "uav"],
        ["net_id", _nid],
        ["mode", _mode],
        ["alt_agl", round _agl],
        ["alt_asl", round _asl],
        ["speed_kmh", round _spd],
        ["pilot", _pilotCs],
        ["pilot_uid", _uid],
        ["model", _model],
        ["source", if (_d isEqualTo _phoneDrone) then { "phone" } else { "terminal" }],
        ["task", _taskKind],
        ["custom_name", _custom],
        ["name", _custom],
        ["icon", _icon],
        ["task_radius", _taskRad],
        ["seq", _seq]
    ];
    if (_tgt isNotEqualTo []) then {
        _props set ["tgt_x", _tgt select 0];
        _props set ["tgt_y", _tgt select 1];
    };

    private _data = createHashMapFromArray [
        ["vehicle_callsign", _key],
        ["vehicle_id", _key],
        ["vehicle_name", _name],
        ["vehicle_type", typeOf _d],
        ["vehicle_class", "UAV"],
        ["side", [_side] call _fnc_sideStr],
        ["unit_assigned", groupId (group player)],
        ["crew_commander_callsign", _pilotCs],
        ["crew_count", 0],
        ["passenger_count", 0],
        ["pos_x", _pos select 0],
        ["pos_y", _pos select 1],
        ["pos_z", _asl],
        ["heading", _dir],
        ["speed", _spd],
        ["fuel_percent", (fuel _d) * 100],
        ["hull_health", (1 - (damage _d)) * 100],
        ["status", if (damage _d > 0.8) then { "DAMAGED" } else { "OPERATIONAL" }],
        ["mission_type", "RECON"],
        ["mission_description", format ["Drone — mode %1", _mode]],
        ["properties", _props]
    ];
    if (_tgt isNotEqualTo []) then {
        _data set ["destination_pos_x", _tgt select 0];
        _data set ["destination_pos_y", _tgt select 1];
    };

    if ([_data] call _fnc_send) then { _sent = _sent + 1; };
} forEach _owned;

missionNamespace setVariable ["COMSPEC_DroneTrackSet", _next, false];
_sent
