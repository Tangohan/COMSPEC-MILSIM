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
// Journal : délai entre l'instant prévu de la détonation et l'effet affiché (avertissement au-delà de 0,3 s).
private _hitAt = _st getOrDefault ["hitAt", []];
if (_hitAt isNotEqualTo []) then {
    _st set ["hitAt", []];
    _hitAt params ["_tPlan", "_tHit", "_tick"];
    private _applied = _tHit + (diag_tickTime - _tick);
    private _delta = [_applied - _tPlan, 0] select (_tPlan < 0);
    ["joueur", format ["instants : prévu t=%1, reçu t=%2, effet affiché t=%3 (écart %4 s)%5", _tPlan toFixed 2, _tHit toFixed 2,
        _applied toFixed 2, _delta toFixed 3, ["", " ATTENTION : plus de 0,3 s"] select (_delta > 0.3)]] call comspec_fb_fnc_log;
};
private _W = "white" call _val;
private _B = "blur" call _val;
private _A = "aim" call _val;
private _H = "hear" call _val;
// Récupération en vagues (éclaircies puis rechutes), clignements (courts retours de flou), « plocs » de l'ouïe,
// pas hésitants : petites variations autour de la courbe moyenne, réglées par « Variation aléatoire ».
private _v = missionNamespace getVariable ["comspec_fb_variation", 0.6];
(_st getOrDefault ["phase", [0, 0, 0, 0]]) params ["_p1", "_p2", "_p3", "_p4"];
_W = _W * (1 + 0.12 * _v * sin (deg (_now * 1.7 + _p1)));
if (_W > 0.08 && {_W < 0.75} && {_now > (_st getOrDefault ["blinkUntil", 0]) + 0.8} && {random 1 < 0.02 * _v}) then {
    _st set ["blinkUntil", _now + 0.12 + random 0.15];
};
if (_now < (_st getOrDefault ["blinkUntil", 0])) then { _B = _B + 0.3 };
if (_H > 0.1 && {_now > (_st getOrDefault ["plocUntil", 0]) + 2} && {random 1 < 0.01 * _v}) then {
    _st set ["plocUntil", _now + 0.3 + random 0.4];
};
if (_now < (_st getOrDefault ["plocUntil", 0])) then { _H = _H * 0.7 };
private _F = "flare" call _val;
private _spots = (_st getOrDefault ["spots", []]) select { _now < (_x select 3) + (_x select 4) };
_st set ["spots", _spots];
private _tinnOn = ((_st getOrDefault ["tinn2", createHashMap]) getOrDefault ["state", "fini"]) != "fini";

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
    // Priorités hautes et libres (600 et 2500 sont prises par d'autres mods de flashbang) ; si une priorité est
    // occupée, ppEffectCreate rend -1 et on essaie la suivante. Créés une seule fois, détruits par fn_playerReset.
    _pp = [["ColorCorrections", 2590] call _mk, ["DynamicBlur", 620] call _mk, ["FilmGrain", 2090] call _mk];
    _st set ["pp", _pp];
    (_pp select 0) ppEffectEnable true;
    (_pp select 1) ppEffectEnable true;
};
_pp params ["_cc", "_blur", "_grain"];
// Voile blanc + contraste et saturation réduits (blanchiment des photorécepteurs) ; légère surexposition.
_cc ppEffectAdjust [1 + 0.15 * _W, (1 - 0.4 * _W - 0.15 * _B) max 0.4, 0, [1, 1, 1, ((_W ^ 0.9) * 0.9) min 0.9],
    [1, 1, 1, (1 - 0.25 * (_W max (0.5 * _B))) max 0.7], [0.299, 0.587, 0.114, 0]];
_cc ppEffectCommit 0.03;
// Flou fort au début (DynamicBlur ~45 à 3 m de face, au moins la force des autres mods de flashbang), qui décroît
// avec la récupération (~35 à 3 s, ~17 à 6 s).
_blur ppEffectAdjust [(((_B max 0) ^ 1.2) * 40 + (_W max 0) * 10) min 50];
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
    (_st getOrDefault ["tint", [1, 1, 1]]) params ["_tr", "_tg", "_tb"];
    private _wa = (((_W - 0.82) / 0.18) max 0) min 1;
    // Stroboscope pendant une rafale : un éclat blanc par détonation reçue (plein blanc au coup, puis chute en
    // 60 ms jusqu'au coup suivant), au lieu d'un blanc fixe : N coups = N éclats.
    if (_now < (_st getOrDefault ["strobeUntil", 0])) then {
        _wa = (_wa max 0.6) * (linearConversion [0, 0.06, _now - (_st getOrDefault ["lastHit", -10]), 1, 0.15, true]);
    };
    (_disp displayCtrl 101) ctrlSetTextColor [_tr, _tg, _tb, _wa];
    // Taches : positives (claires) pendant 0,8 s, puis négatives sombres dont la teinte dérive lentement
    // (« fuite des couleurs » de l'image rémanente), avec un très léger tremblement (micro-saccades).
    for "_k" from 0 to 2 do {
        private _ctrl = _disp displayCtrl (111 + _k);
        if (_k < count _spots) then {
            (_spots select _k) params ["_sx", "_sy", "_peak", "_t0", "_dur", ["_drift", [0, 0]], ["_szk", 1]];
            private _v = [[_peak, _t0, _dur, 0.03, 1.5], _now] call comspec_fb_fnc_chanValue;
            private _age = _now - _t0;
            private _size = (0.10 + 0.16 * _peak) * safeZoneH * _szk;
            _sx = _sx + (_drift select 0) * (_now - _t0);
            _sy = _sy + (_drift select 1) * (_now - _t0);
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
    // Vignette : bords sombres qui se resserrent et s'assombrissent avec l'éblouissement (blanc), puis se relâchent.
    private _vig = _disp displayCtrl 131;
    if (missionNamespace getVariable ["comspec_fb_vignette", true] && {_W > 0.01}) then {
        private _vg = _W min 1;
        private _vk = 1 + 0.35 * _vg;
        private _vw = safeZoneW * _vk;
        private _vh = safeZoneH * _vk;
        _vig ctrlSetPosition [safeZoneX + safeZoneW / 2 - _vw / 2, safeZoneY + safeZoneH / 2 - _vh / 2, _vw, _vh];
        _vig ctrlCommit 0;
        _vig ctrlSetTextColor [0, 0, 0, 0.85 * (_vg ^ 0.8)];
    } else {
        _vig ctrlSetTextColor [0, 0, 0, 0];
    };
};

// ------------------------------------------------------------------ visée et ouïe
private _aim0 = (_st get "orig") getOrDefault ["aim", 1];
private _aimNow = _aim0 * (1 + 4 * _A * (1 + 0.3 * _v * sin (deg (_now * 1.3 + _p2))));
// Pas plus lents et moins sûrs (désorientation) : jusqu'à 0,8 de la vitesse d'animation, jamais d'immobilisation.
if (_A > 0.2 && {_now > (_st getOrDefault ["hesitUntil", 0]) + 1.5} && {random 1 < 0.015 * _v}) then {
    _st set ["hesitUntil", _now + 0.25 + random 0.2];
};
private _slow = 0.2 * (_A min 1) * (1 + 0.35 * _v * sin (deg (_now * 0.9 + _p3)));
if (_now < (_st getOrDefault ["hesitUntil", 0])) then { _slow = _slow max (0.35 * (_A min 1)) };
_unit setAnimSpeedCoef (((_st get "orig") getOrDefault ["anim", 1]) * (1 - (_slow min 0.4)));
if (missionNamespace getVariable ["ace_advanced_fatigue_enabled", false]) then {
    // ACE règle lui-même le coefficient à chaque image : on ne fait que l'augmenter.
    _unit setCustomAimCoef ((getCustomAimCoef _unit) max _aimNow);
} else {
    _unit setCustomAimCoef _aimNow;
};
if (missionNamespace getVariable ["comspec_fb_muffle", true]) then {
    [1 - 0.75 * (_H min 1)] call comspec_fb_fnc_hearing;
};
