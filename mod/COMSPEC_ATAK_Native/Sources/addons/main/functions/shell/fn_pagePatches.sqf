/*
    Page PATCHES (ouverte depuis Réglages > Personnalisation, « Patch au dos ») : tous les patchs en vignettes.
    Recherche (nom ou classe, sans tenir compte des majuscules ni des accents), sources (Tous, Nos patchs, Mods,
    Mission), grille paginée, patch choisi en surbrillance. Un clic sur une vignette met le patch tout de suite
    (profil COMSPEC_ATAK_Patch, comme l'ancien sélecteur des Réglages) sans quitter la page ni remonter.
    Recherche, source et page gardées dans uiNamespace (COMSPEC_ATAK_PatchQuery, COMSPEC_ATAK_PatchSrc,
    COMSPEC_ATAK_PatchPg) : un redessin les retrouve. Seule la partie basse (sources, grille, pages) est redessinée
    pendant la frappe ou après un clic : le champ de recherche garde son texte et le curseur.
    Actions : ["page"] (fn_pageRender), ["grid"], ["pick", valeur], ["src", clé], ["pg", +1 / -1], ["query"].
*/
params [["_act", "page"], ["_arg", ""]];
disableSerialization;

private _isOpen = {
    !isNull ([] call comspec_atak_native_fnc_display)
    && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PATCHES"}
};

switch (_act) do {
    case "pick": {
        if !(_arg isEqualType "") exitWith { false };
        profileNamespace setVariable ["COMSPEC_ATAK_Patch", _arg];
        saveProfileNamespace;
        private _msg = format ["Patch au dos : %1", ["name", _arg] call comspec_atak_native_fnc_patchCatalog];
        uiNamespace setVariable ["COMSPEC_ATAK_PatchMsg", [_msg, diag_tickTime]];
        if (!isNil "comspec_atak_native_fnc_toast") then { [_msg, 2.5] call comspec_atak_native_fnc_toast; } else { ["INFO", _msg, 3, 10] call comspec_atak_native_fnc_notify; };
        // Comme l'ancien choix des Réglages : coque remise à jour ; téléphone au gilet refait tout de suite.
        [{
            [] call comspec_atak_native_fnc_layoutApply;
            ["tick"] call comspec_atak_native_fnc_vestMount;
            ["grid"] call comspec_atak_native_fnc_pagePatches;
        }] call CBA_fnc_execNextFrame;
        true
    };
    case "src": {
        uiNamespace setVariable ["COMSPEC_ATAK_PatchSrc", _arg];
        uiNamespace setVariable ["COMSPEC_ATAK_PatchPg", 0];
        [{ ["grid"] call comspec_atak_native_fnc_pagePatches; }] call CBA_fnc_execNextFrame;
        true
    };
    case "pg": {
        uiNamespace setVariable ["COMSPEC_ATAK_PatchPg", (uiNamespace getVariable ["COMSPEC_ATAK_PatchPg", 0]) + _arg];
        [{ ["grid"] call comspec_atak_native_fnc_pagePatches; }] call CBA_fnc_execNextFrame;
        true
    };
    case "query": {
        // Frappe dans la recherche : on attend 0,3 s sans frappe avant de filtrer.
        private _e = uiNamespace getVariable ["COMSPEC_ATAK_PatchEdit", controlNull];
        if (isNull _e) exitWith { false };
        private _t = ctrlText _e;
        if (_t isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_PatchQuery", ""])) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PatchQuery", _t];
        private _tok = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_PatchTok", _tok];
        [{
            params ["_tok"];
            if ((uiNamespace getVariable ["COMSPEC_ATAK_PatchTok", -1]) isNotEqualTo _tok) exitWith {};
            uiNamespace setVariable ["COMSPEC_ATAK_PatchPg", 0];
            ["grid"] call comspec_atak_native_fnc_pagePatches;
        }, [_tok], 0.3] call CBA_fnc_waitAndExecute;
        true
    };
    case "grid": {
        if !([] call _isOpen) exitWith { false };
        (uiNamespace getVariable ["COMSPEC_ATAK_PatchRect", []]) params [["_x0", 0], ["_y0", 0], ["_w", 0], ["_h", 0], ["_font", 0.03], ["_fs", 0.024], ["_pad", 0.01], ["_land", false]];
        if (_w <= 0) exitWith { false };
        { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PatchGrid", []]);
        uiNamespace setVariable ["COMSPEC_ATAK_PageControls", (uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []]) select { !isNull _x }];
        private _made = [];
        private _mk = {
            params ["_class", "_pos", ["_text", ""]];
            private _c = [_class, _pos, _text] call comspec_atak_native_fnc_pageCtrl;
            _made pushBack _c;
            _c
        };
        private _esc = { params ["_t"]; (_t regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"] regexReplace [">", "&gt;"] };
        ([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
        private _rowH = _font * 1.45;

        // Filtre : chaque mot de la recherche doit se trouver dans le nom ou la classe.
        private _all = ["list"] call comspec_atak_native_fnc_patchCatalog;
        private _terms = (["fold", uiNamespace getVariable ["COMSPEC_ATAK_PatchQuery", ""]] call comspec_atak_native_fnc_patchCatalog) splitString " ";
        private _match = if ((count _terms) isEqualTo 0) then { _all } else {
            _all select { private _k = _x select 4; (_terms findIf { (_k find _x) < 0 }) < 0 }
        };
        private _src = uiNamespace getVariable ["COMSPEC_ATAK_PatchSrc", "all"];
        private _cur = profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"];

        // Sources : nombre de résultats de la recherche dans chacune.
        private _srcs = [["all", "TOUS"], ["ours", "NOS PATCHS"], ["mods", "MODS"], ["mission", "MISSION"]];
        private _sw = _w / 4;
        {
            _x params ["_k", "_t"];
            private _n = if (_k isEqualTo "all") then { count _match } else { { (_x select 3) isEqualTo _k } count _match };
            private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * _sw, _y0, _sw - _pad / 4, _rowH], format ["%1 (%2)", _t, _n]] call _mk;
            _b ctrlSetFontHeight (_fs * 0.85);
            if (_k isEqualTo _src) then { _b ctrlSetBackgroundColor _acc; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; } else { _b ctrlSetBackgroundColor [0.12, 0.145, 0.13, 1]; _b ctrlSetTextColor [0.92, 0.96, 0.93, 1]; };
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['src', '%1'] call comspec_atak_native_fnc_pagePatches;", _k]];
        } forEach _srcs;
        if (_src isNotEqualTo "all") then { _match = _match select { (_x select 3) isEqualTo _src }; };

        // Grille paginée (4 x 3 en portrait, 6 x 2 en paysage).
        private _cols = [4, 6] select _land;
        private _rows = [3, 2] select _land;
        private _per = _cols * _rows;
        private _pages = (ceil ((count _match) / _per)) max 1;
        private _pg = uiNamespace getVariable ["COMSPEC_ATAK_PatchPg", 0];
        _pg = (_pg + _pages) mod _pages;
        uiNamespace setVariable ["COMSPEC_ATAK_PatchPg", _pg];
        private _gy = _y0 + _rowH + _pad / 2;
        private _footH = _rowH + _fs * 1.5 + _pad;
        private _gh = _h - (_gy - _y0) - _footH;
        private _gap = _pad / 2;
        private _tw = (_w - (_cols - 1) * _gap) / _cols;
        private _th = (_gh - (_rows - 1) * _gap) / _rows;
        private _lblH = _fs * 1.9;
        if ((count _match) isEqualTo 0) then {
            private _t = ["COMSPEC_RscStructuredText", [_x0, _gy + _pad, _w, _fs * 3]] call _mk;
            _t ctrlSetStructuredText parseText "<t align='center' color='#8a9a93'>Aucun patch ne correspond à la recherche.</t>";
        };
        {
            _x params ["_v", "_name", "_tex", "_from"];
            private _i = _forEachIndex;
            private _tx = _x0 + (_i mod _cols) * (_tw + _gap);
            private _ty = _gy + (floor (_i / _cols)) * (_th + _gap);
            private _on = _v isEqualTo _cur;
            if (_v isEqualTo "uniform") then { _tex = ([player, "uniform"] call comspec_atak_native_fnc_patchTexture) select 0; };
            private _bg = ["COMSPEC_RscText", [_tx, _ty, _tw, _th]] call _mk;
            _bg ctrlSetBackgroundColor ([[0.085, 0.105, 0.095, 1], [(_acc select 0) * 0.35, (_acc select 1) * 0.35, (_acc select 2) * 0.35, 1]] select _on);
            if (_on) then {
                private _bar = ["COMSPEC_RscText", [_tx, _ty + _th - _pad / 3, _tw, _pad / 3]] call _mk;
                _bar ctrlSetBackgroundColor _acc;
            };
            private _ph = _th - _lblH - _pad / 2;
            if (_tex isNotEqualTo "") then {
                private _p = ["COMSPEC_RscPicture", [_tx + _pad / 4, _ty + _pad / 4, _tw - _pad / 2, _ph], _tex] call _mk;
                _p ctrlSetTextColor [1, 1, 1, 1];
            } else {
                private _e = ["COMSPEC_RscTextCenter", [_tx, _ty + _pad / 4, _tw, _ph], ["AUCUN", "SANS INSIGNE"] select (_v isEqualTo "uniform")] call _mk;
                _e ctrlSetFontHeight (_fs * 0.8);
                _e ctrlSetTextColor [0.55, 0.6, 0.58, 1];
            };
            private _lb = ["COMSPEC_RscStructuredText", [_tx, _ty + _th - _lblH - _pad / 3, _tw, _lblH]] call _mk;
            _lb ctrlSetStructuredText parseText format ["<t align='center' size='0.72' color='%1'>%2</t>", ["#dfe8e3", _accHex] select _on, [_name] call _esc];
            private _b = ["COMSPEC_RscButtonOverlay", [_tx, _ty, _tw, _th]] call _mk;
            _b ctrlSetTooltip format ["%1\n%2 · %3", _name, [_v select [4], _v] select (_from isEqualTo "ours"), ["nos patchs", "mods", "mission"] select (["ours", "mods", "mission"] find _from)];
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['pick', %1] call comspec_atak_native_fnc_pagePatches;", str _v]];
        } forEach (_match select [_pg * _per, _per]);

        // Pages : boutons texte (les flèches ◀ ▶ ne s'affichent pas avec la police du téléphone).
        private _fy = _y0 + _h - _footH + _pad / 2;
        private _bwid = _w * 0.24;
        private _prev = ["COMSPEC_RscButton", [_x0, _fy, _bwid, _rowH], "<  PRÉC."] call _mk;
        private _next = ["COMSPEC_RscButton", [_x0 + _w - _bwid, _fy, _bwid, _rowH], "SUIV.  >"] call _mk;
        {
            _x ctrlSetFontHeight (_fs * 0.9);
            _x ctrlEnable (_pages > 1);
        } forEach [_prev, _next];
        _prev ctrlAddEventHandler ["ButtonClick", { ['pg', -1] call comspec_atak_native_fnc_pagePatches; }];
        _next ctrlAddEventHandler ["ButtonClick", { ['pg', 1] call comspec_atak_native_fnc_pagePatches; }];
        private _pt = ["COMSPEC_RscStructuredText", [_x0 + _bwid, _fy + (_rowH - _fs * 1.3) / 2, _w - 2 * _bwid, _fs * 1.3]] call _mk;
        _pt ctrlSetStructuredText parseText format ["<t align='center' size='0.85'>Page %1 / %2  <t color='#8a9a93'>· %3 patch(s)</t></t>", _pg + 1, _pages, count _match];
        // Ligne de confirmation : patch porté, et le dernier choix pendant quelques secondes.
        (uiNamespace getVariable ["COMSPEC_ATAK_PatchMsg", ["", -100]]) params ["_msg", "_at"];
        private _st = ["COMSPEC_RscStructuredText", [_x0, _fy + _rowH + _pad / 4, _w, _fs * 1.4]] call _mk;
        _st ctrlSetStructuredText parseText (if (diag_tickTime - _at < 4) then {
            format ["<t align='center' size='0.85' color='%1'>✓ %2 · enregistré</t>", _accHex, [_msg] call _esc]
        } else {
            format ["<t align='center' size='0.85' color='#8a9a93'>Porté : </t><t align='center' size='0.85'>%1</t>", [["name", _cur] call comspec_atak_native_fnc_patchCatalog] call _esc]
        });
        uiNamespace setVariable ["COMSPEC_ATAK_PatchGrid", _made];
        true
    };
    default {
        // Page entière : en-tête, recherche (gardée), puis la partie basse.
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["", "", "_bw", "_bh"];
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _pad = (_l get "pad") * 2;
        private _w = _bw - 2 * _pad;
        private _rowH = _font * 1.45;
        uiNamespace setVariable ["COMSPEC_ATAK_PatchGrid", []];
        private _bg = ["COMSPEC_RscPanel", [0, 0, _bw, _bh]] call comspec_atak_native_fnc_pageCtrl;
        _bg ctrlSetBackgroundColor [0.035, 0.045, 0.04, 0.96];
        private _y = _pad;
        private _back = ["COMSPEC_RscButton", [_pad, _y, _w * 0.3, _rowH], "< RÉGLAGES"] call comspec_atak_native_fnc_pageCtrl;
        _back ctrlSetFontHeight (_fs * 0.9);
        _back ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_back; }] call CBA_fnc_execNextFrame; }];
        private _hd = ["COMSPEC_RscStructuredText", [_pad + _w * 0.32, _y + (_rowH - _fs * 1.4) / 2, _w * 0.68, _fs * 1.4]] call comspec_atak_native_fnc_pageCtrl;
        _hd ctrlSetStructuredText parseText format ["<t align='right' font='RobotoCondensedBold'>Patch au dos</t><t align='right' size='0.8' color='#8a9a93'>  %1 insignes d'unité</t>", ["count"] call comspec_atak_native_fnc_patchCatalog];
        _y = _y + _rowH + _pad / 2;
        private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], "RECHERCHER (NOM OU CLASSE)"] call comspec_atak_native_fnc_pageCtrl;
        _lb ctrlSetFontHeight (_fs * 0.9);
        _y = _y + _fs * 1.2;
        private _q = uiNamespace getVariable ["COMSPEC_ATAK_PatchQuery", ""];
        private _e = ["COMSPEC_RscEdit", [_pad, _y, _w * 0.78, _rowH], _q] call comspec_atak_native_fnc_pageCtrl;
        _e ctrlSetFontHeight _font;
        _e ctrlAddEventHandler ["KeyUp", { ["query"] call comspec_atak_native_fnc_pagePatches; }];
        _e ctrlAddEventHandler ["SetFocus", { uiNamespace setVariable ["COMSPEC_ATAK_PatchFocus", true]; }];
        _e ctrlAddEventHandler ["KillFocus", { uiNamespace setVariable ["COMSPEC_ATAK_PatchFocus", false]; }];
        uiNamespace setVariable ["COMSPEC_ATAK_PatchEdit", _e];
        private _clr = ["COMSPEC_RscButton", [_pad + _w * 0.8, _y, _w * 0.2, _rowH], "EFFACER"] call comspec_atak_native_fnc_pageCtrl;
        _clr ctrlSetFontHeight (_fs * 0.85);
        _clr ctrlAddEventHandler ["ButtonClick", {
            private _e = uiNamespace getVariable ["COMSPEC_ATAK_PatchEdit", controlNull];
            if (!isNull _e) then { _e ctrlSetText ""; ctrlSetFocus _e; };
            uiNamespace setVariable ["COMSPEC_ATAK_PatchQuery", ""];
            uiNamespace setVariable ["COMSPEC_ATAK_PatchPg", 0];
            [{ ["grid"] call comspec_atak_native_fnc_pagePatches; }] call CBA_fnc_execNextFrame;
        }];
        _y = _y + _rowH + _pad / 2;
        // Première ouverture : la page qui montre le patch porté.
        if (isNil { uiNamespace getVariable "COMSPEC_ATAK_PatchPg" }) then {
            private _per = [12, 12] select (_l get "landscape");
            private _i = (["list"] call comspec_atak_native_fnc_patchCatalog) findIf { (_x select 0) isEqualTo (profileNamespace getVariable ["COMSPEC_ATAK_Patch", "none"]) };
            uiNamespace setVariable ["COMSPEC_ATAK_PatchSrc", "all"];
            uiNamespace setVariable ["COMSPEC_ATAK_PatchPg", floor ((_i max 0) / _per)];
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PatchRect", [_pad, _y, _w, _bh - _y - _pad / 2, _font, _fs, _pad, _l getOrDefault ["landscape", false]]];
        ["grid"] call comspec_atak_native_fnc_pagePatches;
        // Page redessinée pendant la frappe (rotation, glissé du téléphone…) : le curseur reste dans la recherche.
        if (uiNamespace getVariable ["COMSPEC_ATAK_PatchFocus", false]) then { ctrlSetFocus _e; };
        true
    };
}
