/*
    True si le joueur local est dans le rayon d’un relais encore intact.
*/
private _unit = missionNamespace getVariable ["COMSPEC_PlayerUnit", player];
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { false };

private _list = missionNamespace getVariable ["COMSPEC_AtakRelays", []];
private _ok = false;
{
    if (isNull _x) then { continue };
    if (!alive _x) then { continue };
    if (!(_x getVariable ["COMSPEC_AtakRelay", false])) then { continue };
    private _range = _x getVariable ["COMSPEC_AtakRelayRange", 2000];
    if (!(_range isEqualType 0)) then { _range = 2000; };
    if ((_unit distance2D _x) <= _range) then { _ok = true };
} forEach _list;
_ok
