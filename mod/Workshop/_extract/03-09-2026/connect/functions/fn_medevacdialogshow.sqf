/*
    Ouvre le mini-formulaire MEDEVAC (idd 9987).
    Sur le téléphone ATAK : createDisplay pour ne pas fermer cTab.
    Préremplit l'emplacement (grille). Nombre de blessés vide (pas d'invention).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if (!isNull (uiNamespace getVariable ["COMSPEC_Medevac_Display", displayNull])) exitWith {};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    _parent = findDisplay 46;
};

private _ok = false;
private _disp = displayNull;
if (!isNull _parent) then {
    _disp = _parent createDisplay "COMSPEC_Medevac_Dialog";
    _ok = !isNull _disp;
} else {
    _ok = createDialog "COMSPEC_Medevac_Dialog";
    _disp = uiNamespace getVariable ["COMSPEC_Medevac_Display", displayNull];
};

if (!_ok || {isNull _disp}) exitWith {
    ["Impossible d’ouvrir la demande d’évacuation médicale.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_Medevac_Display", _disp];

private _grid = mapGridPosition player;
(_disp displayCtrl 9601) ctrlSetText "";
(_disp displayCtrl 9603) ctrlSetText format ["Grille %1", _grid];
(_disp displayCtrl 9604) ctrlSetText "";

private _combo = _disp displayCtrl 9602;
lbClear _combo;
{
    _x params ["_label", "_code"];
    private _i = _combo lbAdd _label;
    _combo lbSetData [_i, _code];
} forEach [
    ["Urgent — extraction immédiate", "URGENT"],
    ["Prioritaire — dès que possible", "PRIORITY"],
    ["Différé — peut attendre", "ROUTINE"]
];
_combo lbSetCurSel 0;
