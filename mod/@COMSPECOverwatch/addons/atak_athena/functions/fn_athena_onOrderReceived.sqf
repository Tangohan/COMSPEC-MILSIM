/*
    Nouvel ordre Athena → pastille notification cTab si disponible.
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};
if (!(_order isEqualType createHashMap)) exitWith {};

private _type = _order getOrDefault ["type", "MOVE"];
private _typeLabel = trim (_order getOrDefault ["typeLabel", ""]);
if (_typeLabel isEqualTo "") then {
    _typeLabel = switch (toUpper _type) do {
        case "HOLD": { "Tenir la position" };
        case "RECON": { "Reconnaissance" };
        case "CAS": { "Appui aérien" };
        case "QRF": { "Force de réaction" };
        case "CUSTOM": { "Ordre personnalisé" };
        default { "Se déplacer" };
    };
};
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

["ATHENA", format ["Nouvel ordre — %1 (de %2)", _typeLabel, _issuer], 8] call comspec_overwatch_connect_fnc_addScreenToast;
if (!isNil "cTab_phoneVibrate") then {
    playSound "cTab_phoneVibrate";
};

// Miroir optionnel vers ATAK Enhanced (FRAGO) si le module alertes est présent
if (!(missionNamespace getVariable ["COMSPEC_AthenaBridge_SuppressMirror", false])
    && {(!isNil "Iceman_fnc_alerts_receive") || (!isNil "Iceman_fnc_alerts_send")}
) then {
    private _payload = _order getOrDefault ["payload", ""];
    private _time = if (!isNil "cTab_fnc_currentTime") then { call cTab_fnc_currentTime } else { [daytime, "HH:MM"] call BIS_fnc_timeToString };
    private _pos = getPos player;
    private _grid = mapGridPosition _pos;
    private _body = format [
        "FRAGO<br/>From: %1<br/>Grid: %2<br/>Time: %3<br/><br/>Ordre Athena — %4<br/>%5",
        _issuer, _grid, _time, _typeLabel, _payload
    ];
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", true, false];
    ["Iceman_ATAK_Alerts", ["FRAGO", player, _pos, _body, _time, "FRAGO"]] call CBA_fnc_localEvent;
    missionNamespace setVariable ["COMSPEC_AthenaBridge_SuppressMirror", false, false];
};

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
