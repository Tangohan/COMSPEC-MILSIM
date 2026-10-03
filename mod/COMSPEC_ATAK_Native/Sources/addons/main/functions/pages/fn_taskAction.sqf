params ["_status"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _id = _s getOrDefault ["selectedTask", ""];
if (_id isEqualTo "") exitWith {
    private _msg = ["Sélectionnez un ordre du TOC dans la liste", "Tâche de mission : son statut est géré par la mission"] select ((_s getOrDefault ["selectedTaskKey", ""]) isNotEqualTo "");
    ["INFO", _msg, 3, 10] call comspec_atak_native_fnc_notify;
    false
};
if (!isNil "comspec_overwatch_connect_fnc_updateOrderStatus") then {
    [_id, toUpper _status] call comspec_overwatch_connect_fnc_updateOrderStatus;
} else {
    ["UpdateOrderStatus", [_id, toUpper _status, name player, str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]), "ATAK Native"]] call comspec_atak_native_fnc_extensionCall;
};
// Retour immédiat dans la liste, avant la prochaine synchronisation Athena.
private _task = ([] call comspec_atak_native_fnc_tasksAll) getOrDefault [_id, createHashMap];
if ((count _task) > 0) then {
    _task set ["status", toUpper _status];
    [{ ["TASK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
["SUCCESS", format ["Ordre %1", toUpper _status], 3, 30] call comspec_atak_native_fnc_notify;
true
