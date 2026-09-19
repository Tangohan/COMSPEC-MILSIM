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

[
    "COMSPEC Roleplay",
    "Relais ATAK (mât)",
    {
        params ["_pos", "_obj"];
        if (!(_pos isEqualType []) || {count _pos < 2}) then { _pos = [0, 0, 0]; };
        if (!isNull _obj) then { _pos = getPosATL _obj; };
        [
            "Relais ATAK",
            [
                ["EDIT", ["Nom", "Nom affiché sur Relais AT et au poste."], ["Relais Nord"]],
                ["SLIDER", ["Portée (m)", "Rayon dans lequel un téléphone peut s’appuyer sur ce mât."], [50, 8000, 2000, 0]],
                ["EDIT", ["Identité", "Indicatif réseau du mât."], ["RLY-01"]],
                ["EDIT", ["Adresse réseau", "Vide = générée à la pose."], [""]],
                ["EDIT", ["Passerelle", "Vide = générée à la pose."], [""]],
                ["EDIT", ["Certificat", "Libellé du certificat de liaison."], ["Certificat de relais — valable mission"]],
                ["SLIDER", ["Places", "Téléphones simultanés."], [1, 32, 8, 0]],
                ["SLIDER", ["Puissance (W)", "Passe à zéro si le mât est détruit."], [1, 200, 25, 0]],
                ["SLIDER", ["Débit (Mbit/s)", "Baisse avec la distance et les dégâts."], [1, 100, 12, 0]],
                ["SLIDER", ["Fiabilité (%)", "Baisse hors portée ou sous les tirs."], [10, 100, 92, 0]]
            ],
            {
                params ["_values", "_args"];
                _values params ["_name", "_range", "_identity", "_ip", "_gw", "_cert", "_slots", "_power", "_thru", "_rel"];
                _args params ["_pos"];
                if (!(_name isEqualType "") || {_name isEqualTo ""}) then { _name = "Relais ATAK"; };
                private _meta = createHashMap;
                _meta set ["identity", _identity];
                _meta set ["ip", _ip];
                _meta set ["gateway", _gw];
                _meta set ["certificate", _cert];
                _meta set ["slots", _slots];
                _meta set ["power_w", _power];
                _meta set ["throughput_mbps", _thru];
                _meta set ["reliability_pct", _rel];
                [_pos, _range, _name, _meta] call comspec_overwatch_connect_fnc_placeAtakRelay;
            },
            {},
            [_pos]
        ] call zen_dialog_fnc_create;
    },
    "\A3\ui_f\data\map\markers\military\flag_CA.paa"
] call zen_custom_modules_fnc_register;

missionNamespace setVariable ["COMSPEC_ZenRoleplayModulesRegistered", true];
