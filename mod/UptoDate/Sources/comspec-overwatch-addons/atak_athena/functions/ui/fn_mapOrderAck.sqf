/*
    ACK / refus / terminé depuis la timeline carte — même canal que TASK.
*/
params [["_action", "ACK", [""]]];
private _oid = missionNamespace getVariable ["COMSPEC_MapOrderId", ""];
if (_oid isEqualTo "") then {
    {
        private _st = toUpper (_x getOrDefault ["status", "PENDING"]);
        if (_st in ["PENDING", "DELIVERED", "ACK", "EXEC"]) exitWith {
            _oid = _x getOrDefault ["id", ""];
        };
    } forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);
};
if (_oid isEqualTo "") exitWith {
    ["INFO", "Aucun ordre en attente"] call comspec_overwatch_atak_athena_fnc_showNotification;
};
uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", _oid];
private _map = switch (toUpper _action) do {
    case "DECLINE";
    case "REFUSE": { "REFUSE" };
    case "COMPLETE";
    case "DONE": { "DONE" };
    default { "ACK" };
};
[_map] call comspec_overwatch_atak_athena_fnc_athena_taskRespond;
