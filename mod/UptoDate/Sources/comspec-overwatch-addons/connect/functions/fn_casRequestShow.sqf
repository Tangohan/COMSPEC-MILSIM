/*
    Ouvre le formulaire de demande d'appui aérien.
    Avec le téléphone : application ATAK. Sinon : dialogue overlay.
    Params optionnel : position monde pour préremplir la grille (clic carte).
*/
params [["_world", []]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!(_world isEqualType []) || {(count _world) < 2}) then {
    _world = missionNamespace getVariable ["COMSPEC_CasPrefillPos", []];
};
if ((_world isEqualType []) && {(count _world) >= 2}) then {
    missionNamespace setVariable ["COMSPEC_CasPrefillPos", _world, false];
};

if (
    ([player] call comspec_overwatch_connect_fnc_hasTerminal)
    && { !isNil "comspec_overwatch_atak_athena_fnc_athena_openCas" }
) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_openCas;
};

if (!isNull (uiNamespace getVariable ["COMSPEC_CasRequest_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_CasRequest_Dialog";
    _ok = !isNull _disp;
} else {
    _ok = createDialog "COMSPEC_CasRequest_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_CasRequest_Display", displayNull];
};

if (!_ok || {isNull _disp}) exitWith {
    ["Impossible d'ouvrir la demande d'appui aérien.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_CasRequest_Display", _disp];
[_world] call comspec_overwatch_connect_fnc_casRequestFill;
