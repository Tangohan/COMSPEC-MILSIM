/*
    Nouvel ordre Athena → pastille + feedback visible sans spam.
    Miroir IceMan Reports = FRAGO destinataire + ATHENA_ORDER_ID pour ACK.
    Le bandeau BIS / son sont déjà gérés par receiveOrder (showNotification).
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};
if (!(_order isEqualType createHashMap)) exitWith {};

private _type = _order getOrDefault ["type", "MOVE"];
private _typeLabel = [_order] call comspec_overwatch_connect_fnc_orderTypeLabel;
private _issuer = _order getOrDefault ["issuer", "C2"];

private _prio = _order getOrDefault ["priority", "IMPORTANT"];
private _prioLabel = switch (toUpper _prio) do {
    case "URGENT": { "Urgent" };
    case "ROUTINE": { "Routine" };
    default { "Important" };
};
private _payload = _order getOrDefault ["payload", ""];
private _orderId = _order getOrDefault ["id", format ["ord_%1", diag_tickTime]];
private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;
private _detail = format [
    "<t color='#7eb8ff'>Ordre</t> — %1<br/><t color='#8aa0b4'>Priorité</t>  %2<br/><t color='#8aa0b4'>Émetteur</t>  %3<br/><t color='#8aa0b4'>Cible</t>  %4<br/>%5",
    _typeLabel, _prioLabel, _issuer, _order getOrDefault ["target", "—"],
    if (_payload isEqualTo "") then { "" } else { format ["<br/>%1", _payload] }
];
[
    "order",
    "Ordre",
    format ["%1 — de %2", _typeLabel, _issuer],
    _detail,
    _orderId,
    _timeStr
] call comspec_overwatch_atak_athena_fnc_athena_pushNotification;

// Toast cTab : forcé si alertes écran OFF (sinon le bandeau BIS de receiveOrder suffit).
private _toastText = format ["Nouvel ordre — %1 (de %2)", _typeLabel, _issuer];
private _screenOn = [] call comspec_overwatch_connect_fnc_shouldShowScreenNotification;
if (!_screenOn) then {
    ["ATHENA", _toastText, 8, true] call comspec_overwatch_connect_fnc_addScreenToast;
};

// Soft open TASK si le téléphone est déjà ouvert (pas d’ouverture intrusive à froid).
private _phoneOpen = !isNull (uiNamespace getVariable ["cTab_Android_dlg", displayNull]);
if (!_phoneOpen) then {
    _phoneOpen = !isNull (uiNamespace getVariable ["cTab_Android_dsp", displayNull]);
};
if (_phoneOpen) then {
    private _taskGroup = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
    if (!isNull _taskGroup && {ctrlShown _taskGroup}) then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
    } else {
        if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openAtakApp") then {
            ["AtakTask"] call comspec_overwatch_atak_athena_fnc_athena_openAtakApp;
            [{ [] call comspec_overwatch_atak_athena_fnc_athena_updateTask; }, [], 0.2] call CBA_fnc_waitAndExecute;
        };
    };
};

// Miroir IceMan Reports (FRAGO destinataire) — hors signaux terminal / hors drone.
if (
    !(missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false])
    && {!isNil "Iceman_fnc_alerts_receive"}
    && {!(toUpper _type in ["VIBRATE", "NOTIFY", "HELMET_SNAP", "HELMET_SNAP_HD", "HELMET_STREAM", "PHONE_GEOLOC", "PHONE_GEOLOC_OFF"])}
) then {
    private _time = if (!isNil "cTab_fnc_currentTime") then { call cTab_fnc_currentTime } else { _timeStr };
    private _pos = getPos player;
    private _grid = mapGridPosition _pos;
    private _safePayload = _payload replaceString ["<", "&lt;"];
    _safePayload = _safePayload replaceString [">", "&gt;"];
    private _body = format [
        "<t color='#ffd36a'>ORDRE ATHENA</t><br/>De : %1<br/>Grille : %2<br/>Heure : %3<br/>Type : %4<br/>Priorité : %5<br/><br/>%6<br/><br/>ATHENA_ORDER_ID=%7",
        _issuer,
        _grid,
        _time,
        _typeLabel,
        _prioLabel,
        if (_safePayload isEqualTo "") then { "—" } else { _safePayload },
        _orderId
    ];
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    ["FRAGO", objNull, _pos, _body, _time, "FRAGO"] call Iceman_fnc_alerts_receive;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
};

// Miroir chat de groupe IceMan — backfill + nouvel ordre.
[] call comspec_overwatch_atak_athena_fnc_athena_syncOrdersToGroupChat;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};

private _taskGroup2 = uiNamespace getVariable ["COMSPEC_ATAK_Task_group", controlNull];
if (!isNull _taskGroup2 && {ctrlShown _taskGroup2}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updateTask;
};