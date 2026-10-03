params [["_page", "LAUNCHER"]];
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
// Garder la saisie en cours de l'app Athena quand la page se redessine.
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
if ("email" in _form || {"pair" in _form} || {"otp" in _form}) then {
    private _draft = uiNamespace getVariable ["COMSPEC_ATAK_AthenaDraft", createHashMap];
    { if (_x in _form) then { _draft set [_x, [_x] call comspec_atak_native_fnc_formValue]; }; } forEach ["email", "otp", "pair"];
    uiNamespace setVariable ["COMSPEC_ATAK_AthenaDraft", _draft];
};
[] call comspec_atak_native_fnc_frsDraftSave;
[] call comspec_atak_native_fnc_recoDraftSave;
[] call comspec_atak_native_fnc_firesSave;
[] call comspec_atak_native_fnc_pageClear;
[] call comspec_atak_native_fnc_layoutApply;

private _title = "APPLICATIONS";
if (_page isNotEqualTo "LAUNCHER") then {
    private _app = ([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page };
    _title = if ((count _app) > 0) then { toUpper ((_app select 0) get "name") } else { _page };
};
if (_page isEqualTo "RECENTS") then { _title = "APPS RÉCENTES"; };
(_d displayCtrl 88518) ctrlSetText _title;

switch (_page) do {
    case "LAUNCHER": { [] call comspec_atak_native_fnc_launcherRender; };
    case "MAP": { [] call comspec_atak_native_fnc_pageMap; };
    case "CHAT": { [] call comspec_atak_native_fnc_pageChat; };
    case "GROUP": { [] call comspec_atak_native_fnc_pageGroup; };
    case "BFT": { [] call comspec_atak_native_fnc_pageBft; };
    case "RECENTS": { [] call comspec_atak_native_fnc_pageRecents; };
    case "FOOD": { [] call comspec_atak_native_fnc_pageFood; };
    case "DATING": { [] call comspec_atak_native_fnc_pageDating; };
    case "OSINT": { [] call comspec_atak_native_fnc_pageOsint; };
    case "WAYPOINTS": { [] call comspec_atak_native_fnc_pageWaypoints; };
    case "TASK": { [] call comspec_atak_native_fnc_pageTasks; };
    case "ATHENA": { [] call comspec_atak_native_fnc_pageAthena; };
    case "NETWORK": { [] call comspec_atak_native_fnc_pageNetwork; };
    case "SETTINGS": { [] call comspec_atak_native_fnc_pageSettings; };
    case "PHOTOS": { [] call comspec_atak_native_fnc_pagePhotos; };
    case "FRS": { [] call comspec_atak_native_fnc_pageFrs; };
    case "RECO": { [] call comspec_atak_native_fnc_pageReco; };
    case "FIRES": { [] call comspec_atak_native_fnc_pageFires; };
    case "ALERTS": { [] call comspec_atak_native_fnc_pageAlerts; };
    case "MEDICAL": { [] call comspec_atak_native_fnc_pageMedical; };
    case "PROFILE": { [] call comspec_atak_native_fnc_pageProfile; };
    case "LIVECAM": { [] call comspec_atak_native_fnc_pageLivecam; };
    case "BRIEFING": { [] call comspec_atak_native_fnc_pageBriefing; };
    default { [_page] call comspec_atak_native_fnc_pageText; };
};
// Dégâts de l'écran par-dessus la page.
uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", []];
[] call comspec_atak_native_fnc_deviceOverlay;
true
