/*
    Signets par espace de travail MISSION / SERVER / THEATER.
    Sans argument = enregistre la position joueur. Même nom = recentre.
*/
params [["_name", "", [""]], ["_pos", []]];
private _ws = missionNamespace getVariable ["COMSPEC_MapWorkspace", "MISSION"];
private _key = format ["COMSPEC_MapBookmarks_%1", _ws];
private _list = missionNamespace getVariable [_key, profileNamespace getVariable [_key, []]];
if (!(_list isEqualType [])) then { _list = []; };
if (_name isEqualTo "") then {
    _name = format ["PT %1", [daytime, "HH:MM"] call BIS_fnc_timeToString];
};
if (!(_pos isEqualType []) || {(count _pos) < 2}) then { _pos = getPos player; };
private _found = _list findIf { (_x select 0) isEqualTo _name };
if (_found >= 0) then {
    private _p = _list select _found select 1;
    private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    private _map = _disp displayCtrl 1201;
    if (!isNull _map) then {
        _map ctrlMapAnimAdd [0.4, 0.07, _p];
        ctrlMapAnimCommit _map;
    };
    ["INFO", format ["Signet %1 (%2)", _name, _ws]] call comspec_overwatch_atak_athena_fnc_showNotification;
} else {
    _list pushBack [_name, _pos];
    missionNamespace setVariable [_key, _list, false];
    profileNamespace setVariable [_key, _list];
    saveProfileNamespace;
    ["INFO", format ["Signet enregistré : %1 (%2)", _name, _ws]] call comspec_overwatch_atak_athena_fnc_showNotification;
};
