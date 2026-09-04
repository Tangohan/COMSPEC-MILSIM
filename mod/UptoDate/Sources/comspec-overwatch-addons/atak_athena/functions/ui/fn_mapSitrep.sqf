/*
    SITREP rapide : CONTACT, CASUALTY, VEHICLE, SUSPICIOUS, CLEAR + position.
*/
params [["_kind", "CONTACT", [""]], ["_world", []]];
if (!(_world isEqualType []) || {(count _world) < 2}) then { _world = getPos player; };
_kind = toUpper _kind;
if !(_kind in ["CONTACT", "CASUALTY", "VEHICLE", "SUSPICIOUS", "CLEAR"]) then { _kind = "CONTACT"; };
private _grid = [_world] call comspec_overwatch_atak_athena_fnc_formatGrid;
private _intel = switch (_kind) do {
    case "CASUALTY": { "CHAT" };
    case "VEHICLE": { "VEH" };
    case "CLEAR": { "CHAT" };
    default { "ENEMY_INF" };
};
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
    [player, _intel, format ["%1 @ %2", _kind, _grid], "", "INFANTRY", 0.8] call comspec_overwatch_connect_fnc_sendIntel;
};
private _mk = format ["COMSPEC_INTEL_%1", round diag_tickTime];
createMarkerLocal [_mk, _world];
_mk setMarkerTypeLocal "mil_warning";
_mk setMarkerColorLocal "ColorYellow";
_mk setMarkerTextLocal _kind;
["PRIORITY", format ["%1 — grille %2", _kind, _grid]] call comspec_overwatch_atak_athena_fnc_showNotification;
