/*
    Alerte du vibreur BFT à l'écran du téléphone : grand bandeau qui clignote (orange), indicatif de l'appelant, son
    message, sa grille et sa distance, avec VU (accusé renvoyé à l'appelant), CARTE (centre la carte sur lui) et FERMER.
    Gardée deux minutes (uiNamespace COMSPEC_ATAK_BuzzAlert) : téléphone rangé, elle s'affiche à la prochaine
    ouverture ; elle est redessinée devant après chaque rendu de page (fn_fx « raise »).
    Params :
      ["show", [indicatif, grille, texte, position ASL, émetteur]]  nouvelle alerte (remplace la précédente)
      ["draw"]                                                     redessine l'alerte en cours (sinon rien)
      ["ack"] / ["map"] / ["close"]                                 boutons
*/
params [["_act", "draw"], ["_arg", []]];
disableSerialization;
private _clear = { { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_BuzzCtrls", []]); uiNamespace setVariable ["COMSPEC_ATAK_BuzzCtrls", []]; };
private _cur = uiNamespace getVariable ["COMSPEC_ATAK_BuzzAlert", []];
switch (_act) do {
    case "show": {
        _arg params [["_from", "?"], ["_grid", ""], ["_txt", ""], ["_pos", []], ["_src", objNull]];
        uiNamespace setVariable ["COMSPEC_ATAK_BuzzAlert", [_from, _grid, _txt, _pos, _src, diag_tickTime, [dayTime, "HH:MM"] call BIS_fnc_timeToString]];
        ["draw"] call comspec_atak_native_fnc_buzzAlert;
    };
    case "close": { uiNamespace setVariable ["COMSPEC_ATAK_BuzzAlert", []]; call _clear; };
    case "ack": {
        _cur params ["", "", "", "", ["_src", objNull]];
        if (!isNull _src) then { ["comspec_atak_native_buzzAck", [[player] call comspec_atak_native_fnc_unitCallsign], _src] call CBA_fnc_targetEvent; };
        ["close"] call comspec_atak_native_fnc_buzzAlert;
    };
    case "map": {
        _cur params ["", "", "", ["_pos", []], ["_src", objNull]];
        ["close"] call comspec_atak_native_fnc_buzzAlert;
        ["MAP"] call comspec_atak_native_fnc_navigate;
        private _p = [_pos, getPosASL _src] select (!isNull _src && {alive _src});
        if ((count _p) >= 2) then { [{ [_this, 0.03] call comspec_atak_native_fnc_mapCenter; }, _p, 0.2] call CBA_fnc_waitAndExecute; };
    };
    case "draw": {
        call _clear;
        if ((count _cur) < 6) exitWith {};
        _cur params ["_from", "_grid", "_txt", "_pos", "_src", "_at", "_hh"];
        if ((diag_tickTime - _at) > 120) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_BuzzAlert", []]; };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith {};
        // Écran éteint ou détruit, animation d'alimentation, verrou à distance du Zeus : l'alerte attend (comme fn_fullAlert).
        if ((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"]) exitWith {};
        if (((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) > diag_tickTime) exitWith {};
        if ((["lock", ""] call comspec_atak_native_fnc_zeusFx) isNotEqualTo "") exitWith {};
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        private _pad = _l get "pad";
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _p = [_pos, getPosASL _src] select (!isNull _src && {alive _src});
        private _dist = if ((count _p) >= 2) then { round (player distance2D _p) } else { -1 };
        private _h = (_font * 7.2) min (_bh * 0.8);
        private _y = _by + (_bh - _h) * 0.3;
        private _ax = _bx + _pad * 3;
        private _w = _bw - _pad * 6;
        private _bg = _d ctrlCreate ["COMSPEC_RscText", -1];
        _bg ctrlSetPosition [_ax, _y, _w, _h];
        _bg ctrlSetBackgroundColor [0.10, 0.06, 0.02, 0.97];
        private _band = _d ctrlCreate ["COMSPEC_RscText", -1];
        _band ctrlSetPosition [_ax, _y, _w, _font * 1.6];
        _band ctrlSetBackgroundColor [0.95, 0.55, 0.12, 1];
        private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
        _t ctrlSetPosition [_ax + _pad, _y + _pad * 0.6, _w - _pad * 2, _h - _font * 1.9];
        // Texte d'un autre joueur : &, < et > échappés (entités déjà échappées gardées), comme fn_fullAlert.
        private _esc = {
            params ["_s"];
            _s = [str _s, _s] select (_s isEqualType "");
            _s = _s regexReplace ["&(?!(amp|lt|gt|quot|apos|#[0-9]+);)", "&amp;"];
            [[_s, "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace
        };
        _t ctrlSetStructuredText parseText ((format ["<t size='1.2' font='COMSPEC_BarlowBold' color='#1a0f05'>VIBREUR · %1</t><br/><br/><t size='1.5' font='COMSPEC_BarlowBold' color='#ffffff'>%2</t><br/><t size='0.85' color='#f2c18a'>%3 · grille %4%5</t>",
            [_from] call _esc, [[_txt] call _esc, "Il vous appelle"] select (_txt isEqualTo ""), _hh, [_grid] call _esc, ["", format [" · %1 m", _dist]] select (_dist >= 0)]) call comspec_atak_native_fnc_tr);
        private _ctrls = [_bg, _band, _t];
        private _bw3 = (_w - _pad * 4) / 3;
        {
            _x params ["_lab", "_a", "_cls"];
            private _b = _d ctrlCreate [_cls, -1];
            [_b, _cls] call comspec_atak_native_fnc_fontApply;
            _b ctrlSetPosition [(_bx + _pad * 3) + _pad + _forEachIndex * (_bw3 + _pad), _y + _h - _font * 1.6, _bw3, _font * 1.35];
            _b ctrlSetText ((_lab) call comspec_atak_native_fnc_tr);
            _b ctrlSetFontHeight _fs;
            _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ [%1] call comspec_atak_native_fnc_buzzAlert; }] call CBA_fnc_execNextFrame;", str _a]];
            _ctrls pushBack _b;
        } forEach [["VU", "ack", "COMSPEC_RscButtonPrimary"], ["CARTE", "map", "COMSPEC_RscButton"], ["FERMER", "close", "COMSPEC_RscButton"]];
        { _x ctrlCommit 0; } forEach _ctrls;
        uiNamespace setVariable ["COMSPEC_ATAK_BuzzCtrls", _ctrls];
        // Bandeau sous les bris d'écran et les effets, comme les notifications (fn_overlayRaise, à l'image suivante).
        [] call comspec_atak_native_fnc_overlayRaise;
        // Clignotement du bandeau les premières secondes (animé par le moteur, sans boucle par image).
        if ((diag_tickTime - _at) < 6) then {
            {
                [{ params ["_c", "_f"]; if (!isNull _c) then { _c ctrlSetFade _f; _c ctrlCommit 0.2; }; }, [_band, [0.6, 0] select (_forEachIndex mod 2 isEqualTo 1)], _forEachIndex * 0.4] call CBA_fnc_waitAndExecute;
            } forEach [0, 1, 2, 3, 4, 5, 6, 7];
        };
    };
};
true
