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
        case "interference": { "Poser une zone d'interférence" };
        case "degraded": { "Poser une zone dégradée" };
        case "jammer": { "Poser un brouilleur fixe" };
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
    "COMSPEC_Cat_Atak",
    "Poser une zone sans couverture",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "no_coverage", 200, 100] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call comspec_overwatch_connect_fnc_zenModule;

[
    "COMSPEC_Cat_Atak",
    "Poser une zone d'interférence",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "interference", 300, 50] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call comspec_overwatch_connect_fnc_zenModule;

[
    "COMSPEC_Cat_Atak",
    "Poser une zone dégradée",
    {
        params ["_pos", "_obj"];
        [_pos, _obj, "degraded", 500, 30] call (missionNamespace getVariable "COMSPEC_ZenPlaceRoleplayZone");
    },
    _icon
] call comspec_overwatch_connect_fnc_zenModule;

[
    "COMSPEC_Cat_Atak",
    "Poser un émetteur RF",
    {
        params ["_pos", "_obj"];
        if (!(_pos isEqualType []) || {count _pos < 2}) then { _pos = [0, 0, 0]; };
        if (!isNull _obj) then { _pos = getPosATL _obj; };
        [
            "Émetteur RF Fieldwatch",
            [
                ["EDIT", ["Nom / SSID", "Nom affiché dans Fieldwatch."], ["IPCam-Lobby"]],
                ["COMBO", ["Bande", "Type d’émission simulée."], [
                    ["wifi", "ble", "tracker", "camera", "phone"],
                    ["Wi‑Fi", "Bluetooth LE", "Tracker", "Caméra IP", "Téléphone"],
                    0
                ]],
                ["EDIT", ["Signature", "Identifiant de catalogue."], ["wifi_ipcam"]],
                ["SLIDER", ["Portée scan (m)", "Distance max de détection."], [10, 300, 80, 0]],
                ["SLIDER", ["Puissance (dBm)", "Puissance à la source."], [-20, 20, 10, 0]],
                ["EDIT", ["MAC simulée", "Vide = générée."], [""]]
            ],
            {
                params ["_values", "_args"];
                _values params ["_label", "_band", "_sig", "_range", "_power", "_mac"];
                _args params ["_pos"];
                if (!(_label isEqualType "") || {_label isEqualTo ""}) then { _label = "RF Emitter"; };
                if (!(_band isEqualType "") || {_band isEqualTo ""}) then { _band = "wifi"; };
                private _meta = createHashMap;
                _meta set ["band", _band];
                _meta set ["signature_id", _sig];
                _meta set ["power_dbm", _power];
                _meta set ["mac", _mac];
                [_pos, _range, _label, _meta] call comspec_overwatch_connect_fnc_placeRfEmitter;
            },
            {},
            [_pos]
        ] call zen_dialog_fnc_create;
    },
    "\z\comspec_atak_native\addons\main\data\mod_net.paa"
] call comspec_overwatch_connect_fnc_zenModule;

missionNamespace setVariable ["COMSPEC_ZenRoleplayModulesRegistered", true];
