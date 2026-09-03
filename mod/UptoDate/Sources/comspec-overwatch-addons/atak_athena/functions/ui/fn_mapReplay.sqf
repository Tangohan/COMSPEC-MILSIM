/*
    Replay local : rejoue quelques minutes de positions du groupe.
*/
if (!hasInterface) exitWith {};
private _replay = missionNamespace getVariable ["COMSPEC_MapReplay", []];
if (!(_replay isEqualType []) || {(count _replay) < 2}) exitWith {
    ["INFO", "Pas encore d’historique de déplacement"] call comspec_overwatch_atak_athena_fnc_showNotification;
};
private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _map = _disp displayCtrl 1201;
if (isNull _map) then { _map = _disp displayCtrl 16; };
if (isNull _map) exitWith {};
["INFO", "Historique de groupe — lecture"] call comspec_overwatch_atak_athena_fnc_showNotification;
[_replay, _map] spawn {
    params ["_replay", "_map"];
    private _from = ((count _replay) - 60) max 0;
    private _i = _from;
    while { _i < (count _replay) } do {
        if (isNull _map) exitWith {};
        (_replay select _i) params ["", ["_snap", []]];
        if (_snap isNotEqualTo []) then {
            (_snap select 0) params ["", ["_pos", []]];
            if ((count _pos) >= 2) then {
                _map ctrlMapAnimAdd [0.12, 0.08, _pos];
                ctrlMapAnimCommit _map;
            };
        };
        uiSleep 0.18;
        _i = _i + 1;
    };
};
