/*
    Ouvre l’app TASK (ordres C2) dans ATAK Enhanced.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["COMSPEC_Warning", ["Terminal ATAK manquant — emportez votre téléphone ou tablette tactique."]] call comspec_overwatch_connect_fnc_showNotification;
};

// Garantir l’app dans le tiroir avant ouverture
if (!isNil "BCE_fnc_ATAK_setAPPs_props") then {
    private _apps = + (profileNamespace getVariable ["BCE_ATAK_APPs", []]);
    if (!(_apps isEqualType [])) then { _apps = []; };
    if (!("AtakTask" in _apps)) then {
        _apps pushBack "AtakTask";
        profileNamespace setVariable ["BCE_ATAK_APPs", _apps];
        saveProfileNamespace;
    };
    [_apps] call BCE_fnc_ATAK_setAPPs_props;
};

private _parent = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _parent) then {
    [] call comspec_overwatch_connect_fnc_openAtakEnhanced;
};

[] spawn {
    private _deadline = diag_tickTime + 6;
    waitUntil {
        !isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])
        || {diag_tickTime > _deadline}
    };
    if (isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull])) exitWith {
        ["COMSPEC_Warning", ["Impossible d’ouvrir le téléphone ATAK."]] call comspec_overwatch_connect_fnc_showNotification;
    };

    if (!isNil "comspec_overwatch_connect_fnc_pollOrders") then {
        [] call comspec_overwatch_connect_fnc_pollOrders;
    };
    if (!isNil "comspec_overwatch_connect_fnc_pollMissionPlan") then {
        [] call comspec_overwatch_connect_fnc_pollMissionPlan;
    };
    [] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;

    ["AtakTask"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
    uiSleep 0.15;
    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull])) then {
        ["cTab_Android_dlg", [["showMenu", ["COMSPEC_ATAK_Task", true, ["", -1], createHashMap]]], true, true] call cTab_fnc_setSettings;
        uiSleep 0.12;
    };

    private _tries = 0;
    waitUntil {
        _tries = _tries + 1;
        !isNull (uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull])
        || {_tries > 20}
    };

    [] call comspec_overwatch_atak_athena_fnc_athena_updateTask;

    if (isNull (uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull])) then {
        // Repli : boîte Ordres reçus si l’app TASK n’apparaît pas encore dans le tiroir
        if (!isNil "comspec_overwatch_connect_fnc_orderInboxShow") then {
            [] call comspec_overwatch_connect_fnc_orderInboxShow;
        };
        ["COMSPEC_Warning", ["TASK non visible dans le tiroir — boîte Ordres reçus ouverte. Rouvrez le téléphone après relance."]] call comspec_overwatch_connect_fnc_showNotification;
    };
};
