/*
    Rafraîchit les deux listes de tenues, regroupées par collection (repliables).
    Première ouverture : première collection ouverte (ou toutes si peu d’entrées).
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};

[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;

private _listLocal = _grp getVariable ["COMSPEC_ArsenalLocalList", controlNull];
private _listCloud = _grp getVariable ["COMSPEC_ArsenalList", controlNull];

private _fnc_leafLabel = {
    params ["_name", "_group"];
    private _leaf = _name;
    if (_group isNotEqualTo "Autres") then {
        private _prefix = _group + " - ";
        if ((tolower _name find toLower _prefix) == 0) then {
            _leaf = _name select [count _prefix];
        };
    };
    if (trim _leaf isEqualTo "") then { _leaf = _name };
    _leaf
};

private _fnc_initCollapsed = {
    params ["_display", "_varName", "_groups"];
    private _existing = _display getVariable [_varName, nil];
    if (!isNil "_existing" && {_existing isEqualType createHashMap}) exitWith { _existing };
    private _collapsed = createHashMap;
    private _keys = keys _groups;
    _keys sort true;
    private _total = 0;
    { _total = _total + count (_groups getOrDefault [_x, []]); } forEach _keys;
    private _openAll = (_total <= 40) && {(count _keys) <= 6};
    {
        private _open = _openAll || {_forEachIndex == 0};
        _collapsed set [_x, !_open];
    } forEach _keys;
    _display setVariable [_varName, _collapsed];
    _collapsed
};

private _fnc_fillGrouped = {
    params ["_list", "_groups", "_collapsed", "_itemKind", "_setPic"];
    private _keys = keys _groups;
    _keys sort true;
    private _anyOpen = false;
    {
        private _key = _x;
        private _items = _groups getOrDefault [_key, []];
        if (_items isEqualTo []) then { continue };
        _items = [_items, [], { toLower (_x select 0) }, "ASCEND"] call BIS_fnc_sortBy;
        private _shut = _collapsed getOrDefault [_key, true];
        if (!_shut) then { _anyOpen = true; };
        private _mark = if (_shut) then { "▸" } else { "▾" };
        private _hIdx = _list lbAdd format ["%1  %2  (%3)", _mark, _key, count _items];
        _list lbSetData [_hIdx, _key];
        _list lbSetValue [_hIdx, 0];
        _list lbSetColor [_hIdx, [0.68, 0.90, 0.78, 1]];
        _list lbSetTooltip [_hIdx, "Cliquer pour ouvrir ou fermer la collection"];
        if (_shut) then { continue };
        {
            _x params ["_label", "_data", "_picSrc"];
            private _iIdx = _list lbAdd format ["    %1", _label];
            _list lbSetData [_iIdx, _data];
            _list lbSetValue [_iIdx, _itemKind];
            _list lbSetColor [_iIdx, [0.88, 0.92, 0.90, 1]];
            if (_setPic && {_picSrc isNotEqualTo []}) then {
                private _icons = [_picSrc] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
                private _pic = "";
                {
                    _x params ["", "", "_p"];
                    if (_p isNotEqualTo "") exitWith { _pic = _p; };
                } forEach _icons;
                if (_pic isNotEqualTo "") then {
                    _list lbSetPicture [_iIdx, _pic];
                    _list lbSetPictureColor [_iIdx, [1, 1, 1, 1]];
                };
            };
        } forEach _items;
    } forEach _keys;
    _anyOpen
};

private _fnc_setHint = _grp getVariable ["COMSPEC_ArsenalSetHint", {}];

if (!isNull _listLocal) then {
    lbClear _listLocal;
    private _entries = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
    private _localMap = createHashMap;
    private _groups = createHashMap;
    {
        _x params ["_name", "_data"];
        _localMap set [_name, _data];
        private _g = [_name] call comspec_overwatch_connect_fnc_arsenalCollectionName;
        private _bucket = _groups getOrDefault [_g, []];
        if (_bucket isEqualTo []) then {
            _bucket = [];
            _groups set [_g, _bucket];
        };
        private _leaf = [_name, _g] call _fnc_leafLabel;
        _bucket pushBack [_leaf, _name, _data];
    } forEach _entries;
    _grp setVariable ["COMSPEC_ArsenalLocalRows", _entries];
    _grp setVariable ["COMSPEC_ArsenalLocalMap", _localMap];
    private _collapsed = [_display, "COMSPEC_ArsenalCollapsedLocal", _groups] call _fnc_initCollapsed;
    private _anyOpen = [_listLocal, _groups, _collapsed, 1, true] call _fnc_fillGrouped;
    if ((lbSize _listLocal) < 1) then {
        private _empty = _listLocal lbAdd "(aucune tenue dans cet arsenal)";
        _listLocal lbSetData [_empty, ""];
        _listLocal lbSetValue [_empty, -1];
    } else {
        if (!_anyOpen && {_fnc_setHint isEqualType {}}) then {
            [_grp, "Cliquez une collection (▸) pour afficher les tenues."] call _fnc_setHint;
        };
    };
};

if (isNull _listCloud) exitWith {};

lbClear _listCloud;

private _lines = [] call comspec_overwatch_connect_fnc_arsenalListWardrobes;
if (_lines isEqualTo []) exitWith {
    private _probe = ["COMSPECExtension" callExtension ["ListWardrobes", ["0"]]] call comspec_overwatch_connect_fnc_extResult;
    private _msg = if (!(_probe isEqualType "") || {_probe find "OK|" != 0}) then {
        "(hors ligne — compte non relié)"
    } else {
        "(aucune tenue dans la communauté)"
    };
    private _i = _listCloud lbAdd _msg;
    _listCloud lbSetData [_i, ""];
    _listCloud lbSetValue [_i, -1];
};

private _groups = createHashMap;
private _cloudMeta = [];
{
    if (_x isEqualTo "") then { continue };
    private _parts = _x splitString toString [9];
    if (count _parts < 2) then { continue };
    private _id = _parts select 0;
    private _name = _parts select 1;
    private _coll = if ((count _parts) > 3) then { _parts select 3 } else { "" };
    private _owner = if ((count _parts) > 7) then { _parts select 7 } else { "" };
    private _g = [_name, _coll] call comspec_overwatch_connect_fnc_arsenalCollectionName;
    private _leaf = [_name, _g] call _fnc_leafLabel;
    if (_owner isNotEqualTo "") then {
        _leaf = format ["%1  —  %2", _leaf, _owner];
    };
    private _bucket = _groups getOrDefault [_g, []];
    if (_bucket isEqualTo []) then {
        _bucket = [];
        _groups set [_g, _bucket];
    };
    _bucket pushBack [_leaf, _id, []];
    _cloudMeta pushBack [_id, _name, _g];
} forEach _lines;

_grp setVariable ["COMSPEC_ArsenalCloudMeta", _cloudMeta];
private _collapsed = [_display, "COMSPEC_ArsenalCollapsedCloud", _groups] call _fnc_initCollapsed;
private _anyOpenCloud = [_listCloud, _groups, _collapsed, 2, false] call _fnc_fillGrouped;

if ((lbSize _listCloud) < 1) then {
    private _empty = _listCloud lbAdd "(aucune tenue dans la communauté)";
    _listCloud lbSetData [_empty, ""];
    _listCloud lbSetValue [_empty, -1];
} else {
    if (!_anyOpenCloud && {_fnc_setHint isEqualType {}}) then {
        [_grp, "Cliquez une collection (▸) pour afficher les tenues de la communauté."] call _fnc_setHint;
    };
};

private _visibleIds = [];
for "_i" from 0 to ((lbSize _listCloud) - 1) do {
    if ((_listCloud lbValue _i) == 2) then {
        _visibleIds pushBack [_i, _listCloud lbData _i];
    };
};
if (_visibleIds isEqualTo []) exitWith {};

[_listCloud, _visibleIds] spawn {
    params ["_list", "_rows"];
    {
        if (isNull _list) exitWith {};
        _x params ["_idx", "_id"];
        private _loadout = [_id] call comspec_overwatch_connect_fnc_arsenalCloudLoadout;
        if (_loadout isEqualTo []) then { continue };
        private _icons = [_loadout] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
        private _pic = "";
        {
            _x params ["", "", "_p"];
            if (_p isNotEqualTo "") exitWith { _pic = _p; };
        } forEach _icons;
        if (_pic isNotEqualTo "") then {
            _list lbSetPicture [_idx, _pic];
            _list lbSetPictureColor [_idx, [1, 1, 1, 1]];
        };
        if (_forEachIndex >= 29) exitWith {};
        uiSleep 0.03;
    } forEach _rows;
};
