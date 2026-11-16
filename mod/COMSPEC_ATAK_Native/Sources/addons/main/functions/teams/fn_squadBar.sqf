/*
    Réglette d'effectif : le même dessin dans Groupe, dans l'ORBAT du BFT et sous la boussole de la carte.
    Une case par membre : chef, puis équipes de feu, puis sans équipe. Lettre de l'équipe (la même que les pions de la carte :
    équipe, sinon groupe) dans sa couleur, éclaircie sur fond sombre. L'état par la forme ET la couleur : teintée de l'équipe
    avec un trait plein en bas = apte, moitié basse ambre = blessé, vide + filet et ✕ rouges = hors de combat, piste grise =
    hors ligne ou IA. Une pastille en haut à droite marque le chef, un filet d'accent marque moi. Cases presque carrées,
    écarts égaux, compteur « 6/6 » collé à la dernière case. État médical masqué (fn_medShow) : seules les cases mort et
    hors ligne se distinguent.
    Modes :
      ["cells", groupe ou unités]  -> [[lettre, rgba de l'équipe, état, chef, moi, netId, IA]...]. État : celui de fn_unitCard.
      ["count", cases]             -> [aptes, total]
      ["text", cases]              -> réglette en une chaîne pour une ligne de liste (ORBAT) : A apte, a blessé, x hors de combat, · hors ligne
      ["geom", [h, texte du compteur]]   -> [police, largeur d'une case, écart, largeur du compteur]
      ["prims", [cases, x, y, w, h, renvoi à la ligne, carte, cliquable]] -> [hauteur, [[classe, pos, texte, fond, encre, police, netId]...], largeur prise]
                                      Primitives à créer par l'appelant (fn_formRender, ou la carte ci-dessous).
      ["mapTick"]                  -> réglette de la carte (rectangle posé par fn_pageMap), une fois par seconde par fn_statusUpdate :
                                      aucun contrôle touché tant que la signature (états, équipes) ne change pas.
                                      Réglage joueur comspec_atak_native_map_squadbar décoché : rien n'est créé (absent = affichée).
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
    case "geom": {
        // Mesures d'une réglette de hauteur _h : [police, largeur d'une case, écart, largeur du compteur]. Partagées par
        // « prims » et fn_pageMap (place réservée aux étiquettes : les cases et le compteur, pas plus).
        _arg params ["_h", ["_lbl", "9/9"]];
        private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
        private _fh = COMSPEC_ATAK_FontMin max (_h * 0.6);
        // Case presque carrée, assez large pour une lettre au plancher de police.
        private _cw = (_h / _ratio * 0.92) max (_fh * 1.05 / _ratio);
        [_fh, _cw, pixelW * 3, _fh * (0.52 * (count _lbl) + 0.7) / _ratio]
    };
    case "prims": {
        _arg params ["_cells", "_x0", "_y0", "_w", "_h", ["_wrap", false], ["_forMap", false], ["_click", false]];
        private _th = COMSPEC_ATAK_Th;
        private _gapY = pixelH * 3;
        (["count", _cells] call comspec_atak_native_fnc_squadBar) params ["_apt", "_tot"];
        // Compteur collé à la dernière case : forme et nombre, jamais la couleur seule. Mesuré sur son vrai texte.
        private _lbl = format ["%1/%2", _apt, _tot];
        (["geom", [_h, _lbl]] call comspec_atak_native_fnc_squadBar) params ["_fh", "_cw", "_gap", "_cntW"];
        private _per = (floor ((_w - _cntW) / (_cw + _gap))) max 1;
        private _shown = _cells;
        private _rest = 0;
        if (!_wrap && {(count _cells) > _per}) then {
            _per = (_per - 1) max 1;
            _rest = (count _cells) - _per;
            _shown = _cells select [0, _per];
            _lbl = format ["+%1 %2", _rest, _lbl];
            _cntW = (["geom", [_h, _lbl]] call comspec_atak_native_fnc_squadBar) select 3;
        };
        // Fond sombre (carte, thèmes sombres) : encres éclaircies ; thème jour : encres assombries (contraste AA, 4,5).
        private _dark = _forMap || {_th getOrDefault ["dark", true]};
        private _tone = if (_dark) then {
            { params ["_c", "_k"]; [(_c select 0) + (1 - (_c select 0)) * _k, (_c select 1) + (1 - (_c select 1)) * _k, (_c select 2) + (1 - (_c select 2)) * _k, 1] }
        } else {
            { params ["_c", "_k"]; private _m = 1 - _k; [(_c select 0) * _m, (_c select 1) * _m, (_c select 2) * _m, 1] }
        };
        private _ink = _th get (["text", "mapText"] select _forMap);
        private _muted = _th get (["textMuted", "mapDim"] select _forMap);
        private _alert = [_th get (["alert", "mapAlert"] select _forMap), [0, 0.15] select !_dark] call _tone;
        private _warn = _th get (["warn", "mapWarn"] select _forMap);
        private _warnInk = [_warn, [0, 0.25] select !_dark] call _tone;
        private _acc = _th get "accent";
        private _track = [_th get "surface2", [1, 1, 1, 0.07]] select _forMap;
        private _a = { params ["_c", "_al"]; [_c select 0, _c select 1, _c select 2, _al] };
        // Filet d'un pixel autour d'une case (dedans, ou dehors de 1 px pour « moi »).
        private _edge = {
            params ["_ex", "_ey", "_ew", "_eh", "_col"];
            _out pushBack ["COMSPEC_RscText", [_ex, _ey, _ew, pixelH], "", _col, [], 0, ""];
            _out pushBack ["COMSPEC_RscText", [_ex, _ey + _eh - pixelH, _ew, pixelH], "", _col, [], 0, ""];
            _out pushBack ["COMSPEC_RscText", [_ex, _ey, pixelW, _eh], "", _col, [], 0, ""];
            _out pushBack ["COMSPEC_RscText", [_ex + _ew - pixelW, _ey, pixelW, _eh], "", _col, [], 0, ""];
        };
        private _out = [];
        {
            _x params ["_ltr", "_rgba", "_code", "_lead", "_me", "_nid", "_ai"];
            private _cx = _x0 + (_forEachIndex mod _per) * (_cw + _gap);
            private _cy = _y0 + (floor (_forEachIndex / _per)) * (_h + _gapY);
            private _hc = _code >= 2;
            private _grey = _code isEqualTo -2 || {_ai && {!_hc}};
            private _half = _code isEqualTo 1 && {!_ai};
            private _full = !_hc && {!_half} && {!_grey};
            // Piste neutre, puis l'état : apte = case teintée de l'équipe et trait plein en bas ; blessé = moitié basse
            // ambre ; hors de combat = vide, filet et ✕ rouges ; hors ligne ou IA = piste seule, lettre grisée.
            _out pushBack ["COMSPEC_RscText", [_cx, _cy, _cw, _h], "", _track, [], 0, ""];
            if (_full) then {
                _out pushBack ["COMSPEC_RscText", [_cx, _cy, _cw, _h], "", [_rgba, 0.30] call _a, [], 0, ""];
                _out pushBack ["COMSPEC_RscText", [_cx, _cy + _h - pixelH * 2, _cw, pixelH * 2], "", [_rgba, 1] call _a, [], 0, ""];
            };
            if (_half) then { _out pushBack ["COMSPEC_RscText", [_cx, _cy + _h / 2, _cw, _h / 2], "", [_warn, 0.30] call _a, [], 0, ""]; };
            if (_hc) then { [_cx, _cy, _cw, _h, [_alert, 0.85] call _a] call _edge; };
            // Moi : filet fin d'accent, un pixel autour de la case.
            if (_me) then { [_cx - pixelW, _cy - pixelH, _cw + pixelW * 2, _h + pixelH * 2, _acc] call _edge; };
            // Chef : pastille discrète dans le coin haut droit.
            if (_lead) then { _out pushBack ["COMSPEC_RscText", [_cx + _cw - pixelW * 5, _cy + pixelH * 2, pixelW * 3, pixelH * 3], "", _ink, [], 0, ""]; };
            private _txt = [_ltr, "✕"] select _hc;
            private _col = switch (true) do {
                case (_hc): { _alert };
                case (_grey): { _muted };
                case (_half): { _warnInk };
                default { [_rgba, [0.45, 0.58] select !_dark] call _tone };
            };
            _out pushBack ["COMSPEC_RscTextCenter", [_cx, _cy, _cw, _h], _txt, [], _col, _fh, ""];
            if (_click) then { _out pushBack ["COMSPEC_RscButtonOverlay", [_cx, _cy, _cw, _h], "", [], [], 0, _nid]; };
        } forEach _shown;
        private _nRows = ceil (((count _shown) max 1) / _per);
        private _inRow = (count _shown) min _per;
        private _cntX = _x0 + _inRow * (_cw + _gap);
        _out pushBack ["COMSPEC_RscText", [_cntX, _y0, _cntW, _h], _lbl, [], _ink, _fh, ""];
        // Troisième élément : largeur réellement prise (cases et compteur), pour que le fond n'aille pas plus loin.
        [_nRows * (_h + _gapY), _out, (_cntX + _cntW) - _x0]
    };
    case "mapTick": {
        // Réglage du joueur décoché : pas de rectangle, donc aucune case (les anciennes sont effacées une fois, puis plus rien).
        private _rect = if ((missionNamespace getVariable ["comspec_atak_native_map_squadbar", true]) isEqualTo false) then { [] } else { uiNamespace getVariable ["COMSPEC_ATAK_SquadRect", []] };
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
            // Marges égales autour des cases ; le fond s'arrête au compteur (troisième élément de « prims »).
            private _p = ["prims", [_cells, _x0 + pixelW * 3, _y0 + pixelH * 3, _w - pixelW * 6, _h - pixelH * 6, false, true, _click]] call comspec_atak_native_fnc_squadBar;
            private _bg = ["COMSPEC_RscText", [_x0, _y0, ((_p select 2) + pixelW * 6) min _w, _h], "", false] call comspec_atak_native_fnc_pageCtrl;
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
