/*
    Cible de l'app SSE terrain : entité SSE visée à moins de 6 m (personne ou objet),
    sinon l'entité SSE la plus proche à moins de 3 m.
    [] call comspec_sse_fnc_fieldTarget -> OBJECT | objNull
*/
private _ok = { params ["_o"]; !isNull _o && {_o != player} && {_o getVariable ["comspec_sse_enabled", false]} };
private _c = cursorObject;
if !([_c] call _ok) then { _c = cursorTarget; };
if ([_c] call _ok && {(player distance _c) <= 6}) exitWith { _c };

private _near = (nearestObjects [player, [], 3]) select { [_x] call _ok };
if (_near isEqualTo []) exitWith { objNull };
_near select 0
