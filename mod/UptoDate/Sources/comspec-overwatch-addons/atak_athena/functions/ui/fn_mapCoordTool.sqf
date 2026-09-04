/*
    Clic carte → grille / MGRS, copie et envoi possible dans le chat.
*/
params [["_world", []]];
if (!(_world isEqualType []) || {(count _world) < 2}) exitWith {};
private _grid = [_world] call comspec_overwatch_atak_athena_fnc_formatGrid;
copyToClipboard _grid;
["INFO", format ["Grille %1 — copiée", _grid]] call comspec_overwatch_atak_athena_fnc_showNotification;
if (!isNil "comspec_overwatch_connect_fnc_sendIntel") then {
    [player, "CHAT", format ["GRILLE %1", _grid], "", "INFANTRY", 0.5] call comspec_overwatch_connect_fnc_sendIntel;
};
