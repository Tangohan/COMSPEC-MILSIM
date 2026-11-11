/*
    comspec_fb_fnc_playerHit : une détonation atteint le joueur local. Met à jour les canaux d'effet, puis fn_playerLoop
    (lancée seulement si elle ne tourne pas déjà) applique et fait récupérer le tout.

    Canaux (préréglage Réaliste, exposition 1 ; durée x exposition^0,6 x facteur du préréglage) :
      white  éblouissement (voile blanc, contraste et couleurs réduits) : 6 s, plein blanc ~1,2 s
      blur   perte d'acuité (voile de diffusion)                         : 10 s
      spots  image rémanente au point de l'écran où était le flash      : jusqu'à 25 s (positive puis sombre)
      aim    visée instable (setCustomAimCoef)                           : 8 s
      hear   ouïe étouffée, démarre 0,3 s après le bang (on l'entend)    : 35 s
      flare  halo dans l'optique / les jumelles                          : 3 s
    Empilement de N flashs : chaque flash remonte le niveau (pic + 15 % du plus faible des deux) et prolonge la durée
    restante de moitié, sans jamais dépasser le plafond (réglage « Cécité cumulée max », 15 s par défaut).
    [_vis, _aud, _info, _posASL] call comspec_fb_fnc_playerHit
*/
params ["_vis", "_aud", "_info", "_pos"];
private _st = comspec_fb_state;
private _unit = call CBA_fnc_currentUnit;
if ((_st getOrDefault ["unit", objNull]) != _unit) then {
    [] call comspec_fb_fnc_playerReset;
    _st set ["unit", _unit];
};
private _now = CBA_missionTime;
private _pd = _info getOrDefault ["durMult", 1];
private _cap = (missionNamespace getVariable ["comspec_fb_maxStack", 15]) * (_pd max 1);

private _bump = {
    params ["_ch", "_s", "_dBase", "_hold", "_capC", "_exp", ["_delay", 0]];
    if (_s <= 0.01) exitWith {};
    private _c = _st getOrDefault [_ch, []];
    private _cur = [_c, _now] call comspec_fb_fnc_chanValue;
    private _rem = if (_c isEqualTo []) then { 0 } else { (((_c select 1) + (_c select 2)) - _now) max 0 };
    private _np = ((_cur max _s) + 0.15 * (_cur min _s)) min 1;
    private _nd = (((_dBase * ((_s min 1.5) ^ 0.6)) + 0.5 * _rem) max _rem) min _capC;
    _st set [_ch, [_np, _now + _delay, _nd max 0.3, _hold, _exp, _cur]];
};

// ------------------------------------------------------------------ vision
if (missionNamespace getVariable ["comspec_fb_flash", true]) then {
    ["white", _vis, 6 * _pd, 0.2, _cap, 2.2] call _bump;
    if (_info getOrDefault ["optic", false]) then { ["flare", _vis, 3, 0.1, 6, 2] call _bump };
    if (_info getOrDefault ["nvg", false]) then { _st set ["nvgUntil", _now + (8 * _pd * ((_vis min 1.5) ^ 0.6))] };
    // Image rémanente : seulement si le flash était dans le champ de vision et visible directement.
    if (_vis > 0.15 && {!(_info getOrDefault ["blocked", false])}) then {
        private _scr = worldToScreen (ASLToAGL _pos);
        if (_scr isNotEqualTo [] && {(_scr select 0) > safeZoneX} && {(_scr select 0) < (safeZoneX + safeZoneW)}
            && {(_scr select 1) > safeZoneY} && {(_scr select 1) < (safeZoneY + safeZoneH)}) then {
            private _spots = _st getOrDefault ["spots", []];
            private _peak = _vis min 1;
            private _dur = ((25 * _pd * ((_vis min 1.5) ^ 0.6)) min 45) max 4;
            private _i = _spots findIf { ((_x select [0, 2]) distanceSqr _scr) < 0.004 };
            if (_i >= 0) then {
                private _old = _spots select _i;
                private _cur = [[_old select 2, _old select 3, _old select 4, 0.03, 1.5], _now] call comspec_fb_fnc_chanValue;
                _spots set [_i, [_scr select 0, _scr select 1, ((_cur max _peak) + 0.15 * (_cur min _peak)) min 1, _now,
                    (_dur max (((_old select 3) + (_old select 4)) - _now)) min 45]];
            } else {
                if (count _spots >= 3) then { _spots deleteAt 0 };
                _spots pushBack [_scr select 0, _scr select 1, _peak, _now, _dur];
            };
            _st set ["spots", _spots];
        };
    };
};
if (missionNamespace getVariable ["comspec_fb_blur", true]) then {
    ["blur", _vis * 0.9, 10 * _pd, 0.15, _cap + 5, 1.5] call _bump;
};
["aim", (_vis * 0.8) max (_aud * 0.6), 8 * _pd, 0.3, _cap, 1.5] call _bump;

// ------------------------------------------------------------------ audition
if (missionNamespace getVariable ["comspec_fb_muffle", true]) then {
    ["hear", _aud, 35 * _pd, 0.15, 60, 1.6, 0.3] call _bump;
};
[_aud] call comspec_fb_fnc_tinnitus;
// Souffle de l'onde de pression : secousse brève de la caméra, proportionnelle au bruit reçu.
if (_aud > 0.1) then { addCamShake [_aud * 4, 0.4 + 0.6 * (_aud min 1), 18] };

["joueur", format ["vision=%1 audition=%2 dist=%3 angle=%4 nvg=%5 optique=%6 mur=%7", _vis toFixed 2, _aud toFixed 2,
    (_info get "dist") toFixed 1, (_info getOrDefault ["angle", -1]) toFixed 0, _info getOrDefault ["nvg", false],
    _info getOrDefault ["optic", false], _info getOrDefault ["blocked", false]]] call comspec_fb_fnc_log;

// ------------------------------------------------------------------ boucle d'effet (une seule)
if ((_st getOrDefault ["pfh", -1]) < 0) then {
    private _orig = createHashMapFromArray [
        ["sound", soundVolume], ["radio", radioVolume], ["aim", getCustomAimCoef _unit]
    ];
    if (!isNil "acre_api_fnc_getGlobalVolume") then { _orig set ["acre", call acre_api_fnc_getGlobalVolume] };
    if (isClass (configFile >> "CfgPatches" >> "tfar_core") || {isClass (configFile >> "CfgPatches" >> "task_force_radio")}) then {
        _orig set ["tfar", _unit getVariable ["tf_globalVolume", 1]];
    };
    _st set ["orig", _orig];
    _st set ["pfh", [comspec_fb_fnc_playerLoop, 0.03, []] call CBA_fnc_addPerFrameHandler];
};
