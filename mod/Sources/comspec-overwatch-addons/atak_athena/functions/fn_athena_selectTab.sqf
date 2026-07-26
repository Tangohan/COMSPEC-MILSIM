/*
    Change l’onglet du panneau Athena (all | bda | photo | order | modules).
*/
params [["_tab", "all", [""]]];

_tab = toLower _tab;
if !(_tab in ["all", "bda", "photo", "order", "alert", "modules", "notif"]) then { _tab = "all"; };
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", _tab, false];
[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
