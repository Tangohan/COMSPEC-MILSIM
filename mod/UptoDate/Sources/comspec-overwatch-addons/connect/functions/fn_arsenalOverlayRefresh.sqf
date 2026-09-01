/*
    Rafraîchit les deux listes de tenues (locales et communauté) à l’arsenal.
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};

[_display, [], ""] call comspec_overwatch_connect_fnc_arsenalOverlayPreview;

private _listLocal = _grp getVariable ["COMSPEC_ArsenalLocalList", controlNull];
private _listCloud = _grp getVariable ["COMSPEC_ArsenalList", controlNull];

if (!isNull _listLocal) then {
    lbClear _listLocal;
    private _entries = [] call comspec_overwatch_connect_fnc_arsenalLocalLoadouts;
    _grp setVariable ["COMSPEC_ArsenalLocalRows", _entries];
    {
        _x params ["_name", "_data"];
        private _idx = _listLocal lbAdd _name;
        _listLocal lbSetData [_idx, _name];
        private _icons = [_data] call comspec_overwatch_connect_fnc_arsenalLoadoutIcons;
        private _pic = "";
        {
            _x params ["", "", "_p"];
            if (_p isNotEqualTo "") exitWith { _pic = _p; };
        } forEach _icons;
        if (_pic isNotEqualTo "") then {
            _listLocal lbSetPicture [_idx, _pic];
            _listLocal lbSetPictureColor [_idx, [1, 1, 1, 1]];
        };
    } forEach _entries;
    if ((lbSize _listLocal) < 1) then {
        private _empty = _listLocal lbAdd "(aucune tenue dans cet arsenal)";
        _listLocal lbSetData [_empty, ""];
    };
};

if (isNull _listCloud) exitWith {};

lbClear _listCloud;

private _raw = ["COMSPECExtension" callExtension ["ListWardrobes", []]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {
    private _i = _listCloud lbAdd "(hors ligne — compte non relié)";
    _listCloud lbSetData [_i, ""];
};

private _body = _raw select [3];
private _lines = _body splitString endl;
if (_lines isEqualTo [] && {_body != ""}) then { _lines = [_body]; };

private _cloudMeta = [];
{
    if (_x isEqualTo "") then { continue };
    private _parts = _x splitString toString [9];
    if (count _parts < 2) then { continue };
    private _id = _parts select 0;
    private _name = _parts select 1;
    private _coll = if ((count _parts) > 3) then { _parts select 3 } else { "" };
    private _owner = if ((count _parts) > 7) then { _parts select 7 } else { "" };
    private _label = _name;
    if (_owner isNotEqualTo "") then {
        _label = format ["%1  —  %2", _name, _owner];
    };
    if (_coll isNotEqualTo "") then {
        _label = format ["%1  ·  %2", _label, _coll];
    };
    private _idx = _listCloud lbAdd _label;
    _listCloud lbSetData [_idx, _id];
    _cloudMeta pushBack [_idx, _id, _name];
} forEach _lines;

_grp setVariable ["COMSPEC_ArsenalCloudMeta", _cloudMeta];

if ((lbSize _listCloud) < 1) then {
    private _empty = _listCloud lbAdd "(aucune tenue dans la communauté)";
    _listCloud lbSetData [_empty, ""];
};

if (_cloudMeta isEqualTo []) exitWith {};

[_listCloud, _cloudMeta] spawn {
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
