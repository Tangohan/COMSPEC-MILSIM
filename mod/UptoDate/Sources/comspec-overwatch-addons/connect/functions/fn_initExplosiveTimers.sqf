/*
    Intercepte les minuteries ACE (pose + désamorçage + détonation).
    Sans ACE Explosives : no-op.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ExplosiveTimersHooked", false]) exitWith {};
if (isNil "ace_explosives_fnc_startTimer") exitWith {};

missionNamespace setVariable ["COMSPEC_ExplosiveTimersHooked", true, false];

if (isNil "COMSPEC_ExplosiveLocalIds") then {
    missionNamespace setVariable ["COMSPEC_ExplosiveLocalIds", [], false];
};
if (isNil "COMSPEC_ExplosiveOutcomes") then {
    missionNamespace setVariable ["COMSPEC_ExplosiveOutcomes", createHashMap, false];
};

if (isNil "COMSPEC_ACE_startTimerOrig") then {
    missionNamespace setVariable ["COMSPEC_ACE_startTimerOrig", ace_explosives_fnc_startTimer, false];
    ace_explosives_fnc_startTimer = {
        params [["_explosive", objNull, [objNull]], ["_delay", 0, [0]], ["_trigger", "#timer", [""]], ["_unit", objNull, [objNull]]];
        if (
            !isNull _explosive
            && { _delay >= 1 }
            && { !(_explosive getVariable ["COMSPEC_timerReported", false]) }
        ) then {
            _explosive setVariable ["COMSPEC_timerReported", true, true];
            private _cid = [_explosive, _delay, _unit] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
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

["INFO", "Explosives", "Suivi des charges à retardement ACE actif"] call comspec_overwatch_connect_fnc_log;
