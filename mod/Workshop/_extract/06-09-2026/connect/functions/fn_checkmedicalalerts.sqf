/*
    Surveille l'état médical local (ACE Medical) et déclenche les alertes ATAK.
    Seuls les états visibles / transmissibles en roleplay : inconscient, arrêt cardiaque.
    Appelé depuis le PFH position et depuis les événements ACE.
    Au rétablissement, clôture l’alerte active côté Athena.

    Anti faux positif spawn / JIP : exige plusieurs lectures critiques consécutives
    (ACE_isUnconscious / lifeState INCAPACITATED peuvent flasher au boot).
*/
params [["_unit", player, [objNull]]];
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (isNull _unit || {!local _unit}) exitWith {};
if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
if (isNull findDisplay 46) exitWith {};
if (isMultiplayer && {getClientStateNumber >= 11}) exitWith {};
// Mort / hors combat : géré par l’EH Killed (fn_attachAtakDamageHandlers) — pas ici.
if (!alive _unit) exitWith {};
if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith {};
if !([] call comspec_overwatch_connect_fnc_isPlayerSpawnStable) exitWith {};
// Même garde que la sync position : pas d’alerte médicale sans terminal (évite KO fantôme + carte vide).
if !([_unit] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {};

private _hasAce = isClass (configFile >> "CfgPatches" >> "ace_medical");
// Sans ACE : pas de détection automatique (évite les faux positifs vanilla).
if (!_hasAce) exitWith {};

private _state = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _parts = _state splitString "|";
private _health = if (count _parts >= 1) then { _parts select 0 } else { "stable" };
private _cardiac = (count _parts >= 5) && {(_parts select 4) == "1"};

private _kind = "";
if (_cardiac || {_health == "cardiac_arrest"}) then {
    _kind = "cardiac_arrest";
} else {
    if (_health == "unconscious") then {
        _kind = "unconscious";
    };
};

if (_kind != "") then {
    private _streakKey = "COMSPEC_MedicalAlertConfirm_" + _kind;
    private _streak = missionNamespace getVariable [_streakKey, 0];
    _streak = _streak + 1;
    missionNamespace setVariable [_streakKey, _streak, false];
    // Autre kind : reset l’autre compteur
    if (_kind == "unconscious") then {
        missionNamespace setVariable ["COMSPEC_MedicalAlertConfirm_cardiac_arrest", 0, false];
    } else {
        missionNamespace setVariable ["COMSPEC_MedicalAlertConfirm_unconscious", 0, false];
    };
    // 3 ticks PFH (~quelques secondes) avant publication Athena
    private _need = if (_kind == "cardiac_arrest") then { 2 } else { 3 };
    if (_streak < _need) exitWith {};
    [_unit, _kind] call comspec_overwatch_connect_fnc_reportMedicalAlert;
} else {
    missionNamespace setVariable ["COMSPEC_MedicalAlertConfirm_unconscious", 0, false];
    missionNamespace setVariable ["COMSPEC_MedicalAlertConfirm_cardiac_arrest", 0, false];
    if (_health in ["stable", "wounded", "critical"] && {!_cardiac}) then {
        private _last = missionNamespace getVariable ["COMSPEC_lastMedicalAlertKind", ""];
        private _own = [] call comspec_overwatch_connect_fnc_hasOwnActiveMedicalAlert;
        if (!(_last isEqualTo "") || {count _own > 0}) then {
            [true] call comspec_overwatch_connect_fnc_selfCancelMedicalAlert;
        };
    };
};
