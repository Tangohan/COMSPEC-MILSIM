/*
    Branche Hit + Explosion sur l’unité joueur (réalisme ATAK).
    Idempotent par unité — à rappeler après Respawn (les EH objet sont perdus).
*/
if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };

private _unit = player;

if ((_unit getVariable ["COMSPEC_AtakHitEH", -1]) < 0) then {
    private _hitId = _unit addEventHandler ["Hit", {
        // EH repris sur l’unité respawnée (variables objet perdues) : seul le dernier ajout agit.
        if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_AtakHitEhId", -1])) exitWith {
            (_this select 0) removeEventHandler ["Hit", _thisEventHandler];
        };
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
        params ["", "", "_damage"];
        if (_damage > 0.1) then {
            private _prev = missionNamespace getVariable ["COMSPEC_LastAtakImpact", 0];
            missionNamespace setVariable ["COMSPEC_LastAtakImpact", _prev max _damage, false];
            if (!isNull player) then {
                private _pos = getPosWorld player;
                ["hit", createHashMapFromArray [
                    ["x", _pos select 0],
                    ["y", _pos select 1]
                ]] call comspec_overwatch_connect_fnc_noteCombatEvent;
            };
        };
        [] call comspec_overwatch_connect_fnc_checkAtakDamage;
    }];
    _unit setVariable ["COMSPEC_AtakHitEH", _hitId];
    missionNamespace setVariable ["COMSPEC_AtakHitEhId", _hitId, false];
};

// Explosion = EH objet (pas Mission EH) — params: unit, damage, explosionSource
if ((_unit getVariable ["COMSPEC_AtakExplosionEH", -1]) < 0) then {
    private _expId = _unit addEventHandler ["Explosion", {
        // EH repris sur l’unité respawnée (variables objet perdues) : seul le dernier ajout agit.
        if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_AtakExplosionEhId", -1])) exitWith {
            (_this select 0) removeEventHandler ["Explosion", _thisEventHandler];
        };
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
        params ["", "_damage"];
        if (_damage > 0.05) then {
            private _prev = missionNamespace getVariable ["COMSPEC_LastAtakImpact", 0];
            missionNamespace setVariable ["COMSPEC_LastAtakImpact", _prev max (_damage min 1), false];
            if (!isNull player) then {
                private _pos = getPosWorld player;
                ["hit", createHashMapFromArray [
                    ["x", _pos select 0],
                    ["y", _pos select 1]
                ]] call comspec_overwatch_connect_fnc_noteCombatEvent;
            };
        };
        [] call comspec_overwatch_connect_fnc_checkAtakDamage;
    }];
    _unit setVariable ["COMSPEC_AtakExplosionEH", _expId];
    missionNamespace setVariable ["COMSPEC_AtakExplosionEhId", _expId, false];
};

if ((_unit getVariable ["COMSPEC_AtakKilledEH", -1]) < 0) then {
    private _kid = _unit addEventHandler ["Killed", {
        // EH repris sur l’unité respawnée (variables objet perdues) : seul le dernier ajout agit.
        if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_AtakKilledEhId", -1])) exitWith {
            (_this select 0) removeEventHandler ["Killed", _thisEventHandler];
        };
        params ["_dead"];
        if (missionNamespace getVariable ["COMSPEC_DisconnectSent", false]) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith {};
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
        if !(missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false]) exitWith {};
        [_dead, "kia"] call comspec_overwatch_connect_fnc_reportMedicalAlert;
    }];
    _unit setVariable ["COMSPEC_AtakKilledEH", _kid];
    missionNamespace setVariable ["COMSPEC_AtakKilledEhId", _kid, false];
};

true
