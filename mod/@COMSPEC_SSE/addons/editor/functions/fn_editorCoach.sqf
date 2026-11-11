/*
    Visite guidée de l'éditeur SSE (client) : une bulle avec une pointe, posée À CÔTÉ de ce qu'elle explique, et un cadre
    vert autour de la cible. Pas de voile, la cible n'est jamais couverte ; trois étapes au plus. Contrôles : coach.hpp.
    ["start", [étapes, variable du profil, forcer]]  étapes : [[titre, texte, [x, y, l, h] en cases de la grille]…]
                                                     sans « forcer », rien si la variable est vraie (déjà vue)
    ["next"]   étape suivante ; après la dernière : vue (variable du profil vraie), bulle fermée
    ["skip"]   « Passer » : fermée pour cette fois (revient à la prochaine ouverture)
    ["never"]  « Ne plus afficher » : variable du profil vraie, fermée ; « ? » la rouvre
    ["stop"]   fermée sans rien noter (changement de page, fermeture)
    ["show"]   redessine l'étape courante · ["open"] -> vrai si une bulle est ouverte
    Placement : à droite de la cible, sinon à gauche, en dessous, au-dessus ; toujours dans la fenêtre (40 x 25 cases).
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_editorDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_coach", createHashMap];
uiNamespace setVariable ["comspec_sse_coach", _st];
private _IDCS = [95900, 95901, 95902, 95903, 95904, 95905, 95906, 95907, 95908, 95909, 95910];
private _hide = {
    if (isNull _d) exitWith {};
    { private _c = _d displayCtrl _x; _c ctrlShow false; _c ctrlEnable false; _c ctrlSetPosition [-9, -9, 0.01, 0.01]; _c ctrlCommit 0; } forEach _IDCS;
    _st set ["on", false];
};
private _done = {
    profileNamespace setVariable [_st getOrDefault ["flag", "comspec_sse_editorGuided"], true];
    saveProfileNamespace;
    call _hide;
};
if (_op isEqualTo "open") exitWith { _st getOrDefault ["on", false] };
switch (_op) do {
    case "start": {
        _a params [["_steps", []], ["_flag", ""], ["_force", false]];
        if (isNull _d || {_steps isEqualTo []}) exitWith { false };
        if (!_force && {_flag isNotEqualTo ""} && {profileNamespace getVariable [_flag, false]}) exitWith { call _hide; false };
        _st set ["steps", _steps select [0, 3]];
        _st set ["flag", _flag];
        _st set ["i", 0];
        _st set ["on", true];
        ["show"] call comspec_sse_fnc_editorCoach;
        true
    };
    case "next": {
        private _i = (_st getOrDefault ["i", 0]) + 1;
        if (_i >= (count (_st getOrDefault ["steps", []]))) exitWith { call _done; };
        _st set ["i", _i];
        ["show"] call comspec_sse_fnc_editorCoach;
    };
    case "skip";
    case "stop": { call _hide; };
    case "never": { call _done; };
    case "show": {
        if (isNull _d || {!(_st getOrDefault ["on", false])}) exitWith {};
        private _steps = _st getOrDefault ["steps", []];
        private _i = _st getOrDefault ["i", 0];
        (_steps param [_i, []]) params [["_title", ""], ["_body", ""], ["_rect", [0, 0, 40, 25]]];
        ([] call comspec_sse_fnc_uiGrid) params ["_gx", "_gy", "_gw", "_gh"];
        private _scr = { params ["_x", "_y", "_w", "_h"]; [_gx + _x * _gw, _gy + _y * _gh, _w * _gw, _h * _gh] };
        private _set = { params ["_idc", "_r"]; private _c = _d displayCtrl _idc; _c ctrlShow true; _c ctrlEnable true; _c ctrlSetPosition (_r call _scr); _c ctrlCommit 0; _c };
        private _esc = { private _s = _this; { _s = [_s, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _s };
        _rect params ["_tx", "_ty", "_tw", "_th"];
        // Texte mesuré à la largeur de la bulle, puis hauteur de la bulle.
        private _bw = 13.9;
        private _txt = [95907, [-30, -30, 13.3, 20]] call _set;
        _txt ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' size='1.15'>%1</t><br/>%2  <t color='#9fb0a8'>(%3/%4)</t>", _title call _esc, _body call _esc, _i + 1, count _steps];
        private _textH = ((ctrlTextHeight _txt) / _gh) + 0.1;
        private _bh = 0.4 + _textH + 0.3 + 1.1 + 0.3;
        // Placement : à côté de la cible (0.7 case pour la pointe), dans la fenêtre.
        private _gap = 0.7;
        private _fitsX = { params ["_x"]; _x >= 0.2 && {(_x + _bw) <= 39.8} };
        private _fitsY = { params ["_y"]; _y >= 0.2 && {(_y + _bh) <= 24.8} };
        private _clampX = { params ["_x"]; (_x max 0.2) min (39.8 - _bw) };
        private _clampY = { params ["_y"]; (_y max 0.2) min (24.8 - _bh) };
        private _cyT = _ty + _th / 2;
        private _cxT = _tx + _tw / 2;
        private _cands = [
            ["right", _tx + _tw + _gap, [_cyT - _bh / 2] call _clampY],
            ["left", _tx - _gap - _bw, [_cyT - _bh / 2] call _clampY],
            ["below", [_cxT - _bw / 2] call _clampX, _ty + _th + _gap],
            ["above", [_cxT - _bw / 2] call _clampX, _ty - _gap - _bh]
        ];
        private _pick = _cands findIf { _x params ["", "_x", "_y"]; ([_x] call _fitsX) && {[_y] call _fitsY} };
        (_cands param [_pick, ["corner", 0.3, 24.8 - _bh]]) params ["_where", "_bx", "_by"];
        // Cadre vert autour de la cible (il l'entoure, il ne la couvre pas).
        [95900, [_tx - 0.15, _ty - 0.15, _tw + 0.3, 0.1]] call _set;
        [95901, [_tx - 0.15, _ty + _th + 0.05, _tw + 0.3, 0.1]] call _set;
        [95902, [_tx - 0.15, _ty - 0.15, 0.1, _th + 0.3]] call _set;
        [95903, [_tx + _tw + 0.05, _ty - 0.15, 0.1, _th + 0.3]] call _set;
        // Pointe : carré tourné, centré sur le bord de la bulle qui fait face à la cible.
        private _s = 0.9;
        private _ac = switch (_where) do {
            case "right": { [_bx, (_cyT max (_by + 0.7)) min (_by + _bh - 0.7)] };
            case "left": { [_bx + _bw, (_cyT max (_by + 0.7)) min (_by + _bh - 0.7)] };
            case "below": { [(_cxT max (_bx + 0.7)) min (_bx + _bw - 0.7), _by] };
            case "above": { [(_cxT max (_bx + 0.7)) min (_bx + _bw - 0.7), _by + _bh] };
            default { [-30, -30] };
        };
        private _arrow = [95904, [(_ac select 0) - _s / 2, (_ac select 1) - _s / 2, _s, _s]] call _set;
        _arrow ctrlSetAngle [45, 0.5, 0.5];
        [95905, [_bx, _by, _bw, _bh]] call _set;
        [95906, [_bx, _by, _bw, 0.12]] call _set;
        [95907, [_bx + 0.3, _by + 0.4, 13.3, _textH]] call _set;
        private _yb = _by + _bh - 0.3 - 1.1;
        private _last = _i >= ((count _steps) - 1);
        private _next = [95908, [_bx + 0.3, _yb, 3.7, 1.1]] call _set;
        _next ctrlSetText (localize (["STR_COMSPEC_SSE_AZ_CoNext", "STR_COMSPEC_SSE_AZ_CoDone"] select _last));
        [95909, [_bx + 4.15, _yb, 2.9, 1.1]] call _set;
        [95910, [_bx + 7.2, _yb, 6.4, 1.1]] call _set;
        // Libellés : police réduite au besoin, jamais sous la taille minimale (0.78 case).
        {
            private _c = _d displayCtrl _x;
            private _base = _c getVariable ["comspec_sse_fh0", -1];
            if (_base < 0) then { _base = ctrlFontHeight _c; _c setVariable ["comspec_sse_fh0", _base]; };
            _c ctrlSetFontHeight _base;
            private _fh = _base;
            private _w = ((ctrlPosition _c) select 2) * 0.94;
            while { (ctrlTextWidth _c) > _w && {(_fh * 0.95) >= (_gh * 0.78)} } do { _fh = _fh * 0.95; _c ctrlSetFontHeight _fh; };
        } forEach [95908, 95909, 95910];
        ctrlSetFocus (_d displayCtrl 95908);
    };
};
true
