/*
    comspec_fb_fnc_fx : effets visibles d'UNE détonation, locaux (createVehicleLocal), sur les machines avec interface.

    - Lumière #lightpoint blanc légèrement bleuté, pic sur ~2 images puis décroissance exponentielle (~0,3 s).
      En local fermé, portée coupée à la taille de la pièce + 4 m (les lumières d'Arma traversent les murs).
    - Particules (qualité client) : boule de flash, étincelles et gaz brillants projetés par les évents,
      onde de choc (réfraction), poussière soulevée au sol, fumée blanche légère et brève.
    - DRILL : petite bouffée de fumée et, si le réglage le demande, une lueur faible.
    Hasard (réglage « Variation aléatoire ») : force du flash 60 à 140 %, teinte blanc / blanc-bleu / blanc chaud,
    taille et densité des particules, direction de l'onde de poussière, éclats en plus ; _g = force de la détonation.
    BANG spéciaux : teinte de la lumière et des étincelles tirée à chaque flash dans la palette du thème (teintes[]
    de CfgSons.hpp : JAPAN blanc / rouge, GERMAN doré, WOMEN rose / violet / turquoise, SUPRISE arc-en-ciel).
    Flammes (réglage « Flammes (visuel) », client : Aucune / Légère / Marquée) : courtes langues de gaz incandescents
    orange-blanc par les évents (0,15 - 0,3 s), lueur chaude très atténuée mêlée au flash ; aucun feu, aucun dégât.
    Budget et rafales : au plus 8 lumières à la fois sur ce poste ; une détonation à moins de 0,08 s de la précédente
    de la même grenade ne crée pas de nouvelle lumière ni de flammes : elle relance la lumière en cours (scintillement).
    [_ctx, _posASL, _env, _g] call comspec_fb_fnc_fx
*/
params ["_ctx", "_pos", "_env", ["_g", 1]];
private _k = (_g * (1 + ([0.67] call comspec_fb_fnc_rand))) max 0.3;      // 0,6 à 1,4 à 60 %
private _sz = 1 + ([0.45] call comspec_fb_fnc_rand);                       // taille des particules
private _tint = [[0.93, 0.96, 1], [0.88, 0.93, 1], [1, 0.95, 0.85]] select (floor random 3);
// Étincelles : blanc chaud -> orange (couleurs puis émission) ; BANG spécial : couleur du thème.
private _spark = [[[1, 0.95, 0.85, 1], [1, 0.8, 0.5, 0.8], [1, 0.6, 0.3, 0]], [[40, 35, 25, 1], [8, 5, 2, 0]]];
private _spark2 = [[[1, 0.9, 0.7, 1], [1, 0.7, 0.4, 0.8], [1, 0.5, 0.2, 0]], [[30, 25, 15, 1], [6, 4, 1, 0]]];
private _palette = _ctx getOrDefault ["teintes", []];
if (_palette isNotEqualTo []) then {
    _tint = +(selectRandom _palette);
    _spark = [[_tint + [1], _tint + [0.8], (_tint vectorMultiply 0.7) + [0]], [(_tint vectorMultiply 40) + [1], (_tint vectorMultiply 8) + [0]]];
    _spark2 = _spark;
};
private _kind = _ctx get "kind";
private _q = missionNamespace getVariable ["comspec_fb_quality", 1];
private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _dist = _cam vectorDistance _pos;
if (_dist > 3000) exitWith {};
private _enclosed = _env getOrDefault ["enclosed", false];
private _drill = _kind == "drill";
private _drillFlash = _drill && {missionNamespace getVariable ["comspec_fb_drillFlash", false]};
private _t = diag_tickTime;
private _fused = (_t - (_ctx getOrDefault ["lastLight", -1])) < 0.08;
_ctx set ["lastLight", _t];
if (isNil "comspec_fb_lights") then { comspec_fb_lights = [] };
comspec_fb_lights = comspec_fb_lights select { !isNull (_x select 0) };
private _flames = [0, missionNamespace getVariable ["comspec_fb_flames", 1]] select (!_drill && {!_fused});

// ------------------------------------------------------------------ lumière
private _lightArgs = _ctx getOrDefault ["lightArgs", []];
if ((!_drill || _drillFlash) && {_fused || {count comspec_fb_lights >= 8}} && {_lightArgs isNotEqualTo []} && {!isNull (_lightArgs select 0)}) then {
    // Rafale (ou budget atteint) : la lumière en cours repart à son pic (scintillement), pas de nouvelle source.
    _lightArgs set [1, ((_lightArgs select 1) max (([60000, 1500] select _drill) * _k))];
    _lightArgs set [2, _t];
} else {
if ((!_drill || _drillFlash) && {count comspec_fb_lights < 8}) then {
    private _light = "#lightpoint" createVehicleLocal [0, 0, 0];
    _light setPosASL (_pos vectorAdd [0, 0, 0.35]);
    // Lueur chaude des flammes, très atténuée, mêlée au blanc du flash.
    if (_flames > 0) then { _tint = _tint vectorAdd [0, -0.03 * _flames, -0.08 * _flames] };
    _light setLightColor _tint;
    _light setLightAmbient _tint;
    _light setLightDayLight true;
    _light setLightUseFlare true;
    _light setLightFlareSize ([4, 0.6] select _drill);
    _light setLightFlareMaxDistance 2500;
    private _hard = if (_enclosed) then { (_env getOrDefault ["radius", 5]) + 4 } else { 60 };
    _light setLightAttenuation [0.5, 0, 0, 1, _hard * 0.7, _hard];
    private _i0 = ([60000, 1500] select _drill) * _k;
    _light setLightIntensity _i0;
    private _args = [_light, _i0, _t];
    _ctx set ["lightArgs", _args];
    comspec_fb_lights pushBack _args;
    [{
        params ["_args", "_handle"];
        _args params ["_light", "_i0", "_tStart"];
        private _age = diag_tickTime - _tStart;
        if (isNull _light || {_age > 0.4}) exitWith {
            [_handle] call CBA_fnc_removePerFrameHandler;
            if (!isNull _light) then { deleteVehicle _light };
        };
        // Pic tenu ~0,035 s (2 images), puis décroissance exponentielle (constante 0,07 s).
        _light setLightIntensity (_i0 * (if (_age < 0.035) then { 1 } else { exp (-(_age - 0.035) / 0.07) }));
    }, 0, _args] call CBA_fnc_addPerFrameHandler;
};
};

if (_dist > 1500) exitWith {};

// ------------------------------------------------------------------ particules
// Émetteurs locaux : chaque source émet une courte salve puis est supprimée ; les particules finissent leur vie.
private _emit = {
    params ["_params", "_random", "_interval", "_life"];
    private _src = "#particlesource" createVehicleLocal [0, 0, 0];
    _src setPosASL _pos;
    _src setParticleParams _params;
    _src setParticleRandom _random;
    _src setDropInterval _interval;
    [{ if (!isNull _this) then { deleteVehicle _this } }, _src, _life] call CBA_fnc_waitAndExecute;
};
private _basic = ["\A3\data_f\cl_basic", 1, 0, 1];
private _smoke = ["\A3\data_f\ParticleEffects\Universal\Universal", 16, 7, 48, 1];

// ------------------------------------------------------------------ flammes par les évents (légères)
if (_flames > 0 && {_dist < 800}) then {
    private _fire = ["\A3\data_f\ParticleEffects\Universal\Universal", 16, 10, 32];
    private _count = ([[0, 2, 3], [0, 3, 5], [0, 4, 7]] select _q) select _flames;
    private _len = [0, 1, 1.6] select _flames;
    for "_f" from 1 to _count do {
        private _a = random 360;
        private _sp = (1.2 + random 1.6) * _len * (1 + ([0.6] call comspec_fb_fnc_rand));
        private _life = 0.15 + random 0.15;
        [[_fire, "", "Billboard", 1, _life, [0, 0, 0.1], [_sp * sin _a, _sp * cos _a, 0.4 + random 0.6], 0, 1.2, 1, 0.3,
            [0.12, 0.3, 0.05] apply { _x * _len }, [[1, 0.95, 0.8, 0.9], [1, 0.55, 0.15, 0.7], [0.6, 0.25, 0.05, 0]], [1.5], 0, 0, "", "", ""],
         [0.05, [0.03, 0.03, 0.02], [0.4, 0.4, 0.2], 0, 0.05, [0, 0, 0, 0.05], 0, 0], 0.02, 0.05] call _emit;
    };
    ["fx", format ["flamme=%1 langues=%2 longueur x%3", ["aucune", "légère", "marquée"] select _flames, _count, _len]] call comspec_fb_fnc_log;
};

if (_drill) exitWith {
    // Bouffée de fumée blanche légère, à hauteur du sol.
    [[_smoke, "", "Billboard", 1, 2.5, [0, 0, 0.1], [0, 0, 0.4], 0, 1.275, 1, 0.2, [0.3, 1.2, 2],
        [[0.9, 0.9, 0.9, 0.35], [0.9, 0.9, 0.9, 0.15], [0.9, 0.9, 0.9, 0]], [1], 0.2, 0.1, "", "", ""],
     [1, [0.05, 0.05, 0.02], [0.3, 0.3, 0.2], 0, 0.2, [0, 0, 0, 0.05], 0, 0], 0.05, 0.25] call _emit;
};

// Boule de flash : gros sprite très lumineux, durée 0,12 s.
[[_basic, "", "Billboard", 1, 0.12, [0, 0, 0.25], [0, 0, 0], 0, 1, 1, 0, [1.4, 2.4, 0.4] apply { _x * _sz * (_k ^ 0.5) },
    [[1, 1, 1, 1], [1, 1, 1, 0.6], [1, 1, 1, 0]], [1], 0, 0, "", "", "", 0, false, 0, [[60, 60, 60, 1], [10, 10, 10, 0]]],
 [0, [0, 0, 0], [0, 0, 0], 0, 0.2, [0, 0, 0, 0], 0, 0], 0.04, 0.05] call _emit;

// Onde de choc (réfraction) qui s'étale au ras du sol.
if (_q >= 1) then {
    [[["\A3\data_f\ParticleEffects\Universal\Refract", 1, 0, 1], "", "Billboard", 1, 0.25, [0, 0, 0.3], [0, 0, 0], 0, 1, 1, 0,
        [0.5, 4.5], [[1, 1, 1, 0.9], [1, 1, 1, 0]], [1], 0, 0, "", "", ""],
     [0, [0, 0, 0], [0, 0, 0], 0, 0, [0, 0, 0, 0], 0, 0], 0.1, 0.05] call _emit;
};

// Étincelles et gaz brillants projetés par les évents (nombre selon la qualité).
private _sparkInterval = ([0.02, 0.008, 0.004] select _q) / (_k max 0.5);
[[_basic, "", "Billboard", 1, 0.35, [0, 0, 0.1], [0, 0, 1.5], 0, 3, 1, 0.3, [0.06, 0.03, 0.01],
    _spark select 0, [1], 0.05, 0.3, "", "", "", 0, false, 0.3,
    _spark select 1],
 [0.2, [0.05, 0.05, 0.05], [7, 7, 4], 0, 0.02, [0, 0, 0, 0], 0, 0], _sparkInterval, 0.06] call _emit;

// Poussière soulevée au sol (dehors) ou débris légers (dedans), puis fumée blanche brève.
if (_q >= 1) then {
    [[_smoke, "", "Billboard", 1, 1.2, [0, 0, 0.05], [0, 0, 0.2], 0, 1.3, 1, 0.5, [0.4, 1.6, 2.4],
        [[0.62, 0.58, 0.5, 0.35], [0.62, 0.58, 0.5, 0.15], [0.62, 0.58, 0.5, 0]], [1], 0.1, 0.1, "", "", ""],
     [0.3, [0.2, 0.2, 0], [2.5, 2.5, 0.2] apply { _x * _sz }, 0, 0.3, [0, 0, 0, 0.05], 0, 0], [0.04, 0.02] select (_q - 1), 0.1] call _emit;
};
[[_smoke, "", "Billboard", 1, [2.5, 4] select (_q > 0), [0, 0, 0.3], [0, 0, 0.5], 0, 1.27, 1, 0.1, [0.4, 1.4, 2.4],
    [[0.95, 0.95, 0.95, 0.4], [0.95, 0.95, 0.95, 0.2], [0.95, 0.95, 0.95, 0]], [1], 0.3, 0.2, "", "", ""],
 [0.6, [0.1, 0.1, 0.05], [0.4, 0.4, 0.3], 0, 0.3, [0, 0, 0, 0.05], 0, 0], [0.08, 0.05, 0.03] select _q, 0.15] call _emit;

// Éclats et étincelles en plus, en nombre, vitesse et direction tirés au hasard (une rafale courte).
if (_q >= 1 && {random 1 < 0.4 + 0.5 * (missionNamespace getVariable ["comspec_fb_variation", 0.6])}) then {
    private _a = random 360;
    private _sp = 3 + random 5;
    [[_basic, "", "Billboard", 1, 0.5 + random 0.4, [0, 0, 0.15], [_sp * sin _a, _sp * cos _a, 1 + random 2], 0, 4, 1, 0.2,
        [0.04, 0.02, 0.01], _spark2 select 0, [1], 0.1, 0.3, "", "", "", 0, false, 0.4,
        _spark2 select 1],
     [0.2, [0.05, 0.05, 0.05], [4, 4, 3], 0, 0.02, [0, 0, 0, 0], 0, 0], 0.01 + random 0.02, 0.05 + random 0.06] call _emit;
};
