/*
    Trouve une IA alliée suivie ATAK par identifiant ALLY-… ou indicatif affiché.
*/
params [["_ref", "", [""]]];

private _needle = toLower (trim _ref);
if (_needle isEqualTo "") exitWith { objNull };

private _unit = objNull;
private _pool = missionNamespace getVariable ["COMSPEC_AllyTrackUnits", []];
if (!(_pool isEqualType [])) then { _pool = []; };

private _scan = {
    params ["_u"];
    if (!(_u isEqualType objNull) || {isNull _u} || {!alive _u}) exitWith { false };
    if (isPlayer _u) exitWith { false };
    private _aid = toLower (trim (_u getVariable ["COMSPEC_AllyTrackId", ""]));
    private _custom = toLower (trim (_u getVariable ["COMSPEC_AllyCallsign", ""]));
    private _cs = toLower (trim ([_u] call comspec_overwatch_connect_fnc_allyTrackCallsign));
    if (_aid isEqualTo _needle) exitWith { true };
    if (_cs isEqualTo _needle) exitWith { true };
    if (_custom isNotEqualTo "" && {_custom isEqualTo _needle}) exitWith { true };
    if (_aid isNotEqualTo "" && {(_needle find _aid) == 0}) exitWith { true };
    false
};

{
    if ([_x] call _scan) exitWith { _unit = _x; };
} forEach _pool;

if (!isNull _unit) exitWith { _unit };

{
    if ([_x] call _scan) exitWith { _unit = _x; };
} forEach allUnits;

_unit
