/*
    Module Zeus Enhanced + ACE Zeus : relevé complet du théâtre (bâtiments, forêts, relief).
    ZEN peut arriver après le premier essai : ne pas poser le drapeau tant qu’il n’est pas chargé.
*/
if (!hasInterface) exitWith {};

private _icon = "\A3\ui_f\data\igui\cfg\simpletasks\types\map_ca.paa";
private _open = {
    [true] call comspec_overwatch_connect_fnc_theaterSurveyShow;
};
localNamespace setVariable ["COMSPEC_ZeusOpenTheaterSurvey", _open];

if (
    !(missionNamespace getVariable ["COMSPEC_AceTheaterSurveyRegistered", false])
    && {!isNil "ace_zeus_fnc_addModule"}
) then {
    ["COMSPEC Outils", "Relever la carte du théâtre", {
        [] call (localNamespace getVariable ["COMSPEC_ZeusOpenTheaterSurvey", {}]);
    }, _icon] call ace_zeus_fnc_addModule;
    ["COMSPEC Outils", "Relever routes et villes", {
        ["zeus"] call comspec_overwatch_connect_fnc_moduleGeoNetwork;
    }, "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa"] call ace_zeus_fnc_addModule;
    missionNamespace setVariable ["COMSPEC_AceTheaterSurveyRegistered", true];
};

if (isNil "zen_custom_modules_fnc_register") exitWith {};
if (missionNamespace getVariable ["COMSPEC_ZenTheaterSurveyRegistered", false]) exitWith {};

[
    "COMSPEC Outils",
    "Relever la carte du théâtre",
    {
        [] call (localNamespace getVariable ["COMSPEC_ZeusOpenTheaterSurvey", {}]);
    },
    _icon
] call comspec_overwatch_connect_fnc_zenModule;

// Routes et villes seulement, sans le relevé complet (bâtiments, forêts, relief).
[
    "COMSPEC Outils",
    "Relever routes et villes",
    {
        ["zeus"] call comspec_overwatch_connect_fnc_moduleGeoNetwork;
    },
    "\A3\ui_f\data\igui\cfg\simpletasks\types\move_ca.paa"
] call comspec_overwatch_connect_fnc_zenModule;

if (!isNil "zen_context_menu_fnc_createAction" && {!isNil "zen_context_menu_fnc_addAction"}) then {
    private _ctx = [
        "comspec_theater_survey",
        "Relever la carte du théâtre",
        _icon,
        {
            [] call (localNamespace getVariable ["COMSPEC_ZeusOpenTheaterSurvey", {}]);
        },
        { true }
    ] call comspec_overwatch_connect_fnc_zenAction;
    [_ctx, [], 8] call zen_context_menu_fnc_addAction;
};

missionNamespace setVariable ["COMSPEC_ZenTheaterSurveyRegistered", true];
["INFO", "Theater", "Outil Zeus relevé de carte enregistré"] call comspec_overwatch_connect_fnc_log;
