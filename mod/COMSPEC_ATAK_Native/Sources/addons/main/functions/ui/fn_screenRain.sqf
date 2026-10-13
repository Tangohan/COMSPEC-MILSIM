/*
    Gouttes de pluie sur le verre du téléphone, animées (PFH de XEH_postInitClient, toutes les 0,04 s au plus) :
      - au rythme de la pluie qui touche l'écran (COMSPEC_ATAK_Device rainHit, fn_screenDirt), une goutte se pose au
        hasard ou fait grossir une goutte déjà posée ;
      - petite goutte : reste posée (tension de surface), rapetisse et s'efface quand la pluie s'arrête ;
      - assez grosse : tient un instant, puis glisse vers le bas en accélérant, trajectoire un peu irrégulière et
        à-coups (le verre accroche), perd de l'eau dans sa traînée et s'arrête si elle devient trop petite, absorbe les
        gouttes posées qu'elle croise, sort par le bas ; la traînée mouillée sèche en quelques secondes.
    Rendu d'eau sur du verre (tools/gen_rain.py) : corps un peu assombri, liseré net, croissant clair dans le bas,
    reflet ; quatre formes de goutte posée. Bruine fine sur tout l'écran (une image), qui suit l'humidité.
    Coût : au plus 18 gouttes (9 en « Réduit », 12 images par seconde), deux contrôles chacune, créés une fois et
    déplacés ; rien tant que le téléphone est rangé, l'écran sec ou le réglage client comspec_atak_native_rain_fx
    sur « Désactivé » ; 2 mises à jour par seconde quand rien ne glisse (séchage). Géométrie de l'écran relevée par
    fn_screenSurface (COMSPEC_ATAK_RainRect, suit le plein écran à échelle réglable).
    Params : [mode] : "tick" (défaut) ou "clear" (supprime tout).
*/
params [["_mode", "tick"]];
disableSerialization;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_Rain", createHashMap];
private _slots = _st getOrDefault ["slots", []];
private _clear = {
    { ctrlDelete (_x select 9); ctrlDelete (_x select 10); } forEach _slots;
    ctrlDelete (_st getOrDefault ["mist", controlNull]);
    _slots = [];
    uiNamespace setVariable ["COMSPEC_ATAK_Rain", createHashMap];
};
if (_mode isEqualTo "clear") exitWith { call _clear; true };
private _fx = missionNamespace getVariable ["comspec_atak_native_rain_fx", 2];
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _wet = _n getOrDefault ["drops", 0];
private _hit = _n getOrDefault ["rainHit", 0];
if (_fx isEqualTo 0 || {_wet < 0.02 && {_hit <= 0}} || {isNull ([] call comspec_atak_native_fnc_display)}) exitWith {
    if (_slots isNotEqualTo []) then { call _clear; };
    false
};
private _now = diag_tickTime;
// Saleté coupée (réglage serveur ou Réalisme du joueur) : vérifié toutes les 2 s seulement.
(uiNamespace getVariable ["COMSPEC_ATAK_RainChk", [-1, true]]) params ["_chkAt", "_on"];
if (_now > _chkAt) then {
    _on = ["enabled"] call comspec_atak_native_fnc_screenDirt;
    uiNamespace setVariable ["COMSPEC_ATAK_RainChk", [_now + 2, _on]];
};
if (!_on) exitWith { if (_slots isNotEqualTo []) then { call _clear; }; false };
(uiNamespace getVariable ["COMSPEC_ATAK_RainRect", []]) params [["_rect", []], ["_ratio", pixelH / pixelW]];
if (_rect isEqualTo []) exitWith { false };
private _full = _fx isEqualTo 2;
private _max = [9, 18] select _full;
// Cadence : 25 (Complet) ou 12 (Réduit) images par seconde quand ça bouge, 2 sinon (gouttes posées qui sèchent).
private _step = [0.5, [0.08, 0.04] select _full] select (_st getOrDefault ["busy", true]);
private _last = _st getOrDefault ["last", -1];
if (_last > 0 && {(_now - _last) < _step}) exitWith { false };
private _dt = [0.04, (_now - _last) min 0.6] select (_last > 0);
_st set ["last", _now];
private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
private _mk = {
    private _c = ["COMSPEC_RscPhone", [0, 0, 0, 0], _this, false] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlEnable false;
    _c ctrlShow false;
    _c ctrlCommit 0;
    _c
};
// Emplacements : [0 phase (0 libre, 1 posée, 2 glisse, 3 traînée seule), 1 u, 2 v (fractions de l'écran),
// 3 taille (fraction de la hauteur), 4 attente avant de glisser, 5 vitesse, 6 u0, 7 v0 (haut de la traînée),
// 8 traînée (opacité 0-1), 9 contrôle goutte, 10 contrôle traînée, 11 phase d'ondulation, 12 texture, 13 largeur traînée,
// 14 forme (0-3)]
if ((count _slots) isNotEqualTo _max) then {
    call _clear;
    for "_i" from 1 to _max do { _slots pushBack [0, 0, 0, 0, 0, 0, 0, 0, 0, controlNull, controlNull, random 360, "", 0, floor random 4]; };
    _st = createHashMapFromArray [["last", _now]];
};
// Contrôles supprimés avec la page (fn_pageClear) : recréés par-dessus la nouvelle page, l'état des gouttes est gardé.
// La bruine est créée d'abord : sous les gouttes.
if (isNull ((_slots select 0) select 9)) then {
    ctrlDelete (_st getOrDefault ["mist", controlNull]);
    _st set ["mist", ("rain_mist_land" call _tex) call _mk];
    _st set ["mistKey", ""];
    {
        _x set [9, ("rain_bead_0" call _tex) call _mk];
        _x set [10, (format ["rain_trail_%1", _forEachIndex mod 2] call _tex) call _mk];
        _x set [12, "rain_bead_0"];
    } forEach _slots;
};
_rect params ["_rx", "_ry", "_rw", "_rh"];
// Bruine : fines gouttelettes partout, plus visibles quand l'écran est trempé ; image paysage ou portrait.
private _mist = _st get "mist";
private _mk2 = ["rain_mist_port", "rain_mist_land"] select ((_rw * _ratio) >= _rh);
if (_mk2 isNotEqualTo (_st getOrDefault ["mistKey", ""])) then { _mist ctrlSetText (_mk2 call _tex); _st set ["mistKey", _mk2]; };
_mist ctrlSetPosition _rect;
_mist ctrlSetTextColor [1, 1, 1, ((_wet * 1.6) min 1) * 0.6];
_mist ctrlShow true;
_mist ctrlCommit 0;
// Pluie sur l'écran : nouvelles gouttes, ou une goutte posée qui grossit. Sortie de l'eau (fn_screenDirt "water") :
// l'écran se couvre d'un coup.
private _acc = (_st getOrDefault ["acc", 0]) + _dt * _hit * ([3, 6] select _full);
if ((_n getOrDefault ["splash", 0]) > 0) then { _n set ["splash", 0]; _acc = _acc + _max; };
if (_acc >= 1) then {
    while { _acc >= 1 } do {
        _acc = _acc - 1;
        private _posed = _slots select { (_x select 0) isEqualTo 1 };
        private _i = _slots findIf { (_x select 0) isEqualTo 0 };
        if (_posed isNotEqualTo [] && {_i < 0 || {random 1 < 0.35}}) then {
            private _b = selectRandom _posed;
            _b set [3, ((_b select 3) + 0.004 + random 0.005) min 0.07];
        } else {
            if (_i >= 0) then {
                private _s = _slots select _i;
                _s set [0, 1];
                _s set [1, 0.03 + random 0.94];
                _s set [2, 0.02 + random 0.9];
                _s set [3, 0.01 + 0.03 * ((random 1) ^ 1.8)];
                _s set [4, 0.4 + random 2.5];
                _s set [5, 0];
                _s set [8, 0];
                _s set [14, floor random 4];
            };
        };
    };
};
_st set ["acc", _acc];
private _thr = 0.028; // taille à partir de laquelle une goutte glisse
private _minSlide = 0.016; // en dessous, elle s'arrête
private _dry = [0, _dt * 0.00035] select (_hit <= 0);
private _alpha = 0.95 * ((_wet * 4) min 1);
private _busy = _hit > 0;
{
    private _slot = _x;
    private _i = _forEachIndex;
    _slot params ["_ph", "_u", "_v", "_s", "_hold", "_vy", "_u0", "_v0", "_ta", "_cd", "_ct", "_wp", "_tk", "_tw", "_var"];
    if (_ph isEqualTo 1) then {
        _s = _s - _dry;
        if (_s < 0.005) then { _ph = [0, 3] select (_ta > 0); } else {
            if (_s >= _thr && {_hit > 0 || {_s >= _thr * 1.3}}) then {
                _hold = _hold - _dt;
                if (_hold <= 0) then { _ph = 2; _vy = 0.02; _u0 = _u; _v0 = _v; _ta = 1; _tw = _s; };
            };
        };
    };
    if (_ph isEqualTo 2) then {
        _busy = true;
        // Plus elle est grosse, plus elle accélère ; le verre l'accroche parfois (à-coup).
        _vy = (_vy + _dt * 1.2 * ((_s - _minSlide) / 0.025)) min (0.1 + 6 * _s);
        if (random 1 < _dt * 1.5) then { _vy = _vy * 0.25; };
        private _dv = _vy * _dt;
        _v = _v + _dv;
        _u = ((_u + (sin (_wp + _v * 900)) * _dv * 0.06 + ((random 2) - 1) * _dv * 0.04) max 0.01) min 0.99;
        // Elle laisse de l'eau dans sa traînée ; la traînée ne garde que la fin du trajet (le haut a déjà séché).
        _s = _s - _dv * 0.015;
        if ((_v - _v0) > 0.4) then { _v0 = _v - 0.4; _u0 = _u0 + (_u - _u0) * 0.2; };
        // Absorbe les gouttes posées sur son chemin.
        {
            if (_forEachIndex isNotEqualTo _i && {(_x select 0) isEqualTo 1}) then {
                private _r = ((_x select 3) + _s) * 0.6;
                if (abs ((_x select 2) - _v) < _r && {(abs ((_x select 1) - _u)) * _rw < _r * _rh / _ratio}) then {
                    _s = (sqrt (_s ^ 2 + (_x select 3) ^ 2)) min 0.07;
                    _x set [0, [0, 3] select ((_x select 8) > 0)];
                };
            };
        } forEach _slots;
        if (_s < _minSlide) then { _ph = 1; _vy = 0; _hold = 2 + random 4; };
        if (_v > 1.03) then { _ph = 3; };
    };
    if (_ph isNotEqualTo 2 && {_ta > 0}) then {
        _busy = true;
        _ta = _ta - _dt / 4;
        if (_ta <= 0) then { _ta = 0; if (_ph isEqualTo 3) then { _ph = 0; }; };
    };
    // Rendu.
    if (_ph isEqualTo 0) then {
        _cd ctrlShow false;
        _ct ctrlShow false;
    } else {
        private _cx = _rx + _u * _rw;
        private _cy = _ry + _v * _rh;
        if (_ph isEqualTo 3) then { _cd ctrlShow false; } else {
            private _k = [format ["rain_bead_%1", _var], format ["rain_run_%1", _var mod 2]] select (_ph isEqualTo 2);
            if (_k isNotEqualTo _tk) then { _cd ctrlSetText (_k call _tex); _tk = _k; };
            if (_ph isEqualTo 2) then {
                // Texture 128 x 256 : tête (72 % de la largeur) centrée à 75 % de la hauteur, corps court vers le haut.
                private _h = _s * _rh / 0.72;
                _cd ctrlSetPosition [_cx - _h / _ratio / 2, _cy - _h * 1.5, _h / _ratio, _h * 2];
            } else {
                // Texture 128 x 128 : goutte de ~78 % du cadre.
                private _h = _s * _rh / 0.78;
                _cd ctrlSetPosition [_cx - _h / _ratio / 2, _cy - _h / 2, _h / _ratio, _h];
            };
            _cd ctrlSetTextColor [1, 1, 1, _alpha];
            _cd ctrlShow true;
            _cd ctrlCommit 0;
        };
        if (_ta > 0) then {
            // Traînée : du haut du trajet jusqu'au centre de la goutte, inclinée selon la dérive.
            private _xa = _rx + _u0 * _rw;
            private _ya = _ry + _v0 * _rh;
            private _dx = (_cx - _xa) * _ratio;
            private _dy = _cy - _ya;
            private _len = sqrt (_dx ^ 2 + _dy ^ 2);
            if (_len < 0.003) then { _ct ctrlShow false; } else {
                // Texture 64 x 256 : chapelet large d'environ 60 % du cadre en bas.
                private _w = _tw * _rh * 1.4 / _ratio;
                _ct ctrlSetPosition [(_cx + _xa) / 2 - _w / 2, (_cy + _ya) / 2 - _len / 2, _w, _len];
                _ct ctrlSetAngle [-(_dx atan2 _dy), 0.5, 0.5];
                _ct ctrlSetTextColor [1, 1, 1, _ta * 0.9 * ((_wet * 4) min 1)];
                _ct ctrlShow true;
                _ct ctrlCommit 0;
            };
        } else {
            _ct ctrlShow false;
        };
    };
    _slots set [_i, [_ph, _u, _v, _s, _hold, _vy, _u0, _v0, _ta, _cd, _ct, _wp, _tk, _tw, _var]];
} forEach _slots;
_st set ["slots", _slots];
_st set ["busy", _busy];
uiNamespace setVariable ["COMSPEC_ATAK_Rain", _st];
true
