/*
    Voyants des caméras IP proches (client) : voyant d'état (sélection 1), LED infrarouges la nuit (sélection 2), verre
    noirci et étincelles quand la caméra est détruite (sélection 3). Une passe toutes les 3 s sur les caméras du registre
    à moins de 80 m ; textures locales (setObjectTexture), rien n'est diffusé. Lancée une fois au postInit client.
      vert : en service · ambre : brouillée · éteint : détruite
*/
if (!hasInterface) exitWith { false };
if (!isNil { missionNamespace getVariable "COMSPEC_ATAK_CamLedPfh" }) exitWith { false };
missionNamespace setVariable ["COMSPEC_ATAK_CamLedPfh", [{
    if (isNil "comspec_atak_native_fnc_camAction") exitWith {};
    private _ref = positionCameraToWorld [0, 0, 0];
    private _night = sunOrMoon < 0.5;
    {
        if (_x isEqualType objNull && {!isNull _x} && {(_x distance _ref) < 80}) then {
            private _dead = ["dead", _x] call comspec_atak_native_fnc_camAction;
            private _code = if (_dead) then { "DEAD" } else { (["status", _x] call comspec_atak_native_fnc_camAction) select 0 };
            private _st = [[["OK", "JAM"] select (_code isEqualTo "JAM"), "DEAD"] select _dead, _night];
            private _old = _x getVariable ["COMSPEC_ATAK_CamLedSt", ["", false]];
            if (_st isNotEqualTo _old) then {
                _x setVariable ["COMSPEC_ATAK_CamLedSt", _st];
                _x setObjectTexture [1, switch (_st select 0) do {
                    case "OK": { "#(argb,8,8,3)color(0.1,0.9,0.2,1,co)" };
                    case "JAM": { "#(argb,8,8,3)color(0.95,0.55,0.05,1,co)" };
                    default { "#(argb,8,8,3)color(0.02,0.02,0.02,1,co)" };
                }];
                _x setObjectTexture [2, ["#(argb,8,8,3)color(0.02,0.02,0.02,1,co)", "#(argb,8,8,3)color(0.9,0.05,0.04,1,co)"] select (_night && {!_dead})];
                if (_dead) then {
                    _x setObjectTexture [3, "#(argb,8,8,3)color(0.06,0.055,0.05,1,co)"];
                    _x setObjectMaterial [3, "\z\comspec_atak_native\addons\items\data\cam\cam_dead.rvmat"];
                    // Étincelles au moment de la destruction (pas à l'arrivée près d'une caméra déjà détruite).
                    if ((_old select 0) in ["OK", "JAM"]) then {
                        private _ps = "#particlesource" createVehicleLocal (getPosATL _x);
                        _ps setParticleParams [["\A3\data_f\ParticleEffects\Universal\Universal", 16, 13, 2, 0], "", "Billboard", 1, 0.45, [0, 0, 0], [0, 0, 1.2], 0, 12, 1, 0,
                            [0.035, 0.012], [[1, 0.85, 0.5, 1], [1, 0.5, 0.1, 0]], [1000], 0, 0, "", "", _x, 0, false, 0.3, [[60, 40, 10, 1]]];
                        _ps setParticleRandom [0.2, [0.03, 0.03, 0.03], [1.4, 1.4, 1], 0, 0.01, [0, 0, 0, 0], 0, 0];
                        _ps setDropInterval 0.012;
                        [{ deleteVehicle _this; }, _ps, 0.6] call CBA_fnc_waitAndExecute;
                    };
                };
            };
        };
    } forEach (missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]);
}, 3] call CBA_fnc_addPerFrameHandler];
true
