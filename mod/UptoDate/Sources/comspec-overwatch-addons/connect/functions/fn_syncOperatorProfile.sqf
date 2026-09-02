/*
    Envoie la fiche opérateur observée (register au premier passage, sync ensuite).
    Ne doit PAS être appelé à chaque tick de position.
    Params: [_event, _reason, _force]
    Retour: HashMap résultat (ok, pending, …) ou false
*/
params [
    ["_event", "sync", [""]],
    ["_reason", "profile_sync", [""]],
    ["_force", false, [true]]
];

if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith { false };
if !([] call comspec_overwatch_connect_fnc_isReady) exitWith { false };

if (!_force) then {
    private _backoff = missionNamespace getVariable ["COMSPEC_OperatorProfileBackoffUntil", 0];
    if (diag_tickTime < _backoff) exitWith { false };
};

private _payload = [player, _event, _reason] call comspec_overwatch_connect_fnc_buildOperatorProfile;
private _fp = _payload getOrDefault ["fingerprint", ""];
private _lastFp = missionNamespace getVariable ["COMSPEC_OperatorFingerprint", ""];
if (!_force && {(toLower _event) isNotEqualTo "register"} && {_fp isEqualTo _lastFp} && {_fp isNotEqualTo ""}) exitWith {
    false
};

private _identity = _payload getOrDefault ["identity", createHashMap];
private _steam = _identity getOrDefault ["steam_uid", ""];
if (_steam isEqualTo "") then { _steam = _payload getOrDefault ["steam_id", ""]; };
if ((count _steam) < 15) exitWith {
    ["WARN", "OperatorProfile", "Steam UID absent — fiche non transmise"] call comspec_overwatch_connect_fnc_log;
    false
};

private _fncOmitLoadout = {
    params ["_map"];
    private _eq = _map getOrDefault ["equipment", createHashMap];
    _eq set ["loadout", []];
    _eq set ["loadout_omitted", true];
    _map set ["equipment", _eq];
    _map set ["loadout", []];
    _map
};

private _equipment = _payload getOrDefault ["equipment", createHashMap];
private _loadout = _payload getOrDefault ["loadout", _equipment getOrDefault ["loadout", []]];
private _loadoutJson = [_loadout] call comspec_overwatch_connect_fnc_jsonValue;
if ((count _loadoutJson) > 14000) then {
    _payload = [_payload] call _fncOmitLoadout;
};

private _json = [_payload] call comspec_overwatch_connect_fnc_jsonValue;
if ((count _json) > 28000) then {
    private _eq = _payload getOrDefault ["equipment", createHashMap];
    _eq set ["magazines", []];
    _payload set ["equipment", _eq];
    _payload = [_payload] call _fncOmitLoadout;
    _json = [_payload] call comspec_overwatch_connect_fnc_jsonValue;
};

private _cmd = if ((toLower _event) isEqualTo "register") then { "OperatorRegister" } else { "OperatorSync" };
private _parsed = [
    _cmd,
    [_json, _steam],
    "fiche opérateur",
    true,
    true,
    "link",
    false
] call comspec_overwatch_connect_fnc_callExtLogged;
_parsed params ["_ok", "_status", "_detail"];

private _result = [_ok, _status, _detail] call comspec_overwatch_connect_fnc_applyOperatorProfileResponse;

if (_ok && {!(_result getOrDefault ["pending", false])}) then {
    missionNamespace setVariable ["COMSPEC_OperatorFingerprint", _fp, false];
    missionNamespace setVariable ["COMSPEC_OperatorRegistered", true, false];
};
if (_result getOrDefault ["pending", false]) then {
    missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", diag_tickTime + 300, false];
    missionNamespace setVariable ["COMSPEC_OperatorRegistered", true, false];
} else {
    if (!_ok && {(toUpper _status) in ["TIMEOUT", "NETWORK_ERROR"]}) then {
        missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", diag_tickTime + 30, false];
    };
};

private _bt = (_payload get "medical") getOrDefault ["blood_type", ""];
if (_bt isNotEqualTo "") then {
    ["COMSPECExtension" callExtension ["SetBloodType", [_bt]]] call comspec_overwatch_connect_fnc_extResult;
};

_result
