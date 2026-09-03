/*
    Quick ping SHIFT+clic : CONTACT / MOVE / DANGER / OBSERVE / RALLY (cycle).
*/
params [["_world", []], ["_kind", "", [""]]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith {};
private _cycle = ["CONTACT", "MOVE", "DANGER", "OBSERVE", "RALLY"];
if (_kind isEqualTo "") then {
    private _i = missionNamespace getVariable ["COMSPEC_MapPingKind", 0];
    _kind = _cycle select (_i mod 5);
    missionNamespace setVariable ["COMSPEC_MapPingKind", _i + 1, false];
};
if !(_kind in _cycle) then { _kind = "CONTACT"; };
private _grid = [_world] call comspec_overwatch_atak_athena_fnc_formatGrid;
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
    [player, "PING", _world, format ["%1 %2", _kind, _grid], "INFANTRY"] call comspec_overwatch_connect_fnc_sendIntel;
};
["PRIORITY", format ["%1 — %2", _kind, _grid]] call comspec_overwatch_atak_athena_fnc_showNotification;
