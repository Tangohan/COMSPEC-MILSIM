/*
    Recharge les données du panneau Tenues Athena puis redessine la liste (arsenalOverlayFill).
    Mes tenues : profil ACE local. Communauté : ListWardrobes (cache 90 s, forcé après un envoi).
    Les champs vides de ListWardrobes sont conservés (splitString les fusionnait : la collection
    vide décalait les colonnes et le drapeau favori « 0 » devenait un nom de collection).
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};

private _split = {
    params ["_s"];
    private _tab = toString [9];
    private _out = [];
    private _i = _s find _tab;
    while { _i >= 0 } do { _out pushBack (_s select [0, _i]); _s = _s select [_i + 1]; _i = _s find _tab; };
    _out pushBack _s;
    _out apply { trim _x }
};
private _leafOf = {
    params ["_name", "_coll"];
    private _p = _coll + " - ";
    private _leaf = if (_coll isNotEqualTo "Autres" && {((toLower _name) find (toLower _p)) isEqualTo 0}) then { _name select [count _p] } else { _name };
    if ((trim _leaf) isEqualTo "") then { _name } else { _leaf }
};

// Mes tenues.
private _local = [];
{
    _x params ["_name", "_data"];
    private _c = [_name] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    _local pushBack [_name, [_name, _c] call _leafOf, _c, _data];
} forEach ([] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts);
_grp setVariable ["COMSPEC_ArsenalLocalRows", _local];

// Communauté.
private _lines = missionNamespace getVariable ["COMSPEC_ArsenalWardrobeLines", []];
private _cacheAt = missionNamespace getVariable ["COMSPEC_ArsenalWardrobeAt", -1e9];
if ((_display getVariable ["COMSPEC_ArsenalForceList", false]) || {!(_lines isEqualType [])} || {_lines isEqualTo []} || {(diag_tickTime - _cacheAt) > 90}) then {
    _lines = [] call comspec_overwatch_connect_fnc_arsenalListWardrobes;
    missionNamespace setVariable ["COMSPEC_ArsenalWardrobeLines", _lines, false];
    missionNamespace setVariable ["COMSPEC_ArsenalWardrobeAt", diag_tickTime, false];
    _display setVariable ["COMSPEC_ArsenalForceList", false];
};
private _cloud = [];
{
    if (_x isEqualTo "") then { continue };
    ([_x] call _split) params [["_id", ""], ["_name", ""], ["", ""], ["_coll", ""], ["_fav", "0"], ["_bytes", "0"], ["_upd", ""], ["_owner", ""]];
    if (_id isEqualTo "" || {_name isEqualTo ""}) then { continue };
    private _c = [_name, _coll] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    _cloud pushBack [_id, _name, [_name, _c] call _leafOf, _c, _owner, _upd, _fav isEqualTo "1", parseNumber _bytes];
} forEach _lines;
_grp setVariable ["COMSPEC_ArsenalCloudRows", _cloud];
_grp setVariable ["COMSPEC_ArsenalCloudMeta", _cloud apply { [_x select 0, _x select 1, _x select 3] }];
private _offline = false;
if (_lines isEqualTo []) then {
    private _probe = ["COMSPECExtension" callExtension ["ListWardrobes", ["0"]]] call comspec_overwatch_connect_fnc_extResult;
    _offline = !(_probe isEqualType "") || {(_probe find "OK|") != 0};
};
_grp setVariable ["COMSPEC_ArsenalOffline", _offline];
_grp setVariable ["COMSPEC_ArsenalLoadedAt", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
[_display] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
