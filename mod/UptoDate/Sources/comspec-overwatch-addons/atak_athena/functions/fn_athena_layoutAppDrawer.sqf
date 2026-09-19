/*
    Menu d’applications : fond charbon, libellés cyan.
    La grille (3 colonnes, icône + nom) est uniquement celle d’IceMan.
    Ne pas déplacer, recréer ni masquer les tuiles : ça vide le tiroir et vole les clics.
*/
if (!hasInterface) exitWith {};

params [["_open", true]];

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};
if (isNull _disp) then { _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull]; };
if (isNull _disp) exitWith {};

private _grp = _disp displayCtrl (17000 + 4660);
if (isNull _grp) then { _grp = _disp displayCtrl 4660; };
if (isNull _grp) exitWith {};

private _charcoal = [0.071, 0.071, 0.071, 0.98];
private _cyan = [0.37, 0.78, 0.95, 1];

private _bg = _grp controlsGroupCtrl 9;
if (!isNull _bg) then {
    _bg ctrlSetBackgroundColor _charcoal;
};
_grp ctrlSetBackgroundColor _charcoal;

if (!_open) exitWith {};

{
    if ((ctrlIDC _x) == 9) then { continue };
    _x ctrlSetTextColor _cyan;
    private _lab = toLower (ctrlText _x);
    if ((_lab find "wave relay") >= 0 || {_lab find "waverelay" >= 0}) then {
        _x ctrlSetText "<t size='1'>Relais AT</t>";
        _x ctrlSetTooltip "Mât le plus proche : position, débit, fiabilité, identité.";
    };
} forEach (allControls _grp);
