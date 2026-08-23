/*
    Photographies joignables à une fiche : bibliothèque ATAK si présente, plus
    les captures d'écran déjà prises en jeu.

    Retour : [path, fileName, grid, author][]
*/
if (!hasInterface) exitWith { [] };

private _out = [];
private _seen = [];

private _push = {
    params ["_path", ["_fileName", ""], ["_grid", ""], ["_author", ""]];
    if (!(_path isEqualType "") || {_path isEqualTo ""}) exitWith {};
    _path = trim _path;
    if (_fileName isEqualTo "") then {
        private _parts = _path splitString "\/";
        _fileName = if ((count _parts) > 0) then { _parts select ((count _parts) - 1) } else { _path };
    } else {
        _fileName = trim _fileName;
    };
    private _key = toLower _path;
    if (_key in _seen) exitWith {};
    _seen pushBack _key;
    private _nameKey = toLower _fileName;
    if (_nameKey isNotEqualTo "" && {_nameKey in _seen}) exitWith {};
    if (_nameKey isNotEqualTo "") then { _seen pushBack _nameKey; };
    if (_grid isEqualTo "") then { _grid = mapGridPosition player; };
    if (_author isEqualTo "") then { _author = name player; };
    _out pushBack [_path, _fileName, _grid, _author];
};

{
    [_x param [0, ""], _x param [1, ""], _x param [2, ""], _x param [3, ""]] call _push;
} forEach ([] call comspec_overwatch_connect_fnc_listLocalScreenshots);

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos") then {
    private _photos = [] call comspec_overwatch_atak_athena_fnc_athena_collectLocalPhotos;
    if (_photos isEqualType []) then {
        {
            if (!(_x isEqualType [])) then { continue };
            [_x param [0, ""], _x param [1, ""], _x param [2, ""], _x param [3, ""]] call _push;
        } forEach _photos;
    };
};

_out
