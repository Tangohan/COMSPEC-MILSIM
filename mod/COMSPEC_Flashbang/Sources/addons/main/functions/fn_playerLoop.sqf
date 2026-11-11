/*
    comspec_fb_fnc_playerLoop : boucle (CBA, toutes les 0,03 s) qui n'existe QUE pendant un effet sur le joueur.
    Applique : post-traitements (ColorCorrections, DynamicBlur, FilmGrain en JVN), calque plein écran (voile blanc,
    taches d'image rémanente, halo d'optique), visée, ouïe. S'arrête et nettoie tout dès que tout est revenu à zéro,
    ou tout de suite si le joueur meurt, change d'unité, ouvre Zeus ou passe en spectateur (fn_playerReset).
*/
private _st = comspec_fb_state;
private _unit = _st getOrDefault ["unit", objNull];
if (isNull _unit || {!alive _unit} || {_unit != call CBA_fnc_currentUnit} || {!isNull curatorCamera}) exitWith {
    [] call comspec_fb_fnc_playerReset;
};
private _now = CBA_missionTime;
private _val = { [_st getOrDefault [_this, []], _now] call comspec_fb_fnc_chanValue };
private _W = "white" call _val;
private _B = "blur" call _val;
private _A = "aim" call _val;
private _H = "hear" call _val;
private _F = "flare" call _val;
private _spots = (_st getOrDefault ["spots", []]) select { _now < (_x select 3) + (_x select 4) };
_st set ["spots", _spots];
(_st getOrDefault ["tinn", [-1, 50, 0, 0]]) params ["", "_o0", "_tStart"];
private _tinnOn = (_o0 + _now - _tStart) < 50;

if (_W + _B + _A + _H + _F < 0.003 && {_spots isEqualTo []} && {!_tinnOn}) exitWith {
    [] call comspec_fb_fnc_playerReset;
};

// ------------------------------------------------------------------ post-traitements (créés à la première image)
private _pp = _st getOrDefault ["pp", []];
if (_pp isEqualTo []) then {
    private _mk = {
        params ["_name", "_prio"];
        private _h = -1;
        while { _h = ppEffectCreate [_name, _prio]; _h < 0 } do { _prio = _prio + 1 };
        _h ppEffectForceInNVG true;
        _h
    };
    _pp = [["ColorCorrections", 1590] call _mk, ["DynamicBlur", 490] call _mk, ["FilmGrain", 2090] call _mk];
    _st set ["pp", _pp];
    (_pp select 0) ppEffectEnable true;
    (_pp select 1) ppEffectEnable true;
};
_pp params ["_cc", "_blur", "_grain"];
// Voile blanc + contraste et saturation réduits (blanchiment des photorécepteurs) ; légère surexposition.
_cc ppEffectAdjust [1 + 0.15 * _W, (1 - 0.4 * _W - 0.15 * _B) max 0.4, 0, [1, 1, 1, ((_W ^ 0.9) * 0.9) min 0.9],
    [1, 1, 1, (1 - 0.25 * (_W max (0.5 * _B))) max 0.7], [0.299, 0.587, 0.114, 0]];
_cc ppEffectCommit 0.03;
_blur ppEffectAdjust [(_B * 1.4 + _W * 0.6) min 2.2];
_blur ppEffectCommit 0.03;
// JVN : tube saturé, grain fort tant que l'éblouissement dure.
if (currentVisionMode _unit == 1 && {_now < (_st getOrDefault ["nvgUntil", 0])}) then {
    _grain ppEffectEnable true;
    _grain ppEffectAdjust [0.15 + 0.5 * _W, 1.25, 2.2, 0.75, 1, true];
    _grain ppEffectCommit 0.03;
} else {
    _grain ppEffectEnable false;
};

// ------------------------------------------------------------------ calque plein écran
private _disp = uiNamespace getVariable ["comspec_fb_overlay", displayNull];
if (isNull _disp) then {
    ("comspec_fb_layer" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_FB_Overlay", "PLAIN", 0, false];
    _disp = uiNamespace getVariable ["comspec_fb_overlay", displayNull];
};
if (!isNull _disp) then {
    (_disp displayCtrl 101) ctrlSetTextColor [1, 1, 1, (((_W - 0.82) / 0.18) max 0) min 1];
    // Taches : positives (claires) pendant 0,8 s, puis négatives sombres dont la teinte dérive lentement
    // (« fuite des couleurs » de l'image rémanente), avec un très léger tremblement (micro-saccades).
    for "_k" from 0 to 2 do {
        private _ctrl = _disp displayCtrl (111 + _k);
        if (_k < count _spots) then {
            (_spots select _k) params ["_sx", "_sy", "_peak", "_t0", "_dur"];
            private _v = [[_peak, _t0, _dur, 0.03, 1.5], _now] call comspec_fb_fnc_chanValue;
            private _age = _now - _t0;
            private _size = (0.06 + 0.08 * _peak) * safeZoneH;
            private _w = _size * pixelW / pixelH;
            private _jx = 0.002 * sin (_age * 420);
            private _jy = 0.002 * cos (_age * 310);
            _ctrl ctrlSetPosition [_sx - _w / 2 + _jx, _sy - _size / 2 + _jy, _w, _size];
            _ctrl ctrlCommit 0;
            if (_age < 0.8) then {
                _ctrl ctrlSetTextColor [1, 0.97, 0.88, 0.85 * _v];
            } else {
                _ctrl ctrlSetTextColor [0.12 + 0.05 * sin (_age * 20), 0.08 + 0.04 * sin (_age * 13 + 60), 0.16 + 0.05 * sin (_age * 17 + 120), 0.8 * _v];
            };
        } else {
            _ctrl ctrlSetTextColor [1, 1, 1, 0];
        };
    };
    private _flare = _disp displayCtrl 121;
    if (_F > 0.01) then {
        private _s = 0.7 * safeZoneH;
        private _w = _s * pixelW / pixelH;
        _flare ctrlSetPosition [0.5 - _w / 2, 0.5 - _s / 2, _w, _s];
        _flare ctrlCommit 0;
        _flare ctrlSetTextColor [1, 1, 1, _F min 1];
    } else {
        _flare ctrlSetTextColor [1, 1, 1, 0];
    };
};

// ------------------------------------------------------------------ visée et ouïe
private _aim0 = (_st get "orig") getOrDefault ["aim", 1];
private _aimNow = _aim0 * (1 + 1.8 * _A);
if (missionNamespace getVariable ["ace_advanced_fatigue_enabled", false]) then {
    // ACE règle lui-même le coefficient à chaque image : on ne fait que l'augmenter.
    _unit setCustomAimCoef ((getCustomAimCoef _unit) max _aimNow);
} else {
    _unit setCustomAimCoef _aimNow;
};
if (missionNamespace getVariable ["comspec_fb_muffle", true]) then {
    [1 - 0.75 * (_H min 1)] call comspec_fb_fnc_hearing;
};
