/*
    Ouvre le mini-formulaire de demande d’appui aérien (idd 9988).
    Sur le téléphone ATAK : createDisplay pour ne pas fermer cTab.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

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
    ["Impossible d’ouvrir la demande d’appui aérien.", "order", "warn"] call comspec_overwatch_connect_fnc_announce;
};

uiNamespace setVariable ["COMSPEC_CasRequest_Display", _disp];

(_disp displayCtrl 9702) ctrlSetText format ["Grille %1", mapGridPosition player];
(_disp displayCtrl 9703) ctrlSetText "";

private _combo = _disp displayCtrl 9701;
lbClear _combo;
{
    _x params ["_label", "_code"];
    private _i = _combo lbAdd _label;
    _combo lbSetData [_i, _code];
} forEach [
    ["Appui immédiat (danger proche)", "CAS"],
    ["Reconnaissance aérienne", "RECON_AIR"],
    ["Couverture / survol", "COVER"],
    ["Extraction / extraction aérienne", "EXTRACT"]
];
_combo lbSetCurSel 0;
