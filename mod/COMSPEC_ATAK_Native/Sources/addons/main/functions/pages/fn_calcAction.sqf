/*
    Touche de la calculette. Param : touche (chiffre, opérateur, C, CE, =, neg, %).
*/
params [["_key", ""]];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_Calc", ["", "0", false]];
if !(_st isEqualType []) then { _st = ["", "0", false]; };
_st params ["_expr", "_disp", "_just"];

private _redraw = {
    [{ ["CALC"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
private _set = {
    params ["_e", "_d", ["_j", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_Calc", [_e, _d, _j]];
    call _redraw;
};

private _eval = {
    params ["_s"];
    private _t = (_s splitString " ") joinString "";
    if (_t isEqualTo "") exitWith { "0" };
    // N'accepte que chiffres, opérateurs et point.
    private _ok = true;
    { if !(_x in ["0","1","2","3","4","5","6","7","8","9",".","+","-","*","/","(",")"]) then { _ok = false; }; } forEach (_t splitString "");
    if (!_ok) exitWith { "Erreur" };
    private _r = 0;
    try {
        _r = call compile _t;
    } catch {
        _r = "Erreur";
    };
    if !(_r isEqualType 0) exitWith { "Erreur" };
    if (!finite _r) exitWith { "Erreur" };
    // Affichage propre (évite 1.0000001).
    private _out = if ((round _r) isEqualTo _r) then { str (round _r) } else {
        private _f = _r toFixed 8;
        while { (_f select [count _f - 1]) isEqualTo "0" } do { _f = _f select [0, count _f - 1]; };
        if ((_f select [count _f - 1]) isEqualTo ".") then { _f = _f select [0, count _f - 1]; };
        _f
    };
    _out
};

switch (true) do {
    case (_key isEqualTo "C"): { ["", "0", false] call _set; };
    case (_key isEqualTo "CE"): { [_expr, "0", false] call _set; };
    case (_key isEqualTo "neg"): {
        if (_disp isEqualTo "0" || {_disp isEqualTo "Erreur"}) exitWith { call _redraw; };
        if ((_disp select [0, 1]) isEqualTo "-") then {
            [_expr, _disp select [1], false] call _set;
        } else {
            [_expr, "-" + _disp, false] call _set;
        };
    };
    case (_key isEqualTo "%"): {
        private _n = parseNumber _disp;
        [_expr, str (_n / 100), false] call _set;
    };
    case (_key in ["+", "-", "*", "/"]): {
        private _op = _key;
        private _e = if (_just) then { _disp } else {
            if (_expr isEqualTo "") then { _disp } else { _expr + _disp };
        };
        [_e + _op, "0", false] call _set;
    };
    case (_key isEqualTo "="): {
        private _full = if (_expr isEqualTo "") then { _disp } else { _expr + _disp };
        private _res = [_full] call _eval;
        [_full, _res, true] call _set;
    };
    case (_key isEqualTo "."): {
        if (_just) then { ["", "0.", false] call _set; } else {
            if (("." in _disp)) exitWith { call _redraw; };
            [_expr, _disp + ".", false] call _set;
        };
    };
    default {
        // Chiffre
        if (_just) then {
            ["", _key, false] call _set;
        } else {
            private _d = if (_disp isEqualTo "0") then { _key } else { _disp + _key };
            if ((count _d) > 16) exitWith { call _redraw; };
            [_expr, _d, false] call _set;
        };
    };
};
true
