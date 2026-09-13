/*
    Affiche ou masque la liste de choix des photographies, à la place des
    emplacements de pièces jointes.

    Args: [_on]
*/
params [["_on", false, [true]]];

if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

_disp setVariable ["COMSPEC_IntelNote_PickerOn", _on];

private _picker = _disp displayCtrl 9714;
if (!isNull _picker) then { _picker ctrlShow _on };

private _slotsVisible = (!_on) && {
    (uiNamespace getVariable ["COMSPEC_IntelNote_Pane", "redaction"]) isEqualTo "pieces"
};

{
    private _ctrl = _disp displayCtrl _x;
    if (!isNull _ctrl) then { _ctrl ctrlShow _slotsVisible };
} forEach [9632, 9633, 9634, 9635, 9636, 9637, 9638, 9639, 9710, 9711, 9712, 9713, 9715, 9716, 9717, 9718];

if (!_on) then {
    uiNamespace setVariable ["COMSPEC_IntelNote_PhotoChoices", []];
};

if (_slotsVisible) then {
    [] call comspec_overwatch_connect_fnc_intelNoteRefresh;
};
