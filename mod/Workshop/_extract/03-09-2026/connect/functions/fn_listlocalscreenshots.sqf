/*
    Liste les captures d'écran déjà présentes sur le poste (dossier Screenshots
    du profil Arma). Hors ligne, sans liaison Athena.

    Retour : [path, fileName, grid, author][]
*/
if (!hasInterface) exitWith { [] };

private _out = [];
private _raw = ["COMSPECExtension" callExtension ["ListLocalScreenshots", ["24"]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { [] };
if ((_raw find "OK|") != 0) exitWith { [] };

private _body = _raw select [3];
_body = (_body splitString (toString [13])) joinString "";
private _grid = mapGridPosition player;
private _author = name player;
private _seen = [];

{
    if (_x isEqualTo "") then { continue };
    private _cols = _x splitString (toString [9]);
    if ((count _cols) < 2) then { continue };
    private _name = trim (_cols select 0);
    private _path = trim (_cols select 1);
    if (_path isEqualTo "") then { continue };
    private _key = toLower _path;
    if (_key in _seen) then { continue };
    _seen pushBack _key;
    private _nameKey = toLower _name;
    if (_nameKey isNotEqualTo "" && {_nameKey in _seen}) then { continue };
    if (_nameKey isNotEqualTo "") then { _seen pushBack _nameKey; };
    _out pushBack [_path, _name, _grid, _author];
} forEach (_body splitString (toString [10]));

_out
