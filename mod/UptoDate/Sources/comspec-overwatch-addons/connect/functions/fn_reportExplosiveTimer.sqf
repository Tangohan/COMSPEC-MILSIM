/*
    Envoie une charge à retardement (ou son issue) vers Athena.
    Retour : identifiant de charge (chaîne) ou "".
*/
params [
    ["_explosive", objNull, [objNull]],
    ["_delay", 0, [0]],
    ["_unit", objNull, [objNull]],
    ["_status", "armed", [""]],
    ["_chargeId", "", [""]]
];

private _statusKey = toLower _status;
if (!(_statusKey in ["armed", "detonated", "defused"])) then { _statusKey = "armed"; };

private _cid = _chargeId;
if (!(_cid isEqualType "")) then { _cid = ""; };
if (_cid isEqualTo "" && {!isNull _explosive}) then {
    _cid = _explosive getVariable ["COMSPEC_chargeId", ""];
    if (!(_cid isEqualType "")) then { _cid = ""; };
    if (_cid isEqualTo "") then { _cid = netId _explosive; };
    if (_cid isEqualTo "") then {
        private _p = getPosATL _explosive;
        _cid = format ["loc:%1:%2:%3", round (_p select 0), round (_p select 1), round CBA_missionTime];
    };
    _explosive setVariable ["COMSPEC_chargeId", _cid, true];
};
if (_cid isEqualTo "") exitWith { "" };

private _payload = createHashMap;
_payload set ["charge_id", _cid];
_payload set ["status", _statusKey];
_payload set ["mapId", 1];

if (_statusKey isEqualTo "armed" && {isNull _explosive}) exitWith { "" };

if (_statusKey isEqualTo "armed") then {
    private _pos = getPosATL _explosive;
    private _fuse = (round _delay) max 1;
    private _placer = _unit;
    if (isNull _placer) then { _placer = player; };
    private _author = if (!isNull player && {_placer isEqualTo player}) then {
        [] call comspec_overwatch_connect_fnc_getCallsign
    } else {
        if (isNull _placer) then { "" } else { name _placer }
    };
    if (_author isEqualTo "") then { _author = [] call comspec_overwatch_connect_fnc_getCallsign; };

    private _mag = "";
    {
        if (_mag isEqualTo "") then {
            private _v = _explosive getVariable [_x, ""];
            if (_v isEqualType "" && {_v isNotEqualTo ""}) then { _mag = _v; };
        };
    } forEach ["ace_explosives_magazine", "ace_explosives_magazineClass"];
    private _label = "";
    if (_mag isNotEqualTo "") then {
        _label = getText (configFile >> "CfgMagazines" >> _mag >> "displayName");
    };
    if (_label isEqualTo "") then {
        _label = getText (configOf _explosive >> "displayName");
    };
    if (_label isEqualTo "") then {
        _label = getText (configFile >> "CfgAmmo" >> typeOf _explosive >> "displayName");
    };
    if (_label isEqualTo "") then { _label = "Charge"; };

    _payload set ["author", _author];
    _payload set ["magazine_label", _label];
    _payload set ["grid", mapGridPosition _explosive];
    _payload set ["pos_x", _pos select 0];
    _payload set ["pos_y", _pos select 1];
    _payload set ["fuse_seconds", _fuse];

    private _local = missionNamespace getVariable ["COMSPEC_ExplosiveLocalIds", []];
    if (!(_local isEqualType [])) then { _local = []; };
    if (!(_cid in _local)) then { _local pushBack _cid; };
    while { (count _local) > 40 } do { _local deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ExplosiveLocalIds", _local, false];
};

private _json = [_payload] call comspec_overwatch_connect_fnc_hashMapToJson;
[
    "SubmitExplosiveTimer",
    [_json],
    "Charge à retardement",
    false,
    true,
    "liaison",
    true
] call comspec_overwatch_connect_fnc_callExtLogged;

_cid
