/*
    comspec_fb_fnc_fx : effets visibles d'UNE détonation, locaux (createVehicleLocal), sur les machines avec interface.

    - Lumière #lightpoint blanc légèrement bleuté, pic sur ~2 images puis décroissance exponentielle (~0,3 s).
      En local fermé, portée coupée à la taille de la pièce + 4 m (les lumières d'Arma traversent les murs).
    - Particules (qualité client) : boule de flash, étincelles et gaz brillants projetés par les évents,
      onde de choc (réfraction), poussière soulevée au sol, fumée blanche légère et brève.
    - DRILL : petite bouffée de fumée et, si le réglage le demande, une lueur faible.
    [_ctx, _posASL, _env] call comspec_fb_fnc_fx
*/
params ["_ctx", "_pos", "_env"];
private _kind = _ctx get "kind";
private _q = missionNamespace getVariable ["comspec_fb_quality", 1];
private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _dist = _cam vectorDistance _pos;
if (_dist > 3000) exitWith {};
private _enclosed = _env getOrDefault ["enclosed", false];
private _drill = _kind == "drill";
private _drillFlash = _drill && {missionNamespace getVariable ["comspec_fb_drillFlash", false]};

// ------------------------------------------------------------------ lumière
if (!_drill || _drillFlash) then {
    private _light = "#lightpoint" createVehicleLocal [0, 0, 0];
    _light setPosASL (_pos vectorAdd [0, 0, 0.35]);
    _light setLightColor [0.93, 0.96, 1];
    _light setLightAmbient [0.93, 0.96, 1];
    _light setLightDayLight true;
    _light setLightUseFlare true;
    _light setLightFlareSize ([4, 0.6] select _drill);
    _light setLightFlareMaxDistance 2500;
    private _hard = if (_enclosed) then { (_env getOrDefault ["radius", 5]) + 4 } else { 60 };
    _light setLightAttenuation [0.5, 0, 0, 1, _hard * 0.7, _hard];
    private _i0 = [60000, 1500] select _drill;
    _light setLightIntensity _i0;
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
    }, 0, [_light, _i0, diag_tickTime]] call CBA_fnc_addPerFrameHandler;
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

if (_drill) exitWith {
    // Bouffée de fumée blanche légère, à hauteur du sol.
    [[_smoke, "", "Billboard", 1, 2.5, [0, 0, 0.1], [0, 0, 0.4], 0, 1.275, 1, 0.2, [0.3, 1.2, 2],
        [[0.9, 0.9, 0.9, 0.35], [0.9, 0.9, 0.9, 0.15], [0.9, 0.9, 0.9, 0]], [1], 0.2, 0.1, "", "", ""],
     [1, [0.05, 0.05, 0.02], [0.3, 0.3, 0.2], 0, 0.2, [0, 0, 0, 0.05], 0, 0], 0.05, 0.25] call _emit;
};

// Boule de flash : gros sprite très lumineux, durée 0,12 s.
[[_basic, "", "Billboard", 1, 0.12, [0, 0, 0.25], [0, 0, 0], 0, 1, 1, 0, [1.4, 2.4, 0.4],
    [[1, 1, 1, 1], [1, 1, 1, 0.6], [1, 1, 1, 0]], [1], 0, 0, "", "", "", 0, false, 0, [[60, 60, 60, 1], [10, 10, 10, 0]]],
 [0, [0, 0, 0], [0, 0, 0], 0, 0.2, [0, 0, 0, 0], 0, 0], 0.04, 0.05] call _emit;

// Onde de choc (réfraction) qui s'étale au ras du sol.
if (_q >= 1) then {
    [[["\A3\data_f\ParticleEffects\Universal\Refract", 1, 0, 1], "", "Billboard", 1, 0.25, [0, 0, 0.3], [0, 0, 0], 0, 1, 1, 0,
        [0.5, 4.5], [[1, 1, 1, 0.9], [1, 1, 1, 0]], [1], 0, 0, "", "", ""],
     [0, [0, 0, 0], [0, 0, 0], 0, 0, [0, 0, 0, 0], 0, 0], 0.1, 0.05] call _emit;
};

// Étincelles et gaz brillants projetés par les évents (nombre selon la qualité).
private _sparkInterval = [0.02, 0.008, 0.004] select _q;
[[_basic, "", "Billboard", 1, 0.35, [0, 0, 0.1], [0, 0, 1.5], 0, 3, 1, 0.3, [0.06, 0.03, 0.01],
    [[1, 0.95, 0.85, 1], [1, 0.8, 0.5, 0.8], [1, 0.6, 0.3, 0]], [1], 0.05, 0.3, "", "", "", 0, false, 0.3,
    [[40, 35, 25, 1], [8, 5, 2, 0]]],
 [0.2, [0.05, 0.05, 0.05], [7, 7, 4], 0, 0.02, [0, 0, 0, 0], 0, 0], _sparkInterval, 0.06] call _emit;

// Poussière soulevée au sol (dehors) ou débris légers (dedans), puis fumée blanche brève.
if (_q >= 1) then {
    [[_smoke, "", "Billboard", 1, 1.2, [0, 0, 0.05], [0, 0, 0.2], 0, 1.3, 1, 0.5, [0.4, 1.6, 2.4],
        [[0.62, 0.58, 0.5, 0.35], [0.62, 0.58, 0.5, 0.15], [0.62, 0.58, 0.5, 0]], [1], 0.1, 0.1, "", "", ""],
     [0.3, [0.2, 0.2, 0], [2.5, 2.5, 0.2], 0, 0.3, [0, 0, 0, 0.05], 0, 0], [0.04, 0.02] select (_q - 1), 0.1] call _emit;
};
[[_smoke, "", "Billboard", 1, [2.5, 4] select (_q > 0), [0, 0, 0.3], [0, 0, 0.5], 0, 1.27, 1, 0.1, [0.4, 1.4, 2.4],
    [[0.95, 0.95, 0.95, 0.4], [0.95, 0.95, 0.95, 0.2], [0.95, 0.95, 0.95, 0]], [1], 0.3, 0.2, "", "", ""],
 [0.6, [0.1, 0.1, 0.05], [0.4, 0.4, 0.3], 0, 0.3, [0, 0, 0, 0.05], 0, 0], [0.08, 0.05, 0.03] select _q, 0.15] call _emit;
