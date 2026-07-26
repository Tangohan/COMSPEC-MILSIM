/*
    Envoie une alerte médicale vers la plateforme (chat ATAK) lors d'un événement critique.
    Params: [_unit, _kind] où _kind = "unconscious" | "cardiac_arrest"
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
if (!alive _unit) exitWith {};

private _kindNorm = toLower _kind;
if !(_kindNorm in ["unconscious", "cardiac_arrest"]) exitWith {};

private _last = missionNamespace getVariable ["COMSPEC_lastMedicalAlertKind", ""];
if (_last isEqualTo _kindNorm) exitWith {}; // déjà signalé pour cet état
// Pas de « downgrade » : arrêt cardiaque déjà signalé → ne pas republier inconscient
if (_last isEqualTo "cardiac_arrest" && {_kindNorm isEqualTo "unconscious"}) exitWith {};

// Déjà une alerte active non résolue du même type (cache poll) → ne pas republier
private _own = [] call comspec_overwatch_connect_fnc_hasOwnActiveMedicalAlert;
private _skipDup = false;
if (count _own > 0) then {
    private _ownKind = toLower (_own getOrDefault ["kind", ""]);
    if (_ownKind isEqualTo _kindNorm) then { _skipDup = true; };
    if (
        !_skipDup
        && {_ownKind in ["cardiac_arrest", "cardiac-arrest", "death", "dead", "kia"]}
        && {_kindNorm isEqualTo "unconscious"}
    ) then {
        _skipDup = true;
    };
};
if (_skipDup) exitWith {
    missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", _kindNorm, false];
};

// Verrouiller AVANT l’envoi pour éviter un double POST si le PFH / ACE rappellent dans la foulée
missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", _kindNorm, false];

private _state = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _parts = _state splitString "|";
private _blood = if (count _parts >= 2) then { _parts select 1 } else { "?" };
private _hr = if (count _parts >= 4) then { _parts select 3 } else { "?" };
// Toujours l’indicatif tactique COMSPEC (pas name profil) — sinon Athena crée une 2ᵉ unité fantôme.
private _callSign = if (_unit isEqualTo player) then {
    [] call comspec_overwatch_connect_fnc_getCallsign
} else {
    private _remote = trim (_unit getVariable ["COMSPEC_Callsign", ""]);
    if (_remote isEqualTo "") then { name _unit } else { _remote }
};
if (_callSign isEqualTo "") then { _callSign = name _unit; };
private _pos = getPos _unit;
private _grid = mapGridPosition _unit;

private _label = if (_kindNorm == "cardiac_arrest") then {
    "Cardiac arrest (zero heart rate)"
} else {
    "Au sol — inconscient"
};

private _msg = format [
    "ALERTE MÉDICALE | %1 | %2 | FC=%3 | Volume sanguin≈%4%% | Grille %5",
    _callSign,
    _label,
    _hr,
    _blood,
    _grid
];

// Canal chat ATAK (même voie que le bilan santé manuel WIA)
[player, "CHAT", _msg, "", "INFANTRY", 0.95] call comspec_overwatch_connect_fnc_sendIntel;

private _alert = createHashMapFromArray [
    ["kind", toUpper _kindNorm],
    ["unit", _callSign],
    ["heartRate", parseNumber _hr],
    ["blood", parseNumber _blood],
    ["position", _pos],
    ["grid", _grid],
    ["message", _msg]
];
["OnMedicalAlert", _alert] call comspec_overwatch_connect_fnc_publishEvent;

[format ["Alerte médicale transmise : %1", _label], "medical", "critical"] call comspec_overwatch_connect_fnc_announce;
// Son d’urgence dédié (inconscient → atak_alert_2, arrêt cardiaque → atak_death).
// Mode discret ne coupe pas ce son ; seul « Muet » (CBA Son des notifications) le coupe.
[_kindNorm] call comspec_overwatch_connect_fnc_playAtakNotification;
diag_log format ["[COMSPEC] Medical alert %1 — %2", _kindNorm, _msg];
[format ["[Médical] %1 — %2 (grille %3)", _callSign, _label, _grid], "medical"] call comspec_overwatch_connect_fnc_appendLinkLog;
