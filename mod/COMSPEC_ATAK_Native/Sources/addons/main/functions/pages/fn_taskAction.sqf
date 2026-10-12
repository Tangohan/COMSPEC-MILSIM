params ["_status"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _id = _s getOrDefault ["selectedTask", ""];
if (_id isEqualTo "") exitWith {
    private _msg = ["Sélectionnez un ordre du TOC dans la liste", "Tâche de mission : son statut est géré par la mission"] select ((_s getOrDefault ["selectedTaskKey", ""]) isNotEqualTo "");
    ["INFO", _msg, 3, 10] call comspec_atak_native_fnc_notify;
    false
};
// Chef d'un groupe de plusieurs joueurs : la réponse vaut pour tout le groupe (le site le voit dans la note).
private _grp = group player;
private _forGroup = (leader _grp) isEqualTo player && {({isPlayer _x} count (units _grp)) > 1};
private _note = ["", format ["Pour le groupe %1 (%2 joueurs)", groupId _grp, {isPlayer _x} count (units _grp)]] select _forGroup;
_status = toUpper _status;
// Étapes manquantes envoyées d'abord : Athena refuse EN COURS ou TERMINÉ sur un ordre pas encore reçu
// (TERMINÉ envoyait DELIVERED, refusé après REÇU : le bouton ne remontait jamais au site).
private _cur = toUpper ((([] call comspec_atak_native_fnc_tasksAll) getOrDefault [_id, createHashMap]) getOrDefault ["status", "PENDING"]);
private _steps = [_status];
if (_cur in ["", "PENDING", "DELIVERED", "NEW"] && {_status in ["EXEC", "DONE"]}) then { _steps = ["ACK", _status]; };
{
    if (!isNil "comspec_overwatch_connect_fnc_updateOrderStatus") then {
        [_id, _x, _note] call comspec_overwatch_connect_fnc_updateOrderStatus;
    } else {
        ["UpdateOrderStatus", [_id, _x, name player, str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]), "ATAK Native"]] call comspec_atak_native_fnc_extensionCall;
    };
} forEach _steps;
["send", [_id, _status]] call comspec_atak_native_fnc_orderAck;
// Retour immédiat dans la liste, avant la prochaine synchronisation Athena.
private _task = ([] call comspec_atak_native_fnc_tasksAll) getOrDefault [_id, createHashMap];
if ((count _task) > 0) then {
    _task set ["status", _status];
    [{ ["TASK"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
};
["SUCCESS", format ["Ordre %1%2", _status, ["", " · pour tout le groupe"] select _forGroup], 3, 30] call comspec_atak_native_fnc_notify;
true
