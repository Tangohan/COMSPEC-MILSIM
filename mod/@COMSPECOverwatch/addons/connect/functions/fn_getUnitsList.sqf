/*
    Effectifs pour tablette / roster : fusion joueurs locaux (SQF) + Athena (GetUnits).
    Retourne : [[callsign, gx, gy, isSelf, worldX, worldY, role], ...] trié par callsign.
    - Toujours au moins le joueur local s’il a une interface.
    - Ignore les fantômes Athena en (0,0) sauf s’ils correspondent au joueur local.
*/
if (!hasInterface) exitWith { [] };

private _parseGrid = {
    params [["_gridStr", ""]];
    if (!(_gridStr isEqualType "")) then { _gridStr = str _gridStr; };
    _gridStr = trim _gridStr;
    private _gx = 0;
    private _gy = 0;
    if (_gridStr isEqualTo "") exitWith { [0, 0] };
    private _parts = _gridStr splitString " ";
    if ((count _parts) >= 2) then {
        _gx = parseNumber (_parts select 0);
        _gy = parseNumber (_parts select 1);
    } else {
        private _len = count _gridStr;
        private _half = floor (_len / 2);
        if (_half > 0) then {
            _gx = parseNumber (_gridStr select [0, _half]);
            _gy = parseNumber (_gridStr select [_half, _len - _half]);
        };
    };
    [_gx, _gy]
};

private _callsignOf = {
    params ["_unit"];
    if (isNull _unit) exitWith { "Operateur" };
    if (_unit isEqualTo player) exitWith { [] call comspec_overwatch_connect_fnc_getCallsign };
    private _cs = _unit getVariable ["COMSPEC_Callsign", ""];
    if (!(_cs isEqualType "")) then { _cs = str _cs; };
    _cs = trim _cs;
    if (_cs isEqualTo "" || {(toLower _cs) in ["unknown", "inconnu"]}) then {
        _cs = trim (name _unit);
    };
    if (_cs isEqualTo "") then { _cs = "Operateur"; };
    _cs
};

private _byCs = createHashMap;
private _myCs = [] call comspec_overwatch_connect_fnc_getCallsign;
private _myPos = getPosWorld player;
private _myGrid = [mapGridPosition player] call _parseGrid;
private _myRole = [player] call comspec_overwatch_connect_fnc_getUnitRole;

// 1) Joueurs locaux (même camp en priorité, sinon tous)
private _pool = allPlayers select { alive _x && {!isNull _x} };
if ((count _pool) == 0) then { _pool = [player]; };
{
    private _u = _x;
    private _cs = [_u] call _callsignOf;
    private _gridStr = [_u] call comspec_overwatch_connect_fnc_gridPosition;
    if (_gridStr isEqualTo "") then { _gridStr = mapGridPosition _u; };
    private _grid = [_gridStr] call _parseGrid;
    private _pos = getPosWorld _u;
    private _isSelf = _u isEqualTo player;
    private _role = [_u] call comspec_overwatch_connect_fnc_getUnitRole;
    _byCs set [toLower _cs, [_cs, _grid select 0, _grid select 1, _isSelf, _pos select 0, _pos select 1, _role]];
} forEach _pool;

// Garantie joueur local (indicatif Athena)
_byCs set [toLower _myCs, [_myCs, _myGrid select 0, _myGrid select 1, true, _myPos select 0, _myPos select 1, _myRole]];

// 2) Athena via extension (complète / met à jour les absents locaux)
private _raw = ["COMSPECExtension" callExtension "GetUnits"] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString "|";
private _prefix = if ((count _parts) >= 1) then { _parts select 0 } else { "" };
if (_prefix isEqualTo "OK") then {
    private _payload = if ((count _parts) >= 2) then { _parts select 1 } else { "" };
    {
        private _cols = _x splitString toString [9]; // tab
        if ((count _cols) >= 4 && {(_cols select 0) isEqualTo "U"}) then {
            private _cs = trim (_cols select 1);
            if (_cs isEqualTo "") then { continue };
            private _gx = parseNumber (_cols select 2);
            private _gy = parseNumber (_cols select 3);
            private _key = toLower _cs;
            private _isSelf = _key isEqualTo (toLower _myCs);
            // Fantôme (0,0) : ignorer sauf soi-même
            if (!_isSelf && {_gx == 0} && {_gy == 0}) then { continue };
            private _roleAthena = if ((count _cols) >= 5) then { trim (_cols select 4) } else { "" };
            private _existing = _byCs getOrDefault [_key, []];
            if ((count _existing) >= 7) then {
                private _roleKeep = _existing select 6;
                if (_roleKeep isEqualTo "" || {_roleKeep isEqualTo "Opérateur"}) then {
                    if (!(_roleAthena isEqualTo "")) then { _roleKeep = _roleAthena; };
                };
                _byCs set [_key, [_cs, _gx, _gy, _isSelf || (_existing select 3), _existing select 4, _existing select 5, _roleKeep]];
            } else {
                private _wx = (_myPos select 0) + (_gx - (_myGrid select 0)) * 10;
                private _wy = (_myPos select 1) + (_gy - (_myGrid select 1)) * 10;
                private _roleUse = if (!(_roleAthena isEqualTo "")) then { _roleAthena } else { "Opérateur" };
                if (_isSelf) then { _roleUse = _myRole; };
                _byCs set [_key, [_cs, _gx, _gy, _isSelf, _wx, _wy, _roleUse]];
            };
        };
    } forEach (_payload splitString toString [10]);
};

private _rows = values _byCs;
_rows sort true;
_rows
