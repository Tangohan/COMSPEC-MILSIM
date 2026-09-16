/*
    Rendu IceMan / ATAK : RscStructuredText + parseText.
    Même échappement que Iceman group messages (& < >) si le texte n’est pas déjà du <t>.
    Jamais si le cadre a une largeur ou une hauteur nulle (planteur).
    [_ctrl, _texte] call comspec_overwatch_connect_fnc_setPlainText
*/
params ["_c", "_raw", ["_color", []]];
if (isNull _c) exitWith { false };

private _pos = ctrlPosition _c;
private _w = _pos param [2, 0];
private _h = _pos param [3, 0];
if (!(_w isEqualType 0) || {_w != _w} || {_w < 0.02}) exitWith { false };
if (!(_h isEqualType 0) || {_h != _h} || {_h < 0.008}) exitWith { false };

private _s = if (_raw isEqualType "") then { _raw } else { str _raw };
if ((count _s) > 4000) then { _s = _s select [0, 4000]; };

private _hasMarkup = ((_s find "<t") >= 0) || {(_s find "<br") >= 0} || {(_s find "<img") >= 0};
if (!_hasMarkup && {_s isNotEqualTo ""}) then {
    private _out = [];
    {
        switch (_x) do {
            case 38: { _out append (toArray "&amp;") };
            case 60: { _out append (toArray "&lt;") };
            case 62: { _out append (toArray "&gt;") };
            default { _out pushBack _x };
        };
    } forEach toArray _s;
    _s = toString _out;
};

isNil {
    _c ctrlSetStructuredText parseText _s;
};
if ((_color isEqualType []) && {(count _color) >= 3} && {!_hasMarkup}) then {
    private _a = if ((count _color) > 3) then { _color select 3 } else { 1 };
    _c ctrlSetTextColor [_color select 0, _color select 1, _color select 2, _a];
};
true
