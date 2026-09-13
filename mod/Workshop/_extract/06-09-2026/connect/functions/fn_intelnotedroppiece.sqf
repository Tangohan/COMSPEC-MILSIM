/*
    Retire une pièce jointe retenue pour la fiche en cours.

    Args: [_index]  rang de l'emplacement (0 à 3)
*/
params [["_index", -1, [0]]];

if (!hasInterface) exitWith {};

private _pieces = uiNamespace getVariable ["COMSPEC_IntelNote_Pieces", []];
if (!(_pieces isEqualType [])) then { _pieces = []; };
if (_index < 0 || {_index >= (count _pieces)}) exitWith {};

_pieces deleteAt _index;
uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", _pieces];

[] call comspec_overwatch_connect_fnc_intelNoteRefresh;
