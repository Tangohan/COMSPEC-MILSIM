/*
    Branche les événements de combat (tir, impact, missile) pour le journal d’analyse Athena.
    Idempotent — à rappeler après Respawn (les EH objet sont perdus).
    Les rafales sont agrégées ; on n’envoie pas une transmission par balle.
*/
if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };

private _unit = player;

if ((_unit getVariable ["COMSPEC_CombatFiredEH", -1]) < 0) then {
    private _firedId = _unit addEventHandler ["FiredMan", {
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
        params ["_unit", "", "", "", "_ammo", "", "", ""];
        if (isNull _unit || {_unit != player}) exitWith {};
        private _sim = toLower (getText (configFile >> "CfgAmmo" >> _ammo >> "simulation"));
        private _pos = getPosWorld _unit;
        private _detail = createHashMapFromArray [
            ["x", _pos select 0],
            ["y", _pos select 1]
        ];
        if (_sim in ["shotmissile", "shotrocket"]) then {
            _detail set ["out", "shot"];
            ["missile", _detail] call comspec_overwatch_connect_fnc_noteCombatEvent;
        } else {
            ["fire", _detail] call comspec_overwatch_connect_fnc_noteCombatEvent;
        };
    }];
    _unit setVariable ["COMSPEC_CombatFiredEH", _firedId];
};

if ((_unit getVariable ["COMSPEC_CombatMissileEH", -1]) < 0) then {
    private _incId = _unit addEventHandler ["IncomingMissile", {
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
        params ["_target"];
        if (isNull _target || {_target != player}) exitWith {};
        private _pos = getPosWorld _target;
        ["missile", createHashMapFromArray [
            ["out", "attempt"],
            ["x", _pos select 0],
            ["y", _pos select 1]
        ]] call comspec_overwatch_connect_fnc_noteCombatEvent;
        missionNamespace setVariable ["COMSPEC_CombatMissileWatchAt", diag_tickTime, false];
        [{
            private _watch = missionNamespace getVariable ["COMSPEC_CombatMissileWatchAt", -1e9];
            if (_watch < 0) exitWith {};
            private _lastHit = missionNamespace getVariable ["COMSPEC_CombatLastHitAt", -1e9];
            if (_lastHit >= _watch) exitWith {};
            if (isNull player) exitWith {};
            private _pos = getPosWorld player;
            ["missile", createHashMapFromArray [
                ["out", "miss"],
                ["x", _pos select 0],
                ["y", _pos select 1]
            ]] call comspec_overwatch_connect_fnc_noteCombatEvent;
        }, [], 8] call CBA_fnc_waitAndExecute;
    }];
    _unit setVariable ["COMSPEC_CombatMissileEH", _incId];
};

if (!(missionNamespace getVariable ["COMSPEC_CombatAceLockEH", false])) then {
    missionNamespace setVariable ["COMSPEC_CombatAceLockEH", true, false];
    private _fnc_acePos = {
        params ["_out"];
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
        if (isNull player) exitWith {};
        private _pos = getPosWorld player;
        ["missile", createHashMapFromArray [
            ["out", _out],
            ["x", _pos select 0],
            ["y", _pos select 1]
        ]] call comspec_overwatch_connect_fnc_noteCombatEvent;
    };
    missionNamespace setVariable ["COMSPEC_CombatAcePos", _fnc_acePos, false];
    {
        [_x, {
            ["lock"] call (missionNamespace getVariable ["COMSPEC_CombatAcePos", {}]);
        }] call CBA_fnc_addEventHandler;
    } forEach ["ace_lockon_locked", "ace_missileguidance_lockAcquired"];
    ["ace_missileguidance_incoming", {
        ["attempt"] call (missionNamespace getVariable ["COMSPEC_CombatAcePos", {}]);
    }] call CBA_fnc_addEventHandler;
};

true
