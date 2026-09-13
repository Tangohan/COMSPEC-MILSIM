/*
    Retient une pièce jointe pour la fiche en cours.

    Rien n'est envoyé ici : les pièces partent après la fiche, une fois que le
    serveur a rendu son identifiant. Une capture prise maintenant montrerait
    l'interface du rédacteur ; elle est donc différée à la validation, quand le
    rédacteur est refermé et que la scène est visible.

    Args: [_source]
      "capture"  capture d'écran de la scène, prise à la validation
      "galerie"  photographie déjà prise (Photos ATAK ou captures d'écran du jeu)
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
        private _dispPicker = _disp displayCtrl 9714;
        if (!isNull _dispPicker && { ctrlShown _dispPicker }) exitWith {
            [false] call comspec_overwatch_connect_fnc_intelNotePhotoPicker;
        };

        private _photos = [] call comspec_overwatch_connect_fnc_intelNoteCollectPhotos;
        if (!(_photos isEqualType [])) then { _photos = [] };

        private _taken = _pieces apply { toLower (_x param [1, ""]) };
        private _available = [];
        {
            if (!(_x isEqualType [])) then { continue };
            private _path = _x param [0, ""];
            if (!(_path isEqualType "") || {_path isEqualTo ""}) then { continue };
            if ((toLower _path) in _taken) then { continue };
            _available pushBack _x;
        } forEach _photos;

        if (_available isEqualTo []) exitWith {
            [
                "Aucune photographie disponible. Prenez d’abord une photo depuis Photos, ou une capture d’écran en jeu, puis revenez les joindre ici.",
                "tactical",
                "warn"
            ] call comspec_overwatch_connect_fnc_announce;
        };

        if ((count _available) == 1) then {
            private _pick = _available select 0;
            _pick params [["_path", ""], ["_name", ""], ["_pgrid", ""], ["_pauthor", ""]];
            [_path, _name, _pgrid, _pauthor] call comspec_overwatch_connect_fnc_intelNoteAttachPhoto;
        } else {
            uiNamespace setVariable ["COMSPEC_IntelNote_PhotoChoices", _available];
            private _lb = _disp displayCtrl 9714;
            if (isNull _lb) exitWith {
                private _pick = _available select 0;
                _pick params [["_path", ""], ["_name", ""], ["_pgrid", ""], ["_pauthor", ""]];
                [_path, _name, _pgrid, _pauthor] call comspec_overwatch_connect_fnc_intelNoteAttachPhoto;
            };
            _lb setVariable ["filling", true];
            lbClear _lb;
            {
                _x params [["_path", ""], ["_name", ""]];
                if (_name isEqualTo "") then { _name = "photographie" };
                private _i = _lb lbAdd _name;
                private _uiPath = (_path splitString "\") joinString "/";
                if (_uiPath isNotEqualTo "") then { _lb lbSetPicture [_i, _uiPath] };
            } forEach _available;
            _lb setVariable ["filling", false];
            [true] call comspec_overwatch_connect_fnc_intelNotePhotoPicker;
            [
                "Choisissez la capture à joindre, ou PHOTO à nouveau pour fermer la liste.",
                "tactical",
                "info"
            ] call comspec_overwatch_connect_fnc_announce;
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

if ((toLower _source) isNotEqualTo "galerie") then {
    [false] call comspec_overwatch_connect_fnc_intelNotePhotoPicker;
};

[] call comspec_overwatch_connect_fnc_intelNoteRefresh;
