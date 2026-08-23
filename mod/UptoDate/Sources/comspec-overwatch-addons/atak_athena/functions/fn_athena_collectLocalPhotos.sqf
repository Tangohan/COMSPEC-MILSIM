/*
    Liste les captures locales disponibles pour envoi Athena.
    Retourne : tableau de [path, fileName, grid, author, sortKey]
    Sources : Photo Library Iceman + cache COMSPEC des captures récentes (Quick Pictures).
*/
if (!hasInterface) exitWith { [] };

private _out = [];
private _seen = [];

private _fnc_cleanPath = {
    params ["_p"];
    if (!(_p isEqualType "") || {_p isEqualTo ""}) exitWith { "" };
    _p = trim _p;
    private _n = count _p;
    if (_n >= 2) then {
        private _a = _p select [0, 1];
        private _b = _p select [_n - 1, 1];
        private _dq = toString [34];
        if ((_a isEqualTo _dq && {_b isEqualTo _dq}) || {_a isEqualTo "'" && {_b isEqualTo "'"}}) then {
            _p = _p select [1, _n - 2];
            _p = trim _p;
        };
    };
    // SQF n’a pas d’échappement : "\\" vaut DEUX antislashs. Le séparateur voulu est simple.
    (_p splitString "/") joinString "\"
};

private _push = {
    params ["_path", ["_fileName", ""], ["_grid", ""], ["_author", ""]];
    _path = [_path] call _fnc_cleanPath;
    if (_path isEqualTo "") exitWith {};
    // Si la bibliothèque ne fournit qu’un nom de fichier, le conserver tel quel (résolu côté extension).
    if (_fileName isEqualTo "") then {
        private _parts = _path splitString "\/";
        _fileName = if ((count _parts) > 0) then { _parts select ((count _parts) - 1) } else { _path };
    } else {
        _fileName = trim _fileName;
    };
    private _key = toLower _path;
    if (_key in _seen) exitWith {};
    _seen pushBack _key;
    // Clé secondaire : même capture listée sous chemins différents (jpg vs chemin absolu).
    private _nameKey = toLower _fileName;
    if (_nameKey isNotEqualTo "" && {_nameKey in _seen}) exitWith {};
    if (_nameKey isNotEqualTo "") then { _seen pushBack _nameKey; };
    if (_grid isEqualTo "") then { _grid = mapGridPosition player; };
    if (_author isEqualTo "") then { _author = name player; };
    _out pushBack [_path, _fileName, _grid, _author, _fileName];
};

// 1) Photo Library Iceman (format officiel >= 14 champs)
if (!isNil "Iceman_fnc_photo_getRecords") then {
    private _records = call Iceman_fnc_photo_getRecords;
    if (_records isEqualType []) then {
        {
            if (!(_x isEqualType [])) then { continue };
            if ((count _x) < 4) then { continue };
            private _src = if ((count _x) > 1) then { _x select 1 } else { "local" };
            if (_src isEqualTo "received") then { continue };
            private _path = _x select 2;
            private _name = if ((count _x) > 3) then { _x select 3 } else { "" };
            private _author = if ((count _x) > 4) then { _x select 4 } else { name player };
            private _grid = if ((count _x) > 8) then { _x select 8 } else { mapGridPosition player };
            // Si le « path » n’est pas utilisable, préférer le nom de fichier pour la résolution disque.
            if (!(_path isEqualType "")) then { _path = ""; };
            if (_path isEqualTo "" && {_name isEqualType ""} && {_name isNotEqualTo ""}) then {
                _path = _name;
            };
            [_path, _name, _grid, _author] call _push;
        } forEach _records;
    };
};

// 2) Sélection courante Photo Library (si présente)
if (!isNil "Iceman_fnc_photo_getSelectedRecord") then {
    private _sel = call Iceman_fnc_photo_getSelectedRecord;
    if ((_sel isEqualType []) && {(count _sel) > 3}) then {
        private _src = if ((count _sel) > 1) then { _sel select 1 } else { "local" };
        if (_src isNotEqualTo "received") then {
            private _path = _sel select 2;
            private _name = _sel select 3;
            private _author = if ((count _sel) > 4) then { _sel select 4 } else { name player };
            private _grid = if ((count _sel) > 8) then { _sel select 8 } else { mapGridPosition player };
            if (!(_path isEqualType "")) then { _path = ""; };
            if (_path isEqualTo "" && {_name isEqualType ""} && {_name isNotEqualTo ""}) then {
                _path = _name;
            };
            [_path, _name, _grid, _author] call _push;
        };
    };
};

// 3) Cache COMSPEC (Quick Pictures / envois récents)
private _cache = missionNamespace getVariable ["COMSPEC_Athena_LocalPhotos", []];
if (_cache isEqualType []) then {
    {
        if (!(_x isEqualType [])) then { continue };
        if ((count _x) < 1) then { continue };
        private _path = _x select 0;
        private _name = if ((count _x) > 1) then { _x select 1 } else { "" };
        private _grid = if ((count _x) > 2) then { _x select 2 } else { "" };
        private _author = if ((count _x) > 3) then { _x select 3 } else { name player };
        [_path, _name, _grid, _author] call _push;
    } forEach _cache;
};

reverse _out;

// 4) Captures d'écran du jeu (dossier Screenshots du profil), même hors ligne.
if (!isNil "comspec_overwatch_connect_fnc_listLocalScreenshots") then {
    private _shots = [] call comspec_overwatch_connect_fnc_listLocalScreenshots;
    if (_shots isEqualType []) then {
        {
            if (!(_x isEqualType [])) then { continue };
            [_x param [0, ""], _x param [1, ""], _x param [2, ""], _x param [3, ""]] call _push;
        } forEach _shots;
    };
};

_out
