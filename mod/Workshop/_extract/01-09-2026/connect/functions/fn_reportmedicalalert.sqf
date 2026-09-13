/*
    Envoie une alerte médicale vers la plateforme (chat ATAK) lors d'un événement critique,
    et la recopie dans la liste PANIC du téléphone ATAK (IceMan) pour localisation.

    Params: [_unit, _kind]
    Kinds : unconscious | cardiac_arrest | kia
*/
params [
    ["_unit", objNull, [objNull]],
    ["_kind", "", [""]]
];

if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (isNull _unit || {!local _unit}) exitWith {};
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
if (isNull findDisplay 46) exitWith {};
if (isMultiplayer && {getClientStateNumber >= 11}) exitWith {};

private _kindNorm = toLower _kind;
if (_kindNorm in ["death", "dead", "killed", "mort"]) then { _kindNorm = "kia"; };
private _isKia = _kindNorm isEqualTo "kia";

if (!_isKia && {!alive _unit}) exitWith {};
if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith {};
if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
if (!_isKia && {![] call comspec_overwatch_connect_fnc_isPlayerSpawnStable}) exitWith {};
if (!_isKia && {missionNamespace getVariable ["COMSPEC_MedicalAlertBusy", false]}) exitWith {};

private _allowed = ["unconscious", "cardiac_arrest", "kia"];
if !(_kindNorm in _allowed) exitWith {};

private _callSign = if (_unit isEqualTo player) then {
    [] call comspec_overwatch_connect_fnc_getCallsign
} else {
    private _remote = trim (_unit getVariable ["COMSPEC_Callsign", ""]);
    if (_remote isEqualTo "") then { name _unit } else { _remote }
};
if (_callSign isEqualTo "") then { _callSign = name _unit; };

private _label = switch (_kindNorm) do {
    case "cardiac_arrest": { "Arrêt cardiaque" };
    case "unconscious": { "Au sol — inconscient" };
    case "kia": { "Hors combat" };
    default { "Assistance médicale" };
};

// Panneau PANIC même si Athena a déjà l’alerte (liste téléphone restée vide).
if (!isNil "comspec_overwatch_connect_fnc_pushIcemanMedicalAlert") then {
    [_kindNorm, _unit, getPos _unit, _callSign, _label, true] call comspec_overwatch_connect_fnc_pushIcemanMedicalAlert;
};

private _fnc_kindRank = {
    params ["_k"];
    switch (toLower _k) do {
        case "kia": { 120 };
        case "cardiac_arrest": { 100 };
        case "unconscious": { 80 };
        default { 0 };
    };
};

private _last = missionNamespace getVariable ["COMSPEC_lastMedicalAlertKind", ""];
private _newRank = [_kindNorm] call _fnc_kindRank;
private _lastRank = if (_last isEqualTo "") then { -1 } else { [_last] call _fnc_kindRank };
if (_lastRank >= _newRank && {_lastRank > 0}) exitWith {};

private _lastAt = missionNamespace getVariable ["COMSPEC_lastMedicalAlertAt", -1e9];
if ((diag_tickTime - _lastAt) < 4 && {_newRank <= _lastRank}) exitWith {};

private _own = [] call comspec_overwatch_connect_fnc_hasOwnActiveMedicalAlert;
private _skipDup = false;
if (count _own > 0) then {
    private _ownKind = toLower (_own getOrDefault ["kind", ""]);
    private _ownRank = if (_ownKind isEqualTo "") then { -1 } else { [_ownKind] call _fnc_kindRank };
    if (_ownRank >= _newRank) then { _skipDup = true; };
};

if (_skipDup) exitWith {
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", _kindNorm, false];
};

missionNamespace setVariable ["COMSPEC_MedicalAlertBusy", true, false];
missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", _kindNorm, false];
missionNamespace setVariable ["COMSPEC_lastMedicalAlertAt", diag_tickTime, false];

private _state = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _parts = _state splitString "|";
private _blood = if (count _parts >= 2) then { _parts select 1 } else { "?" };
private _hr = if (count _parts >= 4) then { _parts select 3 } else { "?" };
if (_isKia) then {
    _hr = "0";
};

private _grid = mapGridPosition _unit;
private _msg = format [
    "ALERTE MÉDICALE | %1 | %2 | FC=%3 | Volume sanguin≈%4%% | Grille %5",
    _callSign,
    _label,
    _hr,
    _blood,
    _grid
];

[player, "CHAT", _msg, "", "INFANTRY", 0.95] call comspec_overwatch_connect_fnc_sendIntel;

private _alert = createHashMapFromArray [
    ["kind", toUpper _kindNorm],
    ["unit", _callSign],
    ["heartRate", parseNumber _hr],
    ["blood", parseNumber _blood],
    ["position", getPos _unit],
    ["grid", _grid],
    ["message", _msg]
];
["OnMedicalAlert", _alert] call comspec_overwatch_connect_fnc_publishEvent;

[format ["Alerte médicale transmise : %1", _label], "medical", "critical"] call comspec_overwatch_connect_fnc_announce;
[_kindNorm] call comspec_overwatch_connect_fnc_playAtakNotification;
diag_log format ["[COMSPEC] Medical alert %1 — %2", _kindNorm, _msg];
[
    "WARN",
    "Medical",
    format ["%1 — %2", _callSign, _label],
    "medical",
    format ["FC=%1 · vol. sanguin≈%2%% · grille %3", _hr, _blood, _grid]
] call comspec_overwatch_connect_fnc_logAtakEvent;

missionNamespace setVariable ["COMSPEC_MedicalAlertBusy", false, false];
