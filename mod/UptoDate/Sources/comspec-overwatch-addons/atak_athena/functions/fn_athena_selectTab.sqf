/*
    Change l’onglet du panneau Athena.
    all | bda | photo | order | messages | urgences | liaison | modules | notif
    cas / manifest / briefing → formulaires dédiés
*/
params [["_tab", "all", [""]]];

_tab = toLower _tab;

if (_tab isEqualTo "briefing") exitWith {
    [] call comspec_overwatch_connect_fnc_openBriefingBoard;
};
if (_tab isEqualTo "cas") exitWith {
    [] call comspec_overwatch_connect_fnc_casRequestShow;
};
if (_tab isEqualTo "manifest") exitWith {
    [] call comspec_overwatch_connect_fnc_flightManifestShow;
};

if (_tab isEqualTo "alerts" || {_tab isEqualTo "alert"} || {_tab isEqualTo "medical"} || {_tab isEqualTo "tactical"}) then { _tab = "urgences"; };
if (_tab isEqualTo "chat" || {_tab isEqualTo "msg"}) then { _tab = "messages"; };
if (_tab isEqualTo "phone" || {_tab isEqualTo "link"} || {_tab isEqualTo "callsign"} || {_tab isEqualTo "account"}) then { _tab = "liaison"; };
if (_tab isEqualTo "orders") then { _tab = "order"; };
if (_tab isEqualTo "photos") then { _tab = "photo"; };
if !(_tab in ["all", "bda", "photo", "order", "messages", "urgences", "liaison", "modules", "notif", "alert"]) then { _tab = "all"; };
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", _tab, false];

if (!(missionNamespace getVariable ["COMSPEC_Athena_FilterFromCombo", false])) then {
    switch (_tab) do {
        case "urgences";
        case "alert";
        case "notif": {
            missionNamespace setVariable ["COMSPEC_Athena_HomeSection", "alerter", false];
        };
        case "liaison";
        case "modules": {
            missionNamespace setVariable ["COMSPEC_Athena_HomeSection", "poste", false];
        };
        default {
            if (_tab in ["all", "photo", "order", "messages", "bda"]) then {
                missionNamespace setVariable ["COMSPEC_Athena_HomeSection", "fil", false];
            };
        };
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
