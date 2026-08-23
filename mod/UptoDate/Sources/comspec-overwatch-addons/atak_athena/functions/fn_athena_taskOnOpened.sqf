/*
    Ouverture de l’app TASK (ordres C2) dans cTab.
*/
params ["_group", ["_interfaceInit", false], "_isDialog", "_settings"];

if (isNull _group) exitWith {};

uiNamespace setVariable ["COMSPEC_ATAK_Task_group", _group];
private _token = diag_tickTime + random 1;
uiNamespace setVariable ["COMSPEC_ATAK_Task_token", _token];
uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", ""];

[] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;

if (!isNil "comspec_overwatch_connect_fnc_pollOrders") then {
    [] spawn {
        [] call comspec_overwatch_connect_fnc_pollOrders;
        [] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;
        [] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
    };
};

[] call comspec_overwatch_atak_athena_fnc_athena_updateTask;

private _disp = ctrlParent _group;
if (!isNull _disp) then {
    private _bar = _disp displayCtrl 46600;
    if (!isNull _bar) then {
        private _barPos = ctrlPosition _bar;
        private _slotW = (_barPos param [2, 0.16]) / 4;
        [allControls _bar, [0, 0, _slotW, 0], "", true] call comspec_overwatch_atak_athena_fnc_athena_taskFooter;
    };
};

[_token] spawn {
    params ["_token"];
    while { (uiNamespace getVariable ["COMSPEC_ATAK_Task_token", -1]) isEqualTo _token } do {
        uiSleep 5;
        if ((uiNamespace getVariable ["COMSPEC_ATAK_Task_token", -1]) isNotEqualTo _token) exitWith {};

        private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
        if (isNull _group || {!ctrlShown _group}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Task_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Task_group", controlNull];
            };
        };

        private _page = (["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings) param [0, ""];
        if (_page isNotEqualTo "" && {!(_page in ["AtakTask", "COMSPEC_ATAK_Task", "atak_task", "task"])}) exitWith {
            if ((uiNamespace getVariable ["COMSPEC_ATAK_Task_token", -1]) isEqualTo _token) then {
                uiNamespace setVariable ["COMSPEC_ATAK_Task_token", -1];
                uiNamespace setVariable ["COMSPEC_ATAK_Task_group", controlNull];
            };
        };

        [] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
        private _barKeep = (ctrlParent _group) displayCtrl 46600;
        if (!isNull _barKeep) then {
            private _keepPos = ctrlPosition _barKeep;
            [allControls _barKeep, [0, 0, ((_keepPos param [2, 0.16]) / 4), 0], "", true] call comspec_overwatch_atak_athena_fnc_athena_taskFooter;
        };
    };
};
