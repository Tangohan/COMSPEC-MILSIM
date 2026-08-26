/*
    Extrait la file de combat pour l’inclure dans extra de position, puis vide la file.
    Retour : fragment JSON (",\"combat_events\":[...],\"combat_contact\":true") ou "".
*/
if (!hasInterface) exitWith { "" };

private _q = missionNamespace getVariable ["COMSPEC_CombatQueue", []];
if (!(_q isEqualType []) || {count _q < 1}) exitWith { "" };

missionNamespace setVariable ["COMSPEC_CombatQueue", [], false];

private _parts = [];
{
    if (_x isEqualType createHashMap) then {
        _parts pushBack ([_x] call comspec_overwatch_connect_fnc_hashMapToJson);
    };
} forEach _q;

if ((count _parts) < 1) exitWith { "" };

format [",""combat_events"":[%1],""combat_contact"":true", _parts joinString ","]
