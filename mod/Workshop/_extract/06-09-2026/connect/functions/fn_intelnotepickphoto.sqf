/*
    Choix d'une photographie dans la liste du volet pièces jointes.
*/
params ["_ctrl", ["_idx", -1, [0]]];

if (!hasInterface) exitWith {};
if (isNull _ctrl) exitWith {};
if (_ctrl getVariable ["filling", false]) exitWith {};
if (_idx < 0) exitWith {};

private _choices = uiNamespace getVariable ["COMSPEC_IntelNote_PhotoChoices", []];
if (!(_choices isEqualType []) || {_idx >= (count _choices)}) exitWith {};

private _pick = _choices select _idx;
if (!(_pick isEqualType [])) exitWith {};

_pick params [["_path", ""], ["_name", ""], ["_grid", ""], ["_author", ""]];
[_path, _name, _grid, _author] call comspec_overwatch_connect_fnc_intelNoteAttachPhoto;
