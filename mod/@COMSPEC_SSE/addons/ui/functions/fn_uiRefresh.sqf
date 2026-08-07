/*
    Rafraîchit l'écran UI courant.
    [_screen] call comspec_sse_fnc_uiRefresh
*/
params [
    ["_screen", "", [""]]
];

if (_screen == "") then {
    _screen = missionNamespace getVariable ["comspec_sse_uiScreen", "terminal"];
};

switch (toLower _screen) do {
    case "terminal": { [] call comspec_sse_fnc_uiFillTerminal };
    case "digital": { [] call comspec_sse_fnc_uiFillDigital };
    case "site": { [] call comspec_sse_fnc_uiFillSite };
    case "graph": { [] call comspec_sse_fnc_uiFillGraph };
    case "evidence": { [] call comspec_sse_fnc_uiFillEvidence };
    case "mission": { [] call comspec_sse_fnc_uiFillMission };
    case "zeus": { [] call comspec_sse_fnc_uiFillZeus };
};
true
