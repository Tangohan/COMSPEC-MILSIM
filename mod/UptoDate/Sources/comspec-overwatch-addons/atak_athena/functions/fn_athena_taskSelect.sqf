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
private _kind = "Ordre";
if (!isNil "comspec_overwatch_connect_fnc_orderTypeLabel") then {
    private _lbl = [_order] call comspec_overwatch_connect_fnc_orderTypeLabel;
    if (_lbl isEqualType "" && {_lbl isNotEqualTo ""}) then { _kind = _lbl; };
};

private _status = toUpper (_order getOrDefault ["status", "PENDING"]);
private _stTxt = "À traiter";
if (_status isEqualTo "ACK") then { _stTxt = "Accepté"; };
if (_status isEqualTo "EXEC") then { _stTxt = "En cours d’exécution"; };
if (_status in ["DONE", "CLOSED"]) then { _stTxt = "Terminé"; };
if (_status isEqualTo "FAILED") then { _stTxt = "Refusé / échec"; };
if (_status isEqualTo "CANCELLED") then { _stTxt = "Annulé"; };
if (_status isEqualTo "DELIVERED") then { _stTxt = "Remis"; };

private _prio = toUpper (_order getOrDefault ["priority", "IMPORTANT"]);
private _prioTxt = "Important";
if (_prio isEqualTo "URGENT") then { _prioTxt = "Urgent"; };
if (_prio isEqualTo "ROUTINE") then { _prioTxt = "Routine"; };

private _payload = _order getOrDefault ["payload", ""];
private _safePayload = if (_payload isEqualType "") then {
    (_payload replaceString ["<", "&lt;"]) replaceString [">", "&gt;"]
} else {
    str _payload
};

_detail ctrlSetStructuredText parseText format [
    "<t color='#ffd27a' size='1.05'>%1</t><br/><br/><t color='#8aa0b4'>Émetteur</t>  %2<br/><t color='#8aa0b4'>Priorité</t>  %3<br/><t color='#8aa0b4'>État</t>  %4<br/><t color='#8aa0b4'>Cible</t>  %5<br/><br/>%6",
    _kind,
    _order getOrDefault ["issuer", "C2"],
    _prioTxt,
    _stTxt,
    _order getOrDefault ["target", "—"],
    if (_safePayload isEqualTo "") then { "<t color='#8aa0b4'>Aucun détail supplémentaire.</t>" } else { _safePayload }
];

[] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
