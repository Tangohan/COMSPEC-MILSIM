/*
    Emport et capteurs d’un aéronef (pylônes, tourelles, armes).
    Retour : HashMap { ordnance, sensors, raw }
*/
params [["_veh", objNull, [objNull]]];

private _out = createHashMapFromArray [
    ["ordnance", ""],
    ["sensors", ""],
    ["raw", []]
];
if (isNull _veh) exitWith { _out };
if (_veh isKindOf "Man") exitWith { _out };

private _fncLabel = {
    params ["_cls", "_count"];
    if (!(_cls isEqualType "") || {_cls isEqualTo ""}) exitWith { "" };
    private _dn = getText (configFile >> "CfgMagazines" >> _cls >> "displayName");
    if (_dn isEqualTo "") then {
        _dn = getText (configFile >> "CfgWeapons" >> _cls >> "displayName");
    };
    if (_dn isEqualTo "") then { _dn = _cls; };
    _dn = trim _dn;
    if (_dn isEqualTo "") exitWith { "" };
    private _low = toLower _dn;
    if (_low in ["", "empty", "vide", "none", "n/a"]) exitWith { "" };
    if ((_low find "empty") >= 0 && {(_low find "pylon") >= 0}) exitWith { "" };
    if (_count isEqualType 0 && {_count > 1}) then {
        format ["%1 × %2", _count toFixed 0, _dn]
    } else {
        _dn
    }
};

private _fncKind = {
    params ["_cls", "_dn"];
    private _blob = toLower (_cls + " " + _dn);
    if (
        (_blob find "laser") >= 0
        || {(_blob find "flir") >= 0}
        || {(_blob find "atflir") >= 0}
        || {(_blob find "tgp") >= 0}
        || {(_blob find "litening") >= 0}
        || {(_blob find "damocles") >= 0}
        || {(_blob find "sniper") >= 0}
        || {(_blob find "designat") >= 0}
        || {(_blob find "camera") >= 0}
        || {(_blob find "targeting") >= 0}
        || {(_blob find "dagr") >= 0}
        || {(_blob find "lantirn") >= 0}
        || {(_blob find "pod") >= 0 && {(_blob find "pylon") < 0}}
    ) exitWith { "sensor" };
    if (
        (_blob find "flare") >= 0
        || {(_blob find "chaff") >= 0}
        || {(_blob find "smoke") >= 0}
        || {(_blob find "cmflare") >= 0}
        || {(_blob find "countermeas") >= 0}
    ) exitWith { "skip" };
    "ordnance"
};

private _ordMap = createHashMap;
private _senMap = createHashMap;

private _fncAdd = {
    params ["_cls", "_n", "_kindHint"];
    if (!(_cls isEqualType "") || {_cls isEqualTo ""}) exitWith {};
    if (!(_n isEqualType 0)) then { _n = 1; };
    if (_n < 1) then { _n = 1; };
    private _dn = getText (configFile >> "CfgMagazines" >> _cls >> "displayName");
    if (_dn isEqualTo "") then {
        _dn = getText (configFile >> "CfgWeapons" >> _cls >> "displayName");
    };
    if (_dn isEqualTo "") then { _dn = _cls; };
    private _kind = [_cls, _dn] call _fncKind;
    if (_kind isEqualTo "skip") exitWith {};
    if (_kindHint isEqualTo "sensor") then { _kind = "sensor"; };
    private _bucket = if (_kind isEqualTo "sensor") then { _senMap } else { _ordMap };
    private _prev = _bucket getOrDefault [_cls, 0];
    _bucket set [_cls, _prev + _n];
};

{
    if (!(_x isEqualType []) || {(count _x) < 4}) then { continue };
    private _mag = _x select 3;
    private _ammo = if ((count _x) > 4 && {(_x select 4) isEqualType 0}) then { _x select 4 } else { 1 };
    if (!(_mag isEqualType "") || {_mag isEqualTo ""}) then { continue };
    if (_ammo isEqualType 0 && {_ammo <= 0}) then { continue };
    [_mag, 1, ""] call _fncAdd;
} forEach (getAllPylonsInfo _veh);

{
    if (!(_x isEqualType []) || {(count _x) < 1}) then { continue };
    private _mag = _x select 0;
    private _ammo = if ((count _x) > 2 && {(_x select 2) isEqualType 0}) then { _x select 2 } else { 1 };
    if (!(_mag isEqualType "") || {_mag isEqualTo ""}) then { continue };
    if (_ammo isEqualType 0 && {_ammo <= 0}) then { continue };
    [_mag, (round _ammo) max 1, ""] call _fncAdd;
} forEach (magazinesAllTurrets _veh);

{
    if (!(_x isEqualType "") || {_x isEqualTo ""}) then { continue };
    private _dn = getText (configFile >> "CfgWeapons" >> _x >> "displayName");
    if (_dn isEqualTo "") then { _dn = _x; };
    private _kind = [_x, _dn] call _fncKind;
    if (_kind isEqualTo "sensor") then {
        [_x, 1, "sensor"] call _fncAdd;
    };
} forEach (weapons _veh);

private _ordBits = [];
{
    private _n = _ordMap get _x;
    private _lbl = [_x, _n] call _fncLabel;
    if (_lbl isNotEqualTo "") then { _ordBits pushBack _lbl; };
} forEach (keys _ordMap);

private _senBits = [];
{
    private _lbl = [_x, 1] call _fncLabel;
    if (_lbl isNotEqualTo "") then { _senBits pushBackUnique _lbl; };
} forEach (keys _senMap);

_out set ["ordnance", _ordBits joinString " · "];
_out set ["sensors", _senBits joinString " · "];
_out set ["raw", [_ordBits, _senBits]];
_out
