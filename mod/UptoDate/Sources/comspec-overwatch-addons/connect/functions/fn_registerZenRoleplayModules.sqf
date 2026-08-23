/*
    Enregistre les zones roleplay ATAK dans Zeus Enhanced (ZEN) si présent.
    Placement fiable : carte, objet ou unité sous le curseur.
*/
if (!hasInterface) exitWith {};
if (isNil "zen_custom_modules_fnc_register") exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZenRoleplayModulesRegistered", false]) exitWith {};

private _icon = "\A3\ui_f\data\map\markers\military\warning_CA.paa";

private _place = {
    params ["_pos", "_obj", "_type", "_defaultRadius", "_defaultIntensity"];
    if (!(_pos isEqualType []) || {count _pos < 2}) then { _pos = [0, 0, 0]; };
    if (!isNull _obj) then { _pos = getPosATL _obj; };

    private _title = switch (_type) do {
        case "no_coverage": { "Zone sans couverture ATAK" };
        case "interference": { "Zone d'interférence ATAK" };
        case "degraded": { "Zone de couverture dégradée" };
        case "jammer": { "Brouilleur ATAK actif" };
        default { "Zone ATAK" };
    };

    [
        _title,
        [
            ["SLIDER", ["Rayon (m)", "Étendue de la zone autour du point ou de l’unité."], [5, 2000, _defaultRadius, 0]],
            ["SLIDER", ["Intensité (%)", "Force de l’effet sur la liaison ATAK."], [0, 100, _defaultIntensity, 0]]
        ],
        {
            params ["_values", "_args"];
            _values params ["_radius", "_intensity"];
            _args params ["_pos", "_obj", "_type"];
            if (!isNull _obj) then { _pos = getPosATL _obj; };
            [_pos, _radius, _type, _intensity, _obj] remoteExecCall ["comspec_overwatch_connect_fnc_createRoleplayZoneFromZeus", 2];
        },
        {},
        [_pos, _obj, _type]
    ] call zen_dialog_fnc_create;
};

missionNamespace setVariable ["COMSPEC_ZenPlaceRoleplayZone", _place];

[
    "COMSPEC Roleplay",
    "Zone sans couverture ATAK",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "no_coverage", 200, 100] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call zen_custom_modules_fnc_register;

[
    "COMSPEC Roleplay",
    "Zone d'interférence ATAK",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "interference", 300, 50] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call zen_custom_modules_fnc_register;

[
    "COMSPEC Roleplay",
    "Zone de couverture dégradée",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "degraded", 500, 30] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call zen_custom_modules_fnc_register;

[
    "COMSPEC Roleplay",
    "Brouilleur ATAK actif",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "jammer", 400, 80] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call zen_custom_modules_fnc_register;

missionNamespace setVariable ["COMSPEC_ZenRoleplayModulesRegistered", true];
