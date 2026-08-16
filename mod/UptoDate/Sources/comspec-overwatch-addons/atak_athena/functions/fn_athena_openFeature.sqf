/*
    Ouvre l’app Athena dans ATAK Enhanced (pas la tablette Overwatch),
    puis bascule sur l’onglet demandé.
    Params: [_tab] — all|bda|photo|order|messages|urgences|liaison|modules|notif|alert|cas|manifest|briefing
*/
params [["_tab", "all", [""]]];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

_tab = toLower (trim _tab);

// Formulaires dédiés (hors onglets panneau)
if (_tab isEqualTo "briefing") exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_openBriefing;
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
if (_tab isEqualTo "apps" || {_tab isEqualTo "hub"} || {_tab isEqualTo "bft"} || {_tab isEqualTo "status"} || {_tab isEqualTo "help"} || {_tab isEqualTo "radio"}) then { _tab = "all"; };

// Ordres C2 → app TASK (liste + réponses), pas seulement l’onglet Athena
if (_tab isEqualTo "order" || {_tab isEqualTo "task"} || {_tab isEqualTo "tasks"}) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_openTask;
};

missionNamespace setVariable ["COMSPEC_Athena_PendingTab", _tab, false];
missionNamespace setVariable ["COMSPEC_Athena_PanelTab", _tab, false];

if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["COMSPEC_Warning", ["Terminal ATAK manquant — emportez votre téléphone ou tablette tactique pour synchroniser et ouvrir l’interface."]] call comspec_overwatch_connect_fnc_showNotification;
};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    [] call comspec_overwatch_connect_fnc_openAtakEnhanced;
};

[_tab] spawn {
    params ["_tab"];
    private _deadline = diag_tickTime + 6;
    waitUntil {
        !isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])
        || {diag_tickTime > _deadline}
    };
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith {
        ["COMSPEC_Warning", ["Impossible d’ouvrir le téléphone ATAK."]] call comspec_overwatch_connect_fnc_showNotification;
    };

    ["Athena"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
    uiSleep 0.15;
    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull])) then {
        ["cTab_Android_dlg", [["showMenu", ["COMSPEC_ATAK_Athena", true, ["", -1], createHashMap]]], true, true] call cTab_fnc_setSettings;
        uiSleep 0.12;
    };

    uiSleep 0.2;
    missionNamespace setVariable ["COMSPEC_Athena_PanelTab", _tab, false];

    private _tries = 0;
    waitUntil {
        _tries = _tries + 1;
        !isNull (uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull])
        || {_tries > 20}
    };

    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    [_tab] call comspec_overwatch_atak_athena_fnc_athena_selectTab;

    if (_tab isEqualTo "liaison") then {
        uiSleep 0.15;
        [] call comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect;
    };

    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull])) then {
        ["COMSPEC_Warning", ["Athena ne s’est pas ouvert — réessayez depuis le menu d’applications ATAK."]] call comspec_overwatch_connect_fnc_showNotification;
    };
};
