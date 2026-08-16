/*
    Ouvre le mini-formulaire SALUTE (idd 9993).
    Sur le téléphone ATAK : createDisplay pour ne pas fermer cTab.
    Préremplit Emplacement (grille) et Heure (heure de jeu).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (uiNamespace getVariable ["COMSPEC_Salute_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_Salute_Dialog";
    _ok = !isNull _disp;
} else {
    _ok = createDialog "COMSPEC_Salute_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_Salute_Display", displayNull];
};

if (!_ok || {isNull _disp}) exitWith {
    ["Impossible d’ouvrir le compte rendu SALUTE.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_Salute_Display", _disp];

private _grid = mapGridPosition player;
private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;

(_disp displayCtrl 9403) ctrlSetText format ["Grille %1", _grid];
(_disp displayCtrl 9405) ctrlSetText _timeStr;
