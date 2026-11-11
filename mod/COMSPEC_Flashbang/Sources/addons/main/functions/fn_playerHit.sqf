/*
    comspec_fb_fnc_playerHit : une détonation atteint le joueur local. Met à jour les canaux d'effet, puis fn_playerLoop
    (lancée seulement si elle ne tourne pas déjà) applique et fait récupérer le tout.

    Canaux (préréglage Réaliste, exposition 1 ; durée x exposition^0,6 x facteur du préréglage) :
      white  éblouissement (voile blanc, contraste et couleurs réduits) : 12 s ; plein blanc ~1,5 s puis blanc qui
             s'efface en ~1,5 s, vision très dégradée jusqu'à ~6-8 s, récupération jusqu'à ~12 s
      blur   perte d'acuité (flou fort au début, comparable aux autres mods de flashbang) : 12 s
      spots  image rémanente (grande) au point de l'écran où était le flash : jusqu'à 25 s (positive puis sombre)
      aim    désorientation : visée très instable (setCustomAimCoef), pas plus lents (setAnimSpeedCoef jusqu'à 0,8),
             oscillation lente de la caméra (addCamShake basse fréquence), sans paralysie ni chute : 10 s
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
// Rafale (flashs à moins de 0,25 s) : effet stroboscopique du blanc pendant le train de flashs.
private _prevHit = _st getOrDefault ["lastHit", -10];
if (_now - _prevHit < 0.25) then { _st set ["strobeUntil", _now + 0.3] };
_st set ["lastHit", _now];
// Instants pour le journal (premier flash d'un train) : prévu (horloge commune), reçu ici ; l'application à l'écran est
// notée à l'image suivante.
if (_now - _prevHit > 1) then { _st set ["hitAt", [_info getOrDefault ["tPlan", -1], call comspec_fb_fnc_clock, diag_tickTime]] };
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

// Hasard de cette détonation (« Variation aléatoire », 60 % : durée du blanc +/-25 %, flou +/-18 % et courbe de
// retour variable, début de la surdité +/-20 %, teinte du voile) : jamais deux expositions identiques.
private _rnd = {
    params ["_a"];
    1 + ([_a] call comspec_fb_fnc_rand)
};
private _kW = [0.42] call _rnd;
private _kB = [0.3] call _rnd;
private _kH = [0.33] call _rnd;
_st set ["blurExp", 1.5 * ([0.3] call _rnd)];
_st set ["tint", selectRandom [[1, 1, 1], [0.94, 0.97, 1], [1, 0.97, 0.9]]];
_st set ["phase", [random 6.28, random 6.28, random 6.28, random 6.28]];

// ------------------------------------------------------------------ vision
if (missionNamespace getVariable ["comspec_fb_flash", true]) then {
    ["white", _vis, 12 * _pd * _kW, 0.12, _cap, 1.3] call _bump;
    if (_info getOrDefault ["optic", false]) then { ["flare", _vis, 3, 0.1, 6, 2] call _bump };
    if (_info getOrDefault ["nvg", false]) then { _st set ["nvgUntil", _now + (8 * _pd * ((_vis min 1.5) ^ 0.6))] };
    // Image rémanente : seulement si le flash était dans le champ de vision et visible directement.
    if (_vis > 0.15 && {!(_info getOrDefault ["blocked", false])}) then {
        private _scr = worldToScreen (ASLToAGL _pos);
        if (_scr isNotEqualTo [] && {(_scr select 0) > safeZoneX} && {(_scr select 0) < (safeZoneX + safeZoneW)}
            && {(_scr select 1) > safeZoneY} && {(_scr select 1) < (safeZoneY + safeZoneH)}) then {
            private _spots = _st getOrDefault ["spots", []];
            private _peak = _vis min 1;
            private _dur = (((25 * _pd * ((_vis min 1.5) ^ 0.6)) min 45) max 6) * ([0.3] call _rnd);
            // Dérive lente de la tache dans une direction tirée au hasard, taille variable.
            private _a = random 360;
            private _drift = [0.004 * sin _a, 0.004 * cos _a] apply { _x * (missionNamespace getVariable ["comspec_fb_variation", 0.6]) / 0.6 };
            private _i = _spots findIf { ((_x select [0, 2]) distanceSqr _scr) < 0.004 };
            if (_i >= 0) then {
                private _old = _spots select _i;
                private _cur = [[_old select 2, _old select 3, _old select 4, 0.03, 1.5], _now] call comspec_fb_fnc_chanValue;
                _spots set [_i, [_scr select 0, _scr select 1, ((_cur max _peak) + 0.15 * (_cur min _peak)) min 1, _now,
                    (_dur max (((_old select 3) + (_old select 4)) - _now)) min 45, _drift, [0.3] call _rnd]];
            } else {
                if (count _spots >= 3) then { _spots deleteAt 0 };
                _spots pushBack [_scr select 0, _scr select 1, _peak, _now, _dur, _drift, [0.3] call _rnd];
                // 1 à 3 taches : satellites plus petits et plus faibles autour du point du flash.
                if (_peak > 0.5 && {count _spots < 3} && {random 1 < 0.6 * (missionNamespace getVariable ["comspec_fb_variation", 0.6])}) then {
                    private _b = random 360;
                    _spots pushBack [(_scr select 0) + 0.05 * sin _b, (_scr select 1) + 0.05 * cos _b, _peak * 0.5, _now, _dur * 0.6, _drift, 0.55];
                };
            };
            _st set ["spots", _spots];
        };
    };
};
if (missionNamespace getVariable ["comspec_fb_blur", true]) then {
    ["blur", (_vis * 0.9 * _kB) min 1.2, 12 * _pd * _kW, 0.15, _cap + 5, _st getOrDefault ["blurExp", 1.5]] call _bump;
};
["aim", (_vis * 0.9) max (_aud * 0.7), 10 * _pd * ([0.3] call _rnd), 0.3, _cap, 1.5] call _bump;

// ------------------------------------------------------------------ audition
if (missionNamespace getVariable ["comspec_fb_muffle", true]) then {
    // L'ouïe ne s'étouffe qu'après le pic du bang (0,3 s +/-20 %) : le bang lui-même reste entier.
    ["hear", _aud, 35 * _pd * _kH, 0.15, 60, 1.6, 0.3 * _kH] call _bump;
};
[_aud] call comspec_fb_fnc_tinnitus;
// Souffle de l'onde de pression : secousse brève de la caméra, proportionnelle au bruit reçu.
if (_aud > 0.1) then { addCamShake [_aud * 4 * ([0.5] call _rnd), (0.4 + 0.6 * (_aud min 1)) * ([0.4] call _rnd), 14 + random 8] };
// Désorientation : oscillation lente et décroissante de la vue (vertige), d'autant plus forte que l'exposition l'est.
private _dis = (_vis max _aud) min 1.2;
if (_dis > 0.25) then { addCamShake [(1.2 + 2.3 * _dis) * ([0.5] call _rnd), (4 + 5 * _dis) * ([0.3] call _rnd), 1.2 + random 0.8] };

// Journal : premier flash d'un train (une ligne, pas une par flash).
if (_now - _prevHit > 1) then {
    ["joueur", format ["vision=%1 audition=%2 dist=%3 angle=%4 nvg=%5 optique=%6 mur=%7 tirages : blanc x%8 flou x%9 ouïe x%10 sensibilité x%11", _vis toFixed 2, _aud toFixed 2,
        (_info get "dist") toFixed 1, (_info getOrDefault ["angle", -1]) toFixed 0, _info getOrDefault ["nvg", false],
        _info getOrDefault ["optic", false], _info getOrDefault ["blocked", false], _kW toFixed 2, _kB toFixed 2, _kH toFixed 2,
        (missionNamespace getVariable ["comspec_fb_sens", 1]) toFixed 2]] call comspec_fb_fnc_log;
};

// ------------------------------------------------------------------ boucle d'effet (une seule)
if ((_st getOrDefault ["pfh", -1]) < 0) then {
    private _orig = createHashMapFromArray [
        ["sound", soundVolume], ["radio", radioVolume], ["aim", getCustomAimCoef _unit], ["anim", getAnimSpeedCoef _unit]
    ];
    if (!isNil "acre_api_fnc_getGlobalVolume") then { _orig set ["acre", call acre_api_fnc_getGlobalVolume] };
    if (isClass (configFile >> "CfgPatches" >> "tfar_core") || {isClass (configFile >> "CfgPatches" >> "task_force_radio")}) then {
        _orig set ["tfar", _unit getVariable ["tf_globalVolume", 1]];
    };
    _st set ["orig", _orig];
    _st set ["pfh", [comspec_fb_fnc_playerLoop, 0.03, []] call CBA_fnc_addPerFrameHandler];
};
