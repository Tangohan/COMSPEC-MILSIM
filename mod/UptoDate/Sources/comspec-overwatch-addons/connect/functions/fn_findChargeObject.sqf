/*
    Retrouve l’objet d’une charge ACE à partir de son identifiant Athena.
*/
params [["_chargeId", "", [""]]];
if (_chargeId isEqualTo "") exitWith { objNull };

private _found = objNull;
private _map = missionNamespace getVariable ["COMSPEC_ExplosiveObjects", createHashMap];
if (_map isEqualType createHashMap) then {
    _found = _map getOrDefault [_chargeId, objNull];
};
if (!isNull _found) exitWith { _found };

private _scan = {
    params ["_list", "_cid"];
    private _hit = objNull;
    {
        if (!isNull _x && {(_x getVariable ["COMSPEC_chargeId", ""]) isEqualTo _cid}) exitWith { _hit = _x };
    } forEach _list;
    _hit
};

_found = [allMines, _chargeId] call _scan;
if (!isNull _found) exitWith { _found };
_found = [entities "TimeBombCore", _chargeId] call _scan;
if (!isNull _found) exitWith { _found };
_found = [entities "PipeBombBase", _chargeId] call _scan;
_found
