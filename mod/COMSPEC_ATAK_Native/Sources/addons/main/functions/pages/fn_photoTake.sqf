/*
    Photo rapide : masque le téléphone, prend une capture de la vue et l'envoie sur ATAK web
    par la chaîne recon d'Overwatch (NotifyNewPhoto). Le suivi d'envoi arrive dans l'app Photos.
    Params : [légende]
*/
params [["_caption", ""]];
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    ["WARNING", "Photos : COMSPEC Overwatch doit être chargé pour l'envoi vers ATAK web", 4, 30] call comspec_atak_native_fnc_notify;
    false
};
if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith {
    ["WARNING", "Photos : connectez-vous d'abord à Athena (app Athena)", 4, 30] call comspec_atak_native_fnc_notify;
    false
};
if (_caption isEqualTo "") then { _caption = format ["%1 · %2", [player] call comspec_atak_native_fnc_unitCallsign, [player] call comspec_atak_native_fnc_gridRef]; };
[_caption] spawn {
    params ["_caption"];
    disableSerialization;
    private _d = [] call comspec_atak_native_fnc_display;
    private _hidden = [];
    if (!isNull _d) then { { if (ctrlShown _x) then { _x ctrlShow false; _hidden pushBack _x; }; } forEach (allControls _d); };
    uiSleep 0.15;
    private _ok = ["", _caption, "CTAB", "", false, true, true] call comspec_overwatch_connect_fnc_captureReconImage;
    uiSleep 0.1;
    { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
    [[ "WARNING", "SUCCESS"] select _ok, ["Photo non envoyée : voir l'app Photos", "Photo en cours d'envoi vers Athena"] select _ok, 3, 30] call comspec_atak_native_fnc_notify;
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isEqualTo "PHOTOS") then { ["PHOTOS"] call comspec_atak_native_fnc_pageRender; };
};
true
