/*
    Sélection d’un ordre dans TASK → détail lisible.
*/
if (uiNamespace getVariable ["COMSPEC_ATAK_Task_rebuilding", false]) exitWith {};

params ["_ctrl", ["_idx", -1, [0]]];

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
if (isNull _group) exitWith {};

private _detail = _group controlsGroupCtrl 9903;
if (isNull _detail) exitWith {};
_detail ctrlShow true;

if (!(_idx isEqualType 0) || {_idx < 0}) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", ""];
    _detail ctrlSetStructuredText parseText "<t color='#c5cdd6'>Sélectionnez un ordre dans la liste.</t>";
    [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
};

private _id = _ctrl lbData _idx;
uiNamespace setVariable ["COMSPEC_ATAK_Task_selectedId", _id];

private _order = createHashMap;
{
    if ((_x getOrDefault ["id", ""]) isEqualTo _id) exitWith { _order = _x; };
} forEach (missionNamespace getVariable ["COMSPEC_Orders", []]);

if ((count _order) < 1) exitWith {
    _detail ctrlSetStructuredText parseText "<t color='#e8a0a0'>Ordre introuvable. Appuyez sur Actualiser.</t>";
    [] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
};

private _fnc_esc = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    _s = (_s replaceString ["&", "&amp;"]) replaceString ["<", "&lt;"];
    _s replaceString [">", "&gt;"]
};

private _kind = "Ordre";
if (!isNil "comspec_overwatch_connect_fnc_orderTypeLabel") then {
    private _lbl = [_order] call comspec_overwatch_connect_fnc_orderTypeLabel;
    if (_lbl isEqualType "" && {_lbl isNotEqualTo ""}) then { _kind = _lbl; };
};

private _status = toUpper (_order getOrDefault ["status", "PENDING"]);
private _stTxt = "À traiter";
private _next = "Acceptez l’ordre pour confirmer la prise en compte.";
if (_status isEqualTo "ACK") then {
    _stTxt = "Accepté";
    _next = "Signalez « En cours » dès que vous exécutez, ou interrompez.";
};
if (_status isEqualTo "EXEC") then {
    _stTxt = "En cours d’exécution";
    _next = "Interrompez seulement si l’ordre ne peut plus être tenu.";
};
if (_status in ["DONE", "CLOSED"]) then {
    _stTxt = "Terminé";
    _next = "";
};
if (_status isEqualTo "FAILED") then {
    _stTxt = "Refusé";
    _next = "";
};
if (_status isEqualTo "CANCELLED") then {
    _stTxt = "Annulé";
    _next = "";
};
if (_status isEqualTo "DELIVERED") then {
    _stTxt = "Remis";
    _next = "Acceptez l’ordre pour confirmer la prise en compte.";
};

private _prio = toUpper (_order getOrDefault ["priority", "IMPORTANT"]);
private _prioTxt = "Important";
if (_prio isEqualTo "URGENT") then { _prioTxt = "Urgent"; };
if (_prio isEqualTo "ROUTINE") then { _prioTxt = "Routine"; };

private _payload = _order getOrDefault ["payload", ""];
private _body = "";
if (_payload isEqualType "") then {
    _body = [_payload] call _fnc_esc;
} else {
    if (_payload isEqualType createHashMap) then {
        private _bits = [];
        {
            _bits pushBack format ["%1 : %2", [_x] call _fnc_esc, [_payload getOrDefault [_x, ""]] call _fnc_esc];
        } forEach (keys _payload);
        _body = _bits joinString "<br/>";
    } else {
        _body = [str _payload] call _fnc_esc;
    };
};
if (_body isEqualTo "") then {
    _body = "<t color='#8b929c'>Aucun détail supplémentaire.</t>";
};

private _hint = if (_next isEqualTo "") then { "" } else {
    format ["<br/><br/><t color='#ffd27a'>%1</t>", [_next] call _fnc_esc]
};

_detail ctrlSetStructuredText parseText format [
    "<t color='#ffd27a' size='1.08'>%1</t><br/><t color='#8b929c'>État  %2 · Priorité  %3</t><br/><br/><t color='#8b929c'>Émetteur</t>  %4<br/><t color='#8b929c'>Destinataire</t>  %5<br/><br/>%6%7",
    [_kind] call _fnc_esc,
    [_stTxt] call _fnc_esc,
    [_prioTxt] call _fnc_esc,
    [_order getOrDefault ["issuer", "C2"]] call _fnc_esc,
    [_order getOrDefault ["target", "—"]] call _fnc_esc,
    _body,
    _hint
];

[] call comspec_overwatch_atak_athena_fnc_athena_taskSyncButtons;
