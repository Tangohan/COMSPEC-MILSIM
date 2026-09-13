/*
    Charges ATAK encore armées que le joueur a le droit de déclencher (les siennes).
    Retour : [[chargeId, object, label, grid], ...]
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { [] };

private _uid = getPlayerUID _unit;
private _local = missionNamespace getVariable ["COMSPEC_ExplosiveLocalIds", []];
if (!(_local isEqualType [])) then { _local = []; };
private _objs = missionNamespace getVariable ["COMSPEC_ExplosiveObjects", createHashMap];
if (!(_objs isEqualType createHashMap)) then { _objs = createHashMap; };

private _out = [];
{
    private _cid = _x;
    if (!(_cid isEqualType "") || {_cid isEqualTo ""}) then { continue };
    private _exp = _objs getOrDefault [_cid, objNull];
    if (isNull _exp) then {
        _exp = [_cid] call comspec_overwatch_connect_fnc_findChargeObject;
    };
    if (isNull _exp) then { continue };
    if (!alive _exp) then { continue };
    if (_exp getVariable ["COMSPEC_detonateFired", false]) then { continue };
    if ((toLower (_exp getVariable ["COMSPEC_triggerKind", ""])) isNotEqualTo "atak") then { continue };

    private _owner = _exp getVariable ["COMSPEC_chargeOwnerUid", ""];
    private _mine = false;
    if (_owner isEqualType "" && {_owner isNotEqualTo ""}) then {
        _mine = (_owner isEqualTo _uid);
    } else {
        _mine = (_cid in _local);
    };
    if (!_mine) then { continue };

    private _label = getText (configOf _exp >> "displayName");
    if (_label isEqualTo "") then { _label = "Charge"; };
    _out pushBack [_cid, _exp, _label, mapGridPosition _exp];
} forEach _local;

_out
