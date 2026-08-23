/*
    Sélection d’un ordre dans TASK → détail.
*/
params ["_ctrl", "_idx"];

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
if (isNull _group) exitWith {};

private _detail = _group controlsGroupCtrl 9903;
if (isNull _detail) exitWith {};

if (_idx < 0) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", ""];
    _detail ctrlSetStructuredText parseText "<t color='#8aa0b4'>Sélectionnez un ordre.</t>";
    [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
};

private _id = _ctrl lbData _idx;
uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", _id];

private _order = createHashMap;
{
    if ((_x getOrDefault ["id", ""]) isEqualTo _id) exitWith { _order = _x; };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

if ((count _order) < 1) exitWith {
    _detail ctrlSetStructuredText parseText "<t color='#e8a0a0'>Ordre introuvable.</t>";
    [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
};

private _type = _order getOrDefault ["type", "MOVE"];
private _typeLabel = [_order] call comspec_overwatch_connect_fnc_orderTypeLabel;

private _status = toUpper (_order getOrDefault ["status", "PENDING"]);
private _statusLabel = switch (_status) do {
    case "ACK": { "Accepté" };
    case "EXEC": { "En cours d’exécution" };
    case "DONE";
    case "CLOSED": { "Terminé" };
    case "FAILED": { "Refusé / échec" };
    case "CANCELLED": { "Annulé" };
    case "DELIVERED": { "Remis" };
    default { "À traiter" };
};

private _prio = toUpper (_order getOrDefault ["priority", "IMPORTANT"]);
private _prioLabel = switch (_prio) do {
    case "URGENT": { "Urgent" };
    case "ROUTINE": { "Routine" };
    default { "Important" };
};

private _payload = _order getOrDefault ["payload", ""];
private _safePayload = if (_payload isEqualType "") then {
    (_payload replaceString ["<", "&lt;"]) replaceString [">", "&gt;"]
} else {
    str _payload
};

_detail ctrlSetStructuredText parseText format [
    "<t color='#ffd27a' size='1.05'>%1</t><br/><br/><t color='#8aa0b4'>Émetteur</t>  %2<br/><t color='#8aa0b4'>Priorité</t>  %3<br/><t color='#8aa0b4'>État</t>  %4<br/><t color='#8aa0b4'>Cible</t>  %5<br/><br/>%6",
    _typeLabel,
    _order getOrDefault ["issuer", "C2"],
    _prioLabel,
    _statusLabel,
    _order getOrDefault ["target", "—"],
    if (_safePayload isEqualTo "") then { "<t color='#8aa0b4'>Aucun détail supplémentaire.</t>" } else { _safePayload }
];

[] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
