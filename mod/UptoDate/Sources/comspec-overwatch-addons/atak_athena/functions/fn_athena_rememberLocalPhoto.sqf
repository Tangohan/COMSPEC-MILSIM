/*
    Mémorise une capture locale pour le sélecteur Athena (onglet Photos).
    Params: [_filePath, _fileName]
*/
params [["_filePath", "", [""]], ["_fileName", "", [""]]];
if (!hasInterface) exitWith {};
_filePath = trim _filePath;
if (_filePath isEqualTo "") exitWith {};
// Retirer guillemets éventuels (EH / bibliothèque).
private _n = count _filePath;
if (_n >= 2) then {
    private _a = _filePath select [0, 1];
    private _b = _filePath select [_n - 1, 1];
    private _dq = toString [34];
    if ((_a isEqualTo _dq && {_b isEqualTo _dq}) || {_a isEqualTo "'" && {_b isEqualTo "'"}}) then {
        _filePath = trim (_filePath select [1, _n - 2]);
    };
};
_filePath = (_filePath splitString "/") joinString "\\";
if (_fileName isEqualTo "") then {
    private _parts = _filePath splitString "\/";
    _fileName = if ((count _parts) > 0) then { _parts select ((count _parts) - 1) } else { _filePath };
};

private _grid = mapGridPosition player;
private _author = name player;
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isNotEqualTo "") then { _author = _cs; };
};

private _cache = missionNamespace getVariable ["COMSPEC_Athena_LocalPhotos", []];
if (!(_cache isEqualType [])) then { _cache = []; };

private _key = toLower _filePath;
private _filtered = [];
{
    if (!(_x isEqualType [])) then { continue };
    if ((count _x) < 1) then { continue };
    if ((toLower (_x select 0)) isEqualTo _key) then { continue };
    _filtered pushBack _x;
} forEach _cache;

_filtered pushBack [_filePath, _fileName, _grid, _author, diag_tickTime];
while { (count _filtered) > 30 } do { _filtered deleteAt 0; };
missionNamespace setVariable ["COMSPEC_Athena_LocalPhotos", _filtered, false];
