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
