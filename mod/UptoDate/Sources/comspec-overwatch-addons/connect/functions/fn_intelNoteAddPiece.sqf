/*
    Retient une pièce jointe pour la fiche en cours.

    Rien n'est envoyé ici : les pièces partent après la fiche, une fois que le
    serveur a rendu son identifiant. Une capture prise maintenant montrerait
    l'interface du rédacteur ; elle est donc différée à la validation, quand le
    rédacteur est refermé et que la scène est visible.

    Args: [_source]
      "capture"  capture d'écran de la scène, prise à la validation
      "galerie"  photographie déjà présente dans la bibliothèque ATAK
      "releve"   relevé de position et d'instant, joint comme pièce écrite
*/
params [["_source", "capture", [""]]];

if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_IntelNote_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9982; };
if (isNull _disp) exitWith {};

private _catalog = [] call comspec_overwatch_connect_fnc_intelNoteCatalog;
private _piecesMax = _catalog getOrDefault ["pieces_max", 4];

private _pieces = uiNamespace getVariable ["COMSPEC_IntelNote_Pieces", []];
if (!(_pieces isEqualType [])) then { _pieces = []; };

if ((count _pieces) >= _piecesMax) exitWith {
    [
        format ["%1 pièces jointes au maximum : retirez-en une avant d’en ajouter.", _piecesMax],
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
};

private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = name player; };
private _grid = mapGridPosition player;

switch (toLower _source) do {
    case "galerie": {
        private _photos = [];
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos") then {
            _photos = [] call comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos;
        };
        if (!(_photos isEqualType [])) then { _photos = []; };

        // Déjà retenues : ne pas proposer deux fois la même photographie.
        private _taken = _pieces apply { toLower (_x param [1, ""]) };
        private _pick = [];
        {
            if (!(_x isEqualType [])) then { continue };
            private _path = _x param [0, ""];
            if (!(_path isEqualType "") || {_path isEqualTo ""}) then { continue };
            if (!((toLower _path) in _taken)) exitWith { _pick = _x; };
        } forEach _photos;

        if (_pick isEqualTo []) then {
            [
                "Aucune photographie disponible dans la bibliothèque ATAK. Prenez une capture, ou photographiez la scène depuis l’application Photos.",
                "tactical",
                "warn"
            ] call comspec_overwatch_connect_fnc_announce;
        } else {
            _pick params [["_path", ""], ["_name", ""], ["_pgrid", ""], ["_pauthor", ""]];
            if (_pgrid isEqualTo "") then { _pgrid = _grid; };
            if (_pauthor isEqualTo "") then { _pauthor = _callsign; };
            _pieces pushBack ["photo", _path, _name, _pgrid, _pauthor, ""];
            uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", _pieces];
            ["Photographie retenue — elle partira avec la fiche.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
        };
    };
    case "releve": {
        private _pos = getPosASL player;
        date params ["_year", "_month", "_day", "_hour", "_minute"];
        private _caption = format [
            "Relevé %1 — carroyage %2, altitude %3 m, %4h%5",
            _callsign,
            _grid,
            round (_pos select 2),
            floor _hour,
            if (_minute < 10) then { format ["0%1", floor _minute] } else { str (floor _minute) }
        ];
        _pieces pushBack ["croquis", "", "relevé de position", _grid, _callsign, _caption];
        uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", _pieces];
        ["Relevé de position retenu — il accompagnera la fiche.", "tactical", "info"] call comspec_overwatch_connect_fnc_announce;
    };
    default {
        _pieces pushBack ["capture", "", "", _grid, _callsign, ""];
        uiNamespace setVariable ["COMSPEC_IntelNote_Pieces", _pieces];
        [
            "Capture prévue : elle sera prise à la validation, une fois le rédacteur refermé.",
            "tactical",
            "info"
        ] call comspec_overwatch_connect_fnc_announce;
    };
};

[] call comspec_overwatch_connect_fnc_intelNoteRefresh;
