/*
    Moniteur cardiaque (app Médical) : tracé ECG défilant au rythme du blessé suivi, avec ses constantes.
    Params : [[x, y, w, h] dans la zone de contenu, unité suivie]
    Le tracé est une suite de colonnes fines (une par échantillon) reliant chaque point au précédent ;
    une boucle de 40 ms le fait défiler et s'arrête seule quand la page change.
    Constantes : état et pouls lus par COMSPEC Link (ACE), tension et SpO2 estimées d'après le sang.
*/
params ["_rect", ["_u", objNull]];
disableSerialization;
_rect params ["_x0", "_y0", "_w", "_h"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _bg = ["COMSPEC_RscPanel", _rect] call _mk;
_bg ctrlSetBackgroundColor [0.02, 0.03, 0.025, 0.98];
private _gw = _w * 0.6;
private _head = ["COMSPEC_RscStructuredText", [_x0 + _pad, _y0 + _pad / 2, _gw - _pad, _fs * 2.6]] call _mk;
private _vit = ["COMSPEC_RscStructuredText", [_x0 + _gw + _pad, _y0 + _pad / 2, _w - _gw - _pad * 2, _h - _pad]] call _mk;
private _sep = ["COMSPEC_RscText", [_x0 + _gw, _y0 + _pad, pixelW, _h - _pad * 2]] call _mk;
_sep ctrlSetBackgroundColor [0.2, 0.25, 0.22, 1];
// Zone du tracé
private _ty = _y0 + _fs * 2.8;
private _th = _h - _fs * 2.8 - _pad;
private _n = 72;
private _cw = (_gw - _pad * 2) / _n;
private _cols = [];
for "_i" from 0 to (_n - 1) do {
    private _c = ["COMSPEC_RscText", [_x0 + _pad + _i * _cw, _ty + _th / 2, _cw * 1.05, pixelH * 2]] call _mk;
    _c ctrlSetBackgroundColor [0.36, 0.85, 0.42, 1];
    _cols pushBack _c;
};
private _base = ["COMSPEC_RscText", [_x0 + _pad, _ty + _th * 0.62, _gw - _pad * 2, pixelH]] call _mk;
_base ctrlSetBackgroundColor [0.36, 0.85, 0.42, 0.15];
uiNamespace setVariable ["COMSPEC_ATAK_Ecg", [_cols, _head, _vit, _u, [_x0 + _pad, _ty, _cw, _th], 0, -1]];
if ((uiNamespace getVariable ["COMSPEC_ATAK_EcgPfh", -1]) >= 0) exitWith {};
private _pfh = [{
    params ["", "_pfh"];
    private _e = uiNamespace getVariable ["COMSPEC_ATAK_Ecg", []];
    if ((count _e) < 7 || {isNull ((_e select 0) param [0, controlNull])}) exitWith {
        [_pfh] call CBA_fnc_removePerFrameHandler;
        uiNamespace setVariable ["COMSPEC_ATAK_EcgPfh", -1];
    };
    _e params ["_cols", "_head", "_vit", "_u", "_geo", "_t0", "_next"];
    _geo params ["_gx", "_gy", "_cw", "_th"];
    // Constantes relues une fois par seconde.
    if (diag_tickTime > _next) then {
        _e set [6, diag_tickTime + 1];
        private _st = if (isNull _u) then { ["none", 100, 0, 0] } else {
            if (isNil "comspec_overwatch_connect_fnc_getMedicalState") then { [["stable", "kia"] select !alive _u, 100, 0, [80, 0] select !alive _u] } else { ([_u] call comspec_overwatch_connect_fnc_getMedicalState) splitString "|" }
        };
        private _state = _st param [0, "stable"];
        if (!isNull _u && {!alive _u}) then { _state = "kia"; };
        // Overwatch renvoie des textes ("76") : parseNumber str "76" donnait 0 (guillemets), d'où 0 bpm et ligne plate.
        private _num = { params ["_v"]; if (_v isEqualType "") then { parseNumber _v } else { _v } };
        private _blood = [_st param [1, 100]] call _num;
        private _hr = [_st param [3, 80]] call _num;
        if (_state in ["cardiac_arrest", "kia"]) then { _hr = 0; };
        _e set [7, _hr];
        _e set [8, _state];
        // Données masquées (Réglages > Réalisme, fn_medShow) : ni valeur, ni tracé, ni couleur d'alerte.
        (["state", "hr", "bp", "spo2", "blood"] apply { [_x, "medical"] call comspec_atak_native_fnc_medShow }) params ["_vState", "_vHr", "_vBp", "_vSpo", "_vBlood"];
        _e set [9, [_vHr, _vState]];
        private _lab = createHashMapFromArray [["cardiac_arrest", ["ARRÊT CARDIAQUE", "#e5483a"]], ["unconscious", ["INCONSCIENT", "#e5483a"]], ["critical", ["CRITIQUE", "#f2ab33"]], ["wounded", ["BLESSÉ", "#e8b84a"]], ["kia", ["KIA", "#8a9a93"]], ["none", ["AUCUN BLESSÉ", "#8a9a93"]]] getOrDefault [_state, ["STABLE", "#5cc76b"]];
        if (!_vState && {!(_state in ["kia", "none"])}) then { _lab = ["SUIVI", "#8a9a93"]; };
        _head ctrlSetStructuredText parseText format ["<t size='0.75' color='#8a9a93'>BLESSÉ SUIVI</t>  <t size='0.75' color='%3'>● %2</t><br/><t size='1.25' font='RobotoCondensedBold'>%1</t>",
            [[_u, true] call comspec_atak_native_fnc_unitCallsign, "—"] select (isNull _u), _lab select 0, _lab select 1];
        private _sys = round (70 + 50 * (_blood / 100)); private _dia = round (40 + 30 * (_blood / 100));
        private _spo = [round ((80 + 18 * (_blood / 100)) min 99), 0] select (_hr isEqualTo 0);
        private _col = { params ["_v", "_ok"]; format ["<t color='%1'>%2</t>", ["#f2ab33", "#5cc76b"] select _ok, _v] };
        private _lines = [];
        if (_vHr) then { _lines pushBack format ["Fréquence cardiaque<t align='right'>%1 bpm</t>", [_hr, _hr >= 50 && {_hr <= 110}] call _col]; };
        if (_vBp) then { _lines pushBack format ["Tension<t align='right'>%1 / %2</t>", _sys, _dia]; };
        if (_vSpo) then { _lines pushBack format ["SpO2<t align='right'>%1 %%</t>", [_spo, _spo >= 94] call _col]; };
        if (_vBlood) then { _lines pushBack format ["Sang<t align='right'>%1 %%</t>", [round _blood, _blood >= 80] call _col]; };
        _lines pushBack format ["Distance<t align='right'>%1</t>", [format ["%1 m", round (player distance _u)], "—"] select (isNull _u)];
        _vit ctrlSetStructuredText parseText format ["<t size='0.8'>%1</t>", _lines joinString "<br/>"];
    };
    private _hr = _e param [7, 0];
    private _state = _e param [8, "none"];
    _t0 = _t0 + 0.04;
    _e set [5, _t0];
    private _per = 60 / (_hr max 1);
    // Forme d'un battement (phase 0..1) : P, QRS, T.
    private _wave = {
        params ["_p"];
        switch (true) do {
            case (_p > 0.10 && {_p < 0.18}): { 0.12 * sin (180 * (_p - 0.10) / 0.08) };
            case (_p >= 0.24 && {_p < 0.26}): { -0.12 };
            case (_p >= 0.26 && {_p < 0.28}): { -0.12 + 1.12 * ((_p - 0.26) / 0.02) };
            case (_p >= 0.28 && {_p < 0.30}): { 1 - 1.25 * ((_p - 0.28) / 0.02) };
            case (_p >= 0.30 && {_p < 0.33}): { -0.25 + 0.25 * ((_p - 0.30) / 0.03) };
            case (_p > 0.45 && {_p < 0.60}): { 0.28 * sin (180 * (_p - 0.45) / 0.15) };
            default { 0 };
        }
    };
    private _span = 2.6;
    private _n = count _cols;
    private _prev = -1;
    (_e param [9, [true, true]]) params ["_vHr", "_vState"];
    if (!_vHr) exitWith { { _x ctrlShow false; } forEach _cols; };
    private _rgb = [[0.36, 0.85, 0.42, 1], [0.9, 0.28, 0.23, 1]] select (_vState && {_state in ["cardiac_arrest", "unconscious", "critical"]});
    {
        _x ctrlShow true;
        private _t = _t0 + _forEachIndex * _span / _n;
        private _v = if (_hr <= 0) then { [0, (random 0.06) - 0.03] select (_state isEqualTo "cardiac_arrest") } else { [(_t mod _per) / _per] call _wave };
        // Point : 62 % de la hauteur = ligne de base, 1 = haut de la zone.
        private _py = (0.62 - 0.58 * _v) * _th;
        if (_prev < 0) then { _prev = _py; };
        private _top = (_py min _prev) - pixelH;
        private _hh = ((abs (_py - _prev)) + pixelH * 2);
        _x ctrlSetPosition [_gx + _forEachIndex * _cw, _gy + _top, _cw * 1.05, _hh];
        _x ctrlSetBackgroundColor _rgb;
        _x ctrlCommit 0;
        _prev = _py;
    } forEach _cols;
}, 0.04] call CBA_fnc_addPerFrameHandler;
uiNamespace setVariable ["COMSPEC_ATAK_EcgPfh", _pfh];
