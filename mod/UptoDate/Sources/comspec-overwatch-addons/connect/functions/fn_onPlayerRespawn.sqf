/*
    Grâce post-REAPP / Respawn :
    - gèle MessageBox Win32, alertes médicales, sync position agressive
    - évite faux KIA (mort puis respawn immédiat)
    - reset état médical local
*/
if (!hasInterface) exitWith {};

private _graceSec = 25;
private _until = diag_tickTime + _graceSec;

missionNamespace setVariable ["COMSPEC_RespawnGraceUntil", _until, false];
missionNamespace setVariable ["COMSPEC_SuppressWinMessageBoxUntil", _until, false];
missionNamespace setVariable ["COMSPEC_CancelPendingAthenaHelp", true, false];
missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", false, false];
missionNamespace setVariable ["COMSPEC_lastMedicalAlertKind", "", false];
missionNamespace setVariable ["COMSPEC_lastMedicalAlertAt", -1e9, false];
missionNamespace setVariable ["COMSPEC_MedicalAlertBusy", false, false];
missionNamespace setVariable ["COMSPEC_MedicalCritStreak", 0, false];
missionNamespace setVariable ["COMSPEC_MedicalAlertConfirm_unconscious", 0, false];
missionNamespace setVariable ["COMSPEC_MedicalAlertConfirm_cardiac_arrest", 0, false];
missionNamespace setVariable ["COMSPEC_DeathThenRespawn", true, false];

// Éviter anomalie « saut » + flood UpdatePosition / véhicule juste après REAPP
if (!isNull player) then {
    missionNamespace setVariable ["COMSPEC_lastPos", getPosWorld player, true];
    private _restoreTeam = toUpper (trim (missionNamespace getVariable ["COMSPEC_AssignedTeam", ""]));
    if (_restoreTeam isEqualTo "") then {
        _restoreTeam = toUpper (trim (profileNamespace getVariable ["COMSPEC_AssignedTeam", ""]));
    };
    if (_restoreTeam in ["RED", "GREEN", "BLUE", "YELLOW", "MAIN"]) then {
        player assignTeam _restoreTeam;
    };
};
missionNamespace setVariable ["COMSPEC_lastSendTime", diag_tickTime, true];
if (!isNull player) then {
    missionNamespace setVariable ["COMSPEC_lastHeading", getDir player, true];
};
missionNamespace setVariable ["COMSPEC_lastVehSig", "", true];
missionNamespace setVariable ["COMSPEC_VehTrackLastAt", diag_tickTime, false];

// Hit / Explosion sont des EH objet : perdus avec l’ancienne unité
[] call comspec_overwatch_connect_fnc_attachAtakDamageHandlers;

["INFO", "Respawn", format ["Grâce %1s — médical / MessageBox / sync gelés", _graceSec]] call comspec_overwatch_connect_fnc_log;

// Une seule ré-armement planifié (dernier respawn gagne)
private _token = (missionNamespace getVariable ["COMSPEC_RespawnGraceToken", 0]) + 1;
missionNamespace setVariable ["COMSPEC_RespawnGraceToken", _token, false];

[{
    params ["_token"];
    if (_token != (missionNamespace getVariable ["COMSPEC_RespawnGraceToken", 0])) exitWith {};
    if (isNull player || {!alive player}) exitWith {};
    if (isNull findDisplay 46) exitWith {};
    if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", 0])) exitWith {};

    missionNamespace setVariable ["COMSPEC_MedicalAlertsArmed", true, false];
    missionNamespace setVariable ["COMSPEC_SpawnStableAt", diag_tickTime, false];
    missionNamespace setVariable ["COMSPEC_DeathThenRespawn", false, false];
    missionNamespace setVariable ["COMSPEC_CancelPendingAthenaHelp", false, false];
    ["INFO", "Respawn", "Grâce terminée — alertes médicales réarmées"] call comspec_overwatch_connect_fnc_log;
}, [_token], _graceSec + 0.5] call CBA_fnc_waitAndExecute;
