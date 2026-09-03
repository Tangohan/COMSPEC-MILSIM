/*
    Recherche TA2 / OBJ BRAVO / CAS-03 → focus carte.
*/
params [["_q", "", [""]]];
_q = trim _q;
if (_q isEqualTo "" || {(toLower _q) isEqualTo "rechercher…"}) exitWith {};
private _low = toLower _q;
private _target = [];
private _state = missionNamespace getVariable ["COMSPEC_MapState", createHashMap];
{
    private _cs = toLower (_x getOrDefault ["callsign", ""]);
    private _grp = toLower (_x getOrDefault ["group", ""]);
    if ((_cs find _low) >= 0 || {(_grp find _low) >= 0}) exitWith {
        _target = _x getOrDefault ["pos", []];
    };
} forEach (_state getOrDefault ["units", []]);
if (_target isEqualTo []) then {
    {
        if ((toLower (markerText _x) find _low) >= 0 || {(toLower _x find _low) >= 0}) exitWith {
            _target = markerPos _x;
        };
    } forEach allMapMarkers;
};
if (_target isEqualTo [] || {(count _target) < 2}) exitWith {
    ["INFO", format ["Aucun résultat pour %1", _q]] call comspec_overwatch_atak_athena_fnc_showNotification;
};
private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _map = _disp displayCtrl 1201;
if (isNull _map) then { _map = _disp displayCtrl 16; };
if (!isNull _map) then {
    _map ctrlMapAnimAdd [0.45, 0.06, _target];
    ctrlMapAnimCommit _map;
};
["INFO", format ["Centrage sur %1", _q]] call comspec_overwatch_atak_athena_fnc_showNotification;
