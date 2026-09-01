/*
    Bascule l’écran Athena : fil | alerter | rapporter | poste.
*/
params [["_home", "fil", [""]]];

_home = toLower _home;
if !(_home in ["fil", "alerter", "rapporter", "poste"]) then { _home = "fil"; };

missionNamespace setVariable ["COMSPEC_Athena_HomeSection", _home, false];

private _tab = missionNamespace getVariable ["COMSPEC_Athena_PanelTab", "all"];
switch (_home) do {
    case "alerter": { _tab = "urgences"; };
    case "rapporter": {
        if !(_tab in ["bda", "photo", "all"]) then { _tab = "all"; };
    };
    case "poste": {
        if !(_tab in ["liaison", "modules"]) then { _tab = "liaison"; };
    };
    default {
        if (_tab in ["liaison", "modules", "urgences", "alert", "notif"]) then { _tab = "all"; };
    };
};
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", _tab, false];

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
