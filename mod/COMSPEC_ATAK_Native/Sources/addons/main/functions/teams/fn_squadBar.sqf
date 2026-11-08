/*
    Réglette d'effectif : le même dessin dans Groupe, dans l'ORBAT du BFT et sous la boussole de la carte.
    Une case par membre : chef, puis équipes de feu, puis sans équipe. Lettre de l'équipe (la même que les pions de la carte :
    équipe, sinon groupe), et l'état par la forme avant la couleur : pleine = apte, demi-hauteur = blessé, vide + ✕ = hors de
    combat, grise = hors ligne ou IA. « * » marque le chef, un cadre marque moi. État médical masqué (fn_medShow) : seules les
    cases mort et hors ligne se distinguent.
    Modes :
      ["cells", groupe ou unités]  -> [[lettre, rgba de l'équipe, état, chef, moi, netId, IA]...]. État : celui de fn_unitCard.
      ["count", cases]             -> [aptes, total]
      ["text", cases]              -> réglette en une chaîne pour une ligne de liste (ORBAT) : A apte, a blessé, x hors de combat, · hors ligne
      ["prims", [cases, x, y, w, h, renvoi à la ligne, carte, cliquable]] -> [hauteur, [[classe, pos, texte, fond, encre, police, netId]...]]
                                      Primitives à créer par l'appelant (fn_formRender, ou la carte ci-dessous).
      ["mapTick"]                  -> réglette de la carte (rectangle posé par fn_pageMap), une fois par seconde par fn_statusUpdate :
                                      aucun contrôle touché tant que la signature (états, équipes) ne change pas.
    Aucun coût par image : rien dans fn_mapOnDraw.
*/
params [["_mode", "cells"], ["_arg", []]];
disableSerialization;
switch (_mode) do {
    case "cells": {
        private _units = if (_arg isEqualType grpNull) then { units _arg } else { _arg };
        private _ldr = leader (group (_units param [0, objNull]));
        private _rows = [];
        {
            private _u = _x;
            private _ft = [_u] call comspec_atak_native_fnc_ftInfo;
            private _name = _ft get "name";
            if (_name isEqualTo "") then { _name = groupId group _u; };
            private _ai = !isPlayer _u;
            private _code = (["state", _u] call comspec_atak_native_fnc_unitCard) select 0;
            if (!_ai && {_code < 2} && {!(_u getVariable ["COMSPEC_ATAK_Beacon", true])}) then { _code = -2; };
            private _rgba = [[0.36, 0.78, 0.42, 1], _ft get "rgba"] select ((_ft get "id") isNotEqualTo "");
            // Clé de tri : chef, avec équipe, équipe, rang d'origine (jamais d'égalité : le tri ne compare pas la suite).
            _rows pushBack [[1, 0] select (_u isEqualTo _ldr), [1, 0] select ((_ft get "id") isNotEqualTo ""), _ft get "id", _forEachIndex, [toUpper (toString ((toArray _name) select [0, 1])), _rgba, _code, _u isEqualTo _ldr, _u isEqualTo player, netId _u, _ai]];
        } forEach _units;
        _rows sort true;
        _rows apply { _x select 4 }
    };
    case "count": {
        [{ !((_x select 2) in [1, 2, 3]) } count _arg, count _arg]
    };
    case "text": {
        (_arg apply {
            _x params ["_ltr", "", "_code"];
            switch (true) do { case (_code >= 2): { "x" }; case (_code isEqualTo 1): { toLower _ltr }; case (_code isEqualTo -2): { "·" }; default { _ltr }; }
        }) joinString ""
    };
    case "prims": {
        _arg params ["_cells", "_x0", "_y0", "_w", "_h", ["_wrap", false], ["_forMap", false], ["_click", false]];
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _th = COMSPEC_ATAK_Th;
        private _gap = pixelW * 2;
        private _gapY = pixelH * 2;
        private _fh = COMSPEC_ATAK_FontMin max (_h * 0.72);
        // La largeur tient une lettre et l'étoile du chef à 14 px au moins.
        private _cw = (_h / _ratio * 1.15) max (_fh * 1.4 / _ratio);
        (["count", _cells] call comspec_atak_native_fnc_squadBar) params ["_apt", "_tot"];
        // Compteur à droite : forme et nombre, jamais la couleur seule.
        private _cntW = _fh * 3.2 / _ratio;
        private _avail = _w - _cntW;
        private _per = (floor (_avail / (_cw + _gap))) max 1;
        private _shown = _cells;
        private _rest = 0;
        if (!_wrap && {(count _cells) > _per}) then {
            _per = (_per - 1) max 1;
            _rest = (count _cells) - _per;
            _shown = _cells select [0, _per];
        };
        private _ink = _th get (["text", "mapText"] select _forMap);
        private _muted = _th get (["textMuted", "mapDim"] select _forMap);
        private _alert = _th get (["alert", "mapAlert"] select _forMap);
        private _base = [_th get "surface", [0.035, 0.042, 0.038, 0.9]] select _forMap;
        private _out = [];
        {
            _x params ["_ltr", "_rgba", "_code", "_lead", "_me", "_nid", "_ai"];
            private _cx = _x0 + (_forEachIndex mod _per) * (_cw + _gap);
            private _cy = _y0 + (floor (_forEachIndex / _per)) * (_h + _gapY);
            private _hc = _code >= 2;
            private _grey = _code isEqualTo -2 || {_ai && {!_hc}};
            private _half = _code isEqualTo 1 && {!_ai};
            private _full = !_hc && {!_half};
            private _fill = [_rgba, _muted] select _grey;
            // Cadre de « moi » : une case plus grande derrière la mienne.
            if (_me) then { _out pushBack ["COMSPEC_RscText", [_cx - pixelW, _cy - pixelH, _cw + pixelW * 2, _h + pixelH * 2], "", _ink, [], 0, ""]; };
            _out pushBack ["COMSPEC_RscText", [_cx, _cy, _cw, _h], "", _base, [], 0, ""];
            if (_full) then { _out pushBack ["COMSPEC_RscText", [_cx, _cy, _cw, _h], "", _fill, [], 0, ""]; };
            if (_half) then { _out pushBack ["COMSPEC_RscText", [_cx, _cy + _h / 2, _cw, _h / 2], "", _fill, [], 0, ""]; };
            private _txt = [format ["%1%2", _ltr, ["", "*"] select _lead], "✕"] select _hc;
            _out pushBack ["COMSPEC_RscTextCenter", [_cx, _cy, _cw, _h], _txt, [], [[_ink, _th get "onAccent"] select _full, _alert] select _hc, _fh, ""];
            if (_click) then { _out pushBack ["COMSPEC_RscButtonOverlay", [_cx, _cy, _cw, _h], "", [], [], 0, _nid]; };
        } forEach _shown;
        private _nRows = ceil (((count _shown) max 1) / _per);
        private _lbl = format ["%1%2 / %3", ["", format ["+%1  ", _rest]] select (_rest > 0), _apt, _tot];
        _out pushBack ["COMSPEC_RscTextRight", [_x0 + _w - _cntW, _y0, _cntW, _h], _lbl, [], _ink, _fh, ""];
        [_nRows * (_h + _gapY), _out]
    };
    case "mapTick": {
        private _rect = uiNamespace getVariable ["COMSPEC_ATAK_SquadRect", []];
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_SquadCtrls", []];
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _units = units group player;
        // Rien à signaler, rien à l'écran : seul, ou tous aptes en petit format.
        private _cells = if ((count _rect) > 0 && {(count _units) > 1}) then { ["cells", _units] call comspec_atak_native_fnc_squadBar } else { [] };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        if ((_l get "mini") && {(_cells findIf { !((_x select 2) in [0, -1]) }) < 0}) then { _cells = []; };
        private _sig = [_cells, _rect];
        if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_SquadSig", [[], []]]) && {(_old findIf { isNull _x }) < 0}) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_SquadSig", _sig];
        { ctrlDelete _x; } forEach _old;
        private _made = [];
        if ((count _cells) > 0) then {
            _rect params ["_x0", "_y0", "_w", "_h"];
            private _click = _l get "interactive";
            private _p = ["prims", [_cells, _x0 + pixelW * 2, _y0 + pixelH, _w - pixelW * 4, _h - pixelH * 2, false, true, _click]] call comspec_atak_native_fnc_squadBar;
            private _bg = ["COMSPEC_RscText", [_x0, _y0, _w, _h], "", false] call comspec_atak_native_fnc_pageCtrl;
            _bg ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "mapPanel");
            _made pushBack _bg;
            {
                _x params ["_class", "_pos", "_text", "_back", "_inkC", "_fh", "_nid"];
                private _c = [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl;
                if ((count _back) > 0) then { _c ctrlSetBackgroundColor _back; };
                if ((count _inkC) > 0) then { _c ctrlSetTextColor _inkC; };
                if (_fh > 0) then { _c ctrlSetFontHeight _fh; };
                if (_nid isNotEqualTo "") then {
                    _c setVariable ["sqId", _nid];
                    _c ctrlAddEventHandler ["ButtonClick", {
                        params ["_c"];
                        [{
                            ["select", objectFromNetId _this] call comspec_atak_native_fnc_unitCard;
                            ["mapCard"] call comspec_atak_native_fnc_unitCard;
                            [] call comspec_atak_native_fnc_inspectorUpdate;
                        }, _c getVariable ["sqId", ""]] call comspec_atak_native_fnc_nextFrame;
                    }];
                };
                _made pushBack _c;
            } forEach (_p select 1);
        };
        uiNamespace setVariable ["COMSPEC_ATAK_SquadCtrls", _made];
        true
    };
    default { [] };
}
