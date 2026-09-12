/*
    Illumination locale d’une zone sous le regard (lumière verte type IR).
    Params: ["toggle"|"clear"|"place"]
*/
params [["_mode", "toggle", [""]]];
if (!hasInterface) exitWith {};
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith {
    ["Affichage situation indisponible.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _fnc_clear = {
    private _old = missionNamespace getVariable ["COMSPEC_EcotiZoneLight", objNull];
    if (!isNull _old) then { deleteVehicle _old; };
    missionNamespace setVariable ["COMSPEC_EcotiZoneLight", objNull, false];
    missionNamespace setVariable ["COMSPEC_EcotiZoneLightPos", [], false];
};

if (_mode isEqualTo "clear") exitWith {
    [] call _fnc_clear;
    ["Éclairage de zone éteint.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

if (_mode isEqualTo "toggle") then {
    private _cur = missionNamespace getVariable ["COMSPEC_EcotiZoneLight", objNull];
    if (!isNull _cur) exitWith {
        [] call _fnc_clear;
        ["Éclairage de zone éteint.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
    };
};

private _camPos = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _camEnd = AGLToASL (positionCameraToWorld [0, 0, 80]);
private _hits = lineIntersectsSurfaces [_camPos, _camEnd, player, vehicle player, true, 1, "GEOM", "NONE"];
private _posAGL = positionCameraToWorld [0, 0, 25];
if ((count _hits) > 0) then {
    private _asl = (_hits select 0) select 0;
    _posAGL = ASLToAGL _asl;
};

[] call _fnc_clear;

private _light = "#lightpoint" createVehicleLocal _posAGL;
_light setLightBrightness 0.55;
_light setLightAmbient [0.04, 0.28, 0.08];
_light setLightColor [0.15, 0.95, 0.35];
_light setLightAttenuation [2, 0, 0, 4, 8, 55];
_light setLightUseFlare true;
_light setLightFlareSize 0.35;
_light setLightFlareMaxDistance 120;

missionNamespace setVariable ["COMSPEC_EcotiZoneLight", _light, false];
missionNamespace setVariable ["COMSPEC_EcotiZoneLightPos", _posAGL, false];
["Zone éclairée sous le regard (affichage situation).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
