/*
    Ouvre le formulaire de signalement joueur.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    ["Activez d’abord Overwatch pour signaler un problème.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull (uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["COMSPEC_PauseManager_Display", displayNull];
if (isNull _parent) then {
    _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
};

private _ok = false;
private _disp = displayNull;
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_BugReport_Dialog";
    _ok = !isNull _disp;
};

if (!_ok || {isNull _disp}) then {
    _ok = createDialog "COMSPEC_BugReport_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
    if (isNull _disp) then { _disp = findDisplay 9992; };
};

if (!_ok || {isNull _disp}) exitWith {
    ["Impossible d’ouvrir le formulaire de signalement.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_BugReport_Display", _disp];
(_disp displayCtrl 9802) ctrlSetText "";

private _combo = _disp displayCtrl 9801;
lbClear _combo;
{
    _x params ["_label", "_code"];
    private _i = _combo lbAdd _label;
    _combo lbSetData [_i, _code];
} forEach [
    ["Liaison / connexion Athena", "Liaison"],
    ["Menu ACE / ATAK", "ACE"],
    ["Carte / marqueurs", "Marqueurs"],
    ["Photos / messagerie", "Médias"],
    ["Autre", "Autre"]
];
_combo lbSetCurSel 0;

private _chk = _disp displayCtrl 9805;
if (!isNull _chk) then {
    _chk setVariable ["COMSPEC_LogAttach", true];
    _chk ctrlSetText "Journal de session : oui";
};
