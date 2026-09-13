/*
    Retient une photographie déjà présente sur le poste comme pièce jointe.

    Args: [_path, _name, _grid, _author]
    Retour : true si la pièce a été ajoutée.
*/
params [["_path", "", [""]], ["_name", "", [""]], ["_grid", "", [""]], ["_author", "", [""]]];

if (!hasInterface) exitWith { false };
if (_path isEqualTo "") exitWith { false };

private _catalog = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _piecesMax = _catalog getOrDefault ["pieces_max", 4];
private _pieces = uiNamespace getVariable ["COMSPEC_IntelNote_Pieces", []];
if (!(_pieces isEqualType [])) then { _pieces = [] };

if ((count _pieces) >= _piecesMax) exitWith {
    [
        format ["%1 pièces jointes au maximum : retirez-en une avant d’en ajouter.", _piecesMax],
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

private _taken = _pieces apply { toLower (_x param [1, ""]) };
if ((toLower _path) in _taken) exitWith {
    ["Cette photographie est déjà jointe à la fiche.", "tactical", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};

if (_grid isEqualTo "") then { _grid = mapGridPosition player };
if (_author isEqualTo "") then { _author = name player };
if (_name isEqualTo "") then {
    private _parts = _path splitString "\/";
    _name = if ((count _parts) > 0) then { _parts select ((count _parts) - 1) } else { "photographie" };
};

_pieces pushBack ["photo", _path, _name, _grid, _author, ""];
uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", _pieces];
[false] call comspec_overwatch_connect_fnc_intelNotePhotoPicker;
["Photographie retenue — elle partira avec la fiche.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
[] call comspec_overwatch_connect_fnc_intelNoteRefresh;
true
