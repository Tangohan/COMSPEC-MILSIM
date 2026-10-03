params [["_page", "LAUNCHER"]];
disableSerialization;
private _d = findDisplay 88500;
if (isNull _d) exitWith { false };
[] call comspec_atak_native_fnc_pageClear;
[] call comspec_atak_native_fnc_layoutApply;

private _title = "APPLICATIONS";
if (_page isNotEqualTo "LAUNCHER") then {
    private _app = ([] call comspec_atak_native_fnc_appList) select { (_x get "page") isEqualTo _page };
    _title = if ((count _app) > 0) then { toUpper ((_app select 0) get "name") } else { _page };
};
(_d displayCtrl 88518) ctrlSetText _title;

switch (_page) do {
    case "LAUNCHER": { [] call comspec_atak_native_fnc_launcherRender; };
    case "MAP": { [] call comspec_atak_native_fnc_pageMap; };
    case "CHAT": { [] call comspec_atak_native_fnc_pageChat; };
    case "GROUP": { [] call comspec_atak_native_fnc_pageGroup; };
    case "TASK": { [] call comspec_atak_native_fnc_pageTasks; };
    default { [_page] call comspec_atak_native_fnc_pageText; };
};
true
