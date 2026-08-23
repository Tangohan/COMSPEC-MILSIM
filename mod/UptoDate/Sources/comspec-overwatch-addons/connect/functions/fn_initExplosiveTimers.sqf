/*
    Intercepte les explosifs ACE (pose, déclencheur, minuterie, désamorçage).
    Sans ACE Explosives : no-op.

    Ne pas remonter ace_explosives_setup : c’est le placeholder AVANT le choix
    du déclencheur (délai inconnu). Un fuse 0 y était forcé à 1 s côté Athena.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ExplosiveTimersHooked", false]) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_explosives")) exitWith {};
if (isNil "ace_explosives_fnc_startTimer" || {isNil "ace_explosives_fnc_placeExplosive"}) exitWith {
    [{ [] call comspec_overwatch_connect_fnc_initExplosiveTimers }, [], 3] call CBA_fnc_waitAndExecute;
};

missionNamespace setVariable ["COMSPEC_ExplosiveTimersHooked", true, false];

if (isNil "COMSPEC_ExplosiveLocalIds") then {
    missionNamespace setVariable ["COMSPEC_ExplosiveLocalIds", [], false];
};
if (isNil "COMSPEC_ExplosiveOutcomes") then {
    missionNamespace setVariable ["COMSPEC_ExplosiveOutcomes", createHashMap, false];
};
if (isNil "COMSPEC_ExplosiveObjects") then {
    missionNamespace setVariable ["COMSPEC_ExplosiveObjects", createHashMap, false];
};

private _kindFromTrigger = {
    params ["_cfg"];
    private _s = toLower (if (_cfg isEqualType "") then { _cfg } else { format ["%1", _cfg] });
    if (_s find "timer" >= 0) exitWith { "timer" };
    if (_s find "cell" >= 0) exitWith { "cellphone" };
    if (_s find "command" >= 0 || {_s find "clacker" >= 0} || {_s find "m57" >= 0} || {_s find "m26" >= 0} || {_s find "mk16" >= 0}) exitWith { "clacker" };
    "command"
};

private _delayFromValue = {
    params ["_value"];
    if (_value isEqualType 0) exitWith { _value };
    if (_value isEqualType []) exitWith {
        private _found = _value select { _x isEqualType 0 && {_x >= 1} };
        if ((count _found) > 0) then { _found select 0 } else { 0 }
    };
    0
};

missionNamespace setVariable ["COMSPEC_ExplosiveKindFromTrigger", _kindFromTrigger, false];
missionNamespace setVariable ["COMSPEC_ExplosiveDelayFromValue", _delayFromValue, false];

if (isNil "COMSPEC_ACE_startTimerOrig" && {!isNil "ace_explosives_fnc_startTimer"}) then {
    missionNamespace setVariable ["COMSPEC_ACE_startTimerOrig", ace_explosives_fnc_startTimer, false];
    ace_explosives_fnc_startTimer = {
        private _extract = missionNamespace getVariable ["COMSPEC_ExplosiveDelayFromValue", { 0 }];
        private _explosive = if ((count _this) > 0 && {(_this select 0) isEqualType objNull}) then { _this select 0 } else { objNull };
        private _delay = [if ((count _this) > 1) then { _this select 1 } else { 0 }] call _extract;
        private _unit = objNull;
        if ((count _this) > 3 && {(_this select 3) isEqualType objNull}) then {
            _unit = _this select 3;
        } else {
            if ((count _this) > 2 && {(_this select 2) isEqualType objNull}) then { _unit = _this select 2 };
        };
        if (!isNull _explosive && {_delay >= 1}) then {
            _explosive setVariable ["COMSPEC_timerReported", true, true];
            _explosive setVariable ["COMSPEC_fuseSeconds", _delay, true];
            private _cid = [_explosive, _delay, _unit, "armed", "", "timer"] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
            if (_cid isEqualType "" && {_cid isNotEqualTo ""}) then {
                [{
                    params ["_cid", "_exp"];
                    private _outcomes = missionNamespace getVariable ["COMSPEC_ExplosiveOutcomes", createHashMap];
                    if (!(_outcomes isEqualType createHashMap)) then { _outcomes = createHashMap; };
                    if ((_outcomes getOrDefault [_cid, ""]) isNotEqualTo "") exitWith {};
                    if (!isNull _exp) exitWith {};
                    _outcomes set [_cid, "detonated"];
                    missionNamespace setVariable ["COMSPEC_ExplosiveOutcomes", _outcomes, false];
                    [objNull, 0, objNull, "detonated", _cid] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
                }, [_cid, _explosive], _delay + 1.5] call CBA_fnc_waitAndExecute;
            };
        };
        private _orig = missionNamespace getVariable ["COMSPEC_ACE_startTimerOrig", {}];
        _this call _orig;
    };
};

if (isNil "COMSPEC_ACE_placeExplosiveOrig" && {!isNil "ace_explosives_fnc_placeExplosive"}) then {
    missionNamespace setVariable ["COMSPEC_ACE_placeExplosiveOrig", ace_explosives_fnc_placeExplosive, false];
    ace_explosives_fnc_placeExplosive = {
        private _orig = missionNamespace getVariable ["COMSPEC_ACE_placeExplosiveOrig", {}];
        private _extract = missionNamespace getVariable ["COMSPEC_ExplosiveDelayFromValue", { 0 }];
        private _kindFn = missionNamespace getVariable ["COMSPEC_ExplosiveKindFromTrigger", { "command" }];
        private _unit = if ((count _this) > 0 && {(_this select 0) isEqualType objNull}) then { _this select 0 } else { player };
        private _triggerCfg = if ((count _this) > 4) then { _this select 4 } else { "" };
        private _vars = if ((count _this) > 5 && {(_this select 5) isEqualType []}) then { _this select 5 } else { [] };
        private _kind = [_triggerCfg] call _kindFn;
        private _delay = [_vars] call _extract;
        private _exp = _this call _orig;
        if (!(_exp isEqualType objNull) || {isNull _exp}) exitWith { _exp };
        if (_kind isEqualTo "timer") then {
            if (_delay < 1) then {
                _delay = [_exp getVariable ["COMSPEC_fuseSeconds", 0]] call _extract;
            };
            if (_delay < 1) exitWith { _exp };
            _exp setVariable ["COMSPEC_timerReported", true, true];
            _exp setVariable ["COMSPEC_fuseSeconds", _delay, true];
            [_exp, _delay, _unit, "armed", "", "timer"] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
        } else {
            [_exp, 0, _unit, "armed", "", _kind] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
        };
        _exp
    };
};

["ace_explosives_defuse", {
    params ["_explosive", "_unit"];
    private _cid = "";
    if (!isNull _explosive) then {
        _cid = _explosive getVariable ["COMSPEC_chargeId", ""];
        if (!(_cid isEqualType "")) then { _cid = ""; };
        if (_cid isEqualTo "") then { _cid = netId _explosive; };
    };
    if (_cid isEqualTo "") exitWith {};
    private _local = missionNamespace getVariable ["COMSPEC_ExplosiveLocalIds", []];
    if (!(_local isEqualType [])) then { _local = []; };
    if (!(_cid in _local)) exitWith {};
    private _outcomes = missionNamespace getVariable ["COMSPEC_ExplosiveOutcomes", createHashMap];
    if (!(_outcomes isEqualType createHashMap)) then { _outcomes = createHashMap; };
    if ((_outcomes getOrDefault [_cid, ""]) isNotEqualTo "") exitWith {};
    _outcomes set [_cid, "defused"];
    missionNamespace setVariable ["COMSPEC_ExplosiveOutcomes", _outcomes, false];
    [objNull, 0, _unit, "defused", _cid] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
}] call CBA_fnc_addEventHandler;

["INFO", "Explosives", "Suivi des charges ACE (minuterie et déclenchement) actif"] call comspec_overwatch_connect_fnc_log;
