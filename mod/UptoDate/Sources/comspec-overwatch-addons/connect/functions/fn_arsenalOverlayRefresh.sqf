/*
    Rafraîchit la liste cloud du panneau Athena (ACE Arsenal).
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
private _list = _grp getVariable ["COMSPEC_ArsenalList", controlNull];
if (isNull _list) exitWith {};

lbClear _list;

private _raw = ["COMSPECExtension" callExtension ["ListWardrobes", []]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {
    private _i = _list lbAdd "(hors ligne / non lié)";
    _list lbSetData [_i, ""];
};

private _body = _raw select [3];
private _lines = _body splitString endl;
if (_lines isEqualTo [] && {_body != ""}) then { _lines = [_body]; };

{
    if (_x isEqualTo "") then { continue };
    private _parts = _x splitString toString [9];
    if (count _parts < 2) then { continue };
    private _id = _parts select 0;
    private _name = _parts select 1;
    private _coll = if (count _parts > 3) then { _parts select 3 } else { "" };
    private _label = if (_coll isEqualTo "") then { _name } else { format ["%1 · %2", _name, _coll] };
    private _idx = _list lbAdd _label;
    _list lbSetData [_idx, _id];
} forEach _lines;

if ((lbSize _list) < 1) then {
    private _empty = _list lbAdd "(aucune wardrobe cloud)";
    _list lbSetData [_empty, ""];
};
