/*
    Bugs d'affichage passagers de l'écran du téléphone (famille « glitch » de fn_screenDamage) : image déchirée, barre de
    balayage, couleurs décalées, pavés de compression, image figée, scintillement, neige, luminosité qui pompe, demi-écran
    en retard, écran noir puis logo, appuis fantômes. Toujours courts (0,2 à 3 s), un seul à la fois, jamais gardés.
    Un bug crée au plus 4 contrôles, animés par un seul gestionnaire « EachFrame » posé au début du bug et retiré à sa
    fin : aucun travail par image tant que rien ne bugue. Rien ne bloque l'écran, sauf en « gêne légère » ou « milsim »
    (réglage comspec_atak_native_screen_impact) où « image figée » et « écran noir puis logo » avalent les appuis le temps
    du bug. Les appuis fantômes ne font que s'afficher : aucune action n'est jamais déclenchée à la place du joueur.
    Scintillement et pompage : variations lentes et de faible contraste (rien de photosensible).
    Params : [genre du bug | "bg" | "frame" | "stop", valeur]
      genre  : lance ce bug tout de suite (valeur : durée en secondes, 0 = durée propre au bug) ;
      "bg"   : tirage de fond, chaque seconde (fn_deviceOverlay, valeur : état matériel) : plus fréquent avec les
               dégâts, l'eau, une batterie presque vide, le froid, le brouillage ;
      "frame": image suivante du bug en cours ; "stop" : arrêt immédiat (redémarrage, réparation, écran éteint).
*/
params [["_mode", "bg"], ["_v", 0]];
disableSerialization;
private _state = uiNamespace getVariable ["COMSPEC_ATAK_Glitch", []];
private _stop = {
    { ctrlDelete _x; } forEach (_state param [3, []]);
    uiNamespace setVariable ["COMSPEC_ATAK_Glitch", []];
    private _eh = uiNamespace getVariable ["COMSPEC_ATAK_GlitchEh", -1];
    if (_eh >= 0) then { removeMissionEventHandler ["EachFrame", _eh]; uiNamespace setVariable ["COMSPEC_ATAK_GlitchEh", -1]; };
};
if (_mode isEqualTo "stop") exitWith { call _stop; true };
private _d = [] call comspec_atak_native_fnc_display;
if (_mode isEqualTo "frame") exitWith {
    if (_state isEqualTo []) exitWith { call _stop; false };
    _state params ["_kind", "_t0", "_t1", "_ctrls", "_rect", "_next"];
    if (isNull _d || {diag_tickTime >= _t1} || {(_ctrls findIf { isNull _x }) >= 0}) exitWith { call _stop; false };
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    private _p = (diag_tickTime - _t0) / ((_t1 - _t0) max 0.01);
    private _c = _ctrls param [0, controlNull];
    // Pas de l'animation : 12 changements par seconde au plus (jamais un changement à chaque image).
    private _step = diag_tickTime >= _next;
    if (_step) then { _state set [5, diag_tickTime + 0.08]; };
    switch (_kind) do {
        case "tear": { if (_step) then { _c ctrlSetPosition [_rx, _ry + pixelH * (floor random 9), _rw, _rh]; _c ctrlCommit 0; _c ctrlShow (random 1 < 0.8); }; };
        case "roll": { private _h = _rh * 0.3; _c ctrlSetPosition [_rx, _ry + (_rh - _h) * _p, _rw, _h]; _c ctrlCommit 0; };
        case "rgb": { if (_step) then { _c ctrlSetPosition [_rx + pixelW * ((floor random 5) - 2), _ry, _rw, _rh]; _c ctrlCommit 0; }; };
        case "noise": { if (_step) then { _c ctrlSetText (format ["\z\comspec_atak_native\addons\main\data\gl_static_%1.paa", floor random 2]); _c ctrlSetAngle [selectRandom [0, 180], 0.5, 0.5]; }; }; // i18n:brut
        // Trois creux par seconde au plus, contraste faible.
        case "flicker": { _c ctrlSetBackgroundColor [0, 0, 0, 0.28 * (0.5 - 0.5 * cos (360 * 2.5 * (diag_tickTime - _t0)))]; };
        case "pump": { _c ctrlSetBackgroundColor [0, 0, 0, 0.24 * (0.5 - 0.5 * cos (360 * (diag_tickTime - _t0) / 1.3))]; };
        case "partial": { { _x ctrlShow (_p < 0.75); } forEach _ctrls; };
        case "reboot": { (_ctrls param [1, controlNull]) ctrlShow (_p > 0.3 && {_p < 0.92}); };
        case "ghosttap": { { _x ctrlShow (_p >= _forEachIndex * 0.3 && {_p < _forEachIndex * 0.3 + 0.38}); } forEach _ctrls; };
    };
    true
};
if !(["on", ["glitch"]] call comspec_atak_native_fnc_screenDamage) exitWith { false };
if (_mode isEqualTo "bg") exitWith {
    if (_state isNotEqualTo [] || {isNull _d}) exitWith { false };
    private _hp = if (_v isEqualType createHashMap) then { _v } else { createHashMap };
    if ((_hp getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"]) exitWith { false };
    private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
    private _dmg = _hp getOrDefault ["damage", 0];
    private _jam = ([] call comspec_atak_native_fnc_ewEffects) param [3, 0];
    private _bat = missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100];
    private _wet = (_n getOrDefault ["wet", 0]) > 0 || {((_n getOrDefault ["scr", []]) findIf { (_x select 0) isEqualTo "water" }) >= 0};
    private _cold = (ambientTemperature select 0) < -5;
    // Chances par seconde, par cause ; la plus forte choisit la famille de bugs.
    private _w = [
        ["shock", [0, 0.004 + 0.02 * _dmg] select (_dmg >= 0.2)],
        ["jam", 0.06 * _jam],
        ["water", [0, 0.012] select _wet],
        ["lowbat", [0, 0.012] select (_bat < 8)],
        ["cold", [0, 0.004] select _cold],
        ["wear", 0.0015 * (count (_n getOrDefault ["scr", []]))]
    ];
    private _tot = 0;
    { _tot = _tot + (_x select 1); } forEach _w;
    private _k = ((missionNamespace getVariable ["comspec_atak_native_screen_glitch_rate", 1]) max 0 min 3) * ([0, 0.5, 1, 1.4] select (["level"] call comspec_atak_native_fnc_screenDamage));
    if (_tot <= 0 || {random 1 > _tot * _k}) exitWith { false };
    private _best = "shock";
    private _bv = -1;
    { if ((_x select 1) > _bv) then { _bv = _x select 1; _best = _x select 0; }; } forEach _w;
    private _bugs = ((["causes"] call comspec_atak_native_fnc_screenDamage) getOrDefault [_best, [[], [], []]]) select 2;
    if (_bugs isEqualTo []) exitWith { false };
    [selectRandom _bugs] call comspec_atak_native_fnc_screenGlitch
};
// Lancement d'un bug.
if (_state isNotEqualTo [] || {isNull _d}) exitWith { false };
if ((([] call comspec_atak_native_fnc_deviceHealth) getOrDefault ["state", "OK"]) in ["OFF", "BROKEN"]) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _rect = _l get "device";
_rect params ["_rx", "_ry", "_rw", "_rh"];
private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
private _impact = ["impact"] call comspec_atak_native_fnc_screenDamage;
private _soft = [1, 0.7] select ((["level"] call comspec_atak_native_fnc_screenDamage) isEqualTo 1);
private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
// Contrôles créés par les effets eux-mêmes : fn_pageCtrl ne demande pas de remonter les calques.
private _ovb = uiNamespace getVariable ["COMSPEC_ATAK_OvBuild", -1];
uiNamespace setVariable ["COMSPEC_ATAK_OvBuild", diag_frameNo];
private _ctrls = [];
private _pic = {
    params ["_t", "_pos", ["_alpha", 1]];
    private _c = ["COMSPEC_RscPhone", _pos, _t call _tex, false] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlEnable false;
    _c ctrlSetTextColor [1, 1, 1, _alpha * _soft];
    _ctrls pushBack _c;
    _c
};
private _box = {
    params ["_pos", "_col"];
    private _c = ["COMSPEC_RscText", _pos, "", false] call comspec_atak_native_fnc_pageCtrl;
    _c ctrlEnable false;
    _c ctrlSetBackgroundColor _col;
    _ctrls pushBack _c;
    _c
};
// Avale les appuis le temps du bug (gêne légère et milsim seulement).
private _swallow = { if (_impact >= 1) then { _ctrls pushBack (["COMSPEC_RscButtonInvisible", _rect, "", false] call comspec_atak_native_fnc_pageCtrl); }; };
private _dur = 0.4;
switch (_mode) do {
    case "tear": { [format ["gl_tear_%1", floor random 2], _rect, 0.95] call _pic; _dur = 0.25 + random 0.35; };
    case "roll": { ["gl_scan_0", [_rx, _ry, _rw, _rh * 0.3], 0.9] call _pic; _dur = 0.8 + random 0.7; };
    case "rgb": { ["gl_rgb_0", _rect, 0.9] call _pic; _dur = 0.2 + random 0.3; };
    case "blocks": {
        // Pavés carrés (pas d'étirement), une ou deux grappes.
        private _s = (_rh min (_rw * _ratio)) * (0.6 + random 0.4);
        for "_i" from 0 to (floor random 2) do {
            [format ["gl_block_%1", floor random 2], [_rx + (_rw - _s / _ratio) * random 1, _ry + (_rh - _s) * random 1, _s / _ratio, _s], 0.95] call _pic;
        };
        _dur = 0.2 + random 0.3;
    };
    case "freeze": { [_rect, [0.6, 0.7, 0.8, 0.04]] call _box; call _swallow; _dur = 0.3 + random 0.4; };
    case "flicker": { [_rect, [0, 0, 0, 0]] call _box; _dur = 0.4 + random 0.5; };
    case "noise": { [format ["gl_static_%1", floor random 2], _rect, 0.45] call _pic; _dur = 0.2 + random 0.3; };
    case "pump": { [_rect, [0, 0, 0, 0]] call _box; _dur = 2 + random 1; };
    case "partial": {
        // La moitié basse (ou droite) reste en retard : plus sombre, avec la ligne de raccord.
        private _half = if (random 1 < 0.5) then { [_rx, _ry + _rh * 0.5, _rw, _rh * 0.5] } else { [_rx + _rw * 0.5, _ry, _rw * 0.5, _rh] };
        [_half, [0, 0, 0, 0.3 * _soft]] call _box;
        [[_half select 0, _half select 1, [_half select 2, pixelW * 2] select ((_half select 3) isEqualTo _rh), [_half select 3, pixelH * 2] select ((_half select 2) isEqualTo _rw)], [1, 1, 1, 0.5 * _soft]] call _box;
        _dur = 0.3 + random 0.3;
    };
    case "reboot": {
        [_rect, [0, 0, 0, 1]] call _box;
        [format ["boot_%1", ["port", "land"] select (_l getOrDefault ["landscape", false])], _rect, 1] call _pic;
        call _swallow;
        _dur = 1.6;
    };
    case "ghosttap": {
        private _s = (_rh min (_rw * _ratio)) * 0.09;
        for "_i" from 1 to (2 + floor random 2) do {
            (["gl_tap_0", [_rx + (_rw - _s / _ratio) * (0.1 + random 0.8), _ry + (_rh - _s) * (0.15 + random 0.75), _s / _ratio, _s], 0.8] call _pic) ctrlShow false;
        };
        _dur = 0.7 + random 0.5;
    };
    default { _dur = 0; };
};
uiNamespace setVariable ["COMSPEC_ATAK_OvBuild", _ovb];
if (_dur <= 0 || {_ctrls isEqualTo []}) exitWith { { ctrlDelete _x; } forEach _ctrls; false };
if (_v isEqualType 0 && {_v > 0}) then { _dur = _v min 5; };
uiNamespace setVariable ["COMSPEC_ATAK_Glitch", [_mode, diag_tickTime, diag_tickTime + _dur, _ctrls, _rect, 0]];
if ((uiNamespace getVariable ["COMSPEC_ATAK_GlitchEh", -1]) < 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_GlitchEh", addMissionEventHandler ["EachFrame", { ["frame"] call comspec_atak_native_fnc_screenGlitch; }]];
};
// Les calques restent devant l'app (le contrôle focalisé passerait devant le bug).
uiNamespace setVariable ["COMSPEC_ATAK_DevFront", true];
[] call comspec_atak_native_fnc_overlayFront;
true
