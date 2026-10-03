/*
    Groupe : membres de mon groupe (rôle, état, distance) puis les autres groupes de mon camp.
    Reprend la liste de groupe de BCE (Aaren, APL-SA). Double-clic : centre la carte sur l'unité.
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";

private _list = ["COMSPEC_RscListBox", [_pad, _pad, _bw - 2 * _pad, _bh - 2 * _pad]] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _font;
private _rows = [];
private _dim = [0.58, 0.64, 0.60, 1];

private _header = {
    params ["_text"];
    private _i = _list lbAdd _text;
    _list lbSetColor [_i, [0.36, 0.78, 0.42, 1]];
    _rows pushBack objNull;
};

private _members = units group player;
[format ["%1 · %2 membre(s)", toUpper groupId group player, count _members]] call _header;
{
    private _u = _x;
    private _role = getText (configOf _u >> "displayName");
    private _i = _list lbAdd format ["%1   %2", name _u, _role];
    _list lbSetPicture [_i, [_u, "texture"] call BIS_fnc_rankParams];
    _list lbSetPictureColor [_i, [0.86, 0.90, 0.88, 1]];
    _list lbSetTextRight [_i, if (_u isEqualTo player) then { "moi" } else { format ["%1 m", round (_u distance2D player)] }];
    private _state = switch (true) do {
        case (!alive _u): { [[0.88, 0.25, 0.22, 1], "Mort"] };
        case (lifeState _u isEqualTo "INCAPACITATED"): { [[0.88, 0.25, 0.22, 1], "Inconscient"] };
        case ((damage _u) > 0.25): { [[0.95, 0.67, 0.20, 1], "Blessé"] };
        default { [[0.90, 0.94, 0.91, 1], ""] };
    };
    _list lbSetColor [_i, _state select 0];
    if ((_state select 1) isNotEqualTo "") then { _list lbSetTooltip [_i, _state select 1]; };
    _rows pushBack _u;
} forEach _members;

private _others = allGroups select { side _x isEqualTo side group player && {_x isNotEqualTo group player} && {(count units _x) > 0} && {isPlayer leader _x || {(units _x) findIf { isPlayer _x } >= 0}} };
if ((count _others) > 0) then {
    [""] call _header;
    ["AUTRES GROUPES"] call _header;
    {
        private _lead = leader _x;
        private _i = _list lbAdd format ["%1   %2", groupId _x, name _lead];
        _list lbSetTextRight [_i, format ["%1 · %2 m", count units _x, round (_lead distance2D player)]];
        _list lbSetColor [_i, _dim];
        _rows pushBack _lead;
    } forEach _others;
};

uiNamespace setVariable ["COMSPEC_ATAK_GroupRows", _rows];
_list ctrlAddEventHandler ["LBDblClick", {
    params ["", "_index"];
    private _u = (uiNamespace getVariable ["COMSPEC_ATAK_GroupRows", []]) param [_index, objNull];
    if (isNull _u) exitWith {};
    [{
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [_this, 0.03] call comspec_atak_native_fnc_mapCenter;
    }, _u] call CBA_fnc_execNextFrame;
}];
true
