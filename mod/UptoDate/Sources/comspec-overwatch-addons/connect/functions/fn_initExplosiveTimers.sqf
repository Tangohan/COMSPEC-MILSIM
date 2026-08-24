/*
    Suit les explosifs ACE sans envelopper les fonctions (compileFinal :
    l’affectation est ignorée, plus aucune charge ne remontait).

    Événements officiels :
    - ace_explosives_setup  : placeholder, on mémorise le magasin, on n’envoie rien
    - ace_explosives_place  : charge armée (déclencheur choisi)
    - ace_explosives_defuse : désamorçage
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ExplosiveTimersHooked", false]) exitWith {};
if (!isClass (configFile >> "CfgPatches" >> "ace_explosives")) exitWith {};
if (isNil "CBA_fnc_addEventHandler") exitWith {
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
    if (_s find "atak" >= 0 || {_s find "comspec" >= 0}) exitWith { "atak" };
    if (_s find "cell" >= 0) exitWith { "cellphone" };
    if (_s find "command" >= 0 || {_s find "clacker" >= 0} || {_s find "m57" >= 0} || {_s find "m26" >= 0} || {_s find "mk16" >= 0} || {_s find "deadman" >= 0}) exitWith { "clacker" };
    "command"
};
missionNamespace setVariable ["COMSPEC_ExplosiveKindFromTrigger", _kindFromTrigger, false];

private _kindFromClackers = {
    params ["_explosive", "_unit"];
    private _list = _unit getVariable ["ace_explosives_clackers", []];
    if (!(_list isEqualType [])) exitWith { "" };
    private _kind = "";
    {
        if ((_x isEqualType []) && {(count _x) > 0} && {(_x select 0) isEqualTo _explosive}) then {
            private _trig = if ((count _x) > 4) then { _x select 4 } else { "Command" };
            _kind = [_trig] call (missionNamespace getVariable ["COMSPEC_ExplosiveKindFromTrigger", { "command" }]);
        };
    } forEach _list;
    _kind
};

missionNamespace setVariable ["COMSPEC_ExplosiveKindFromClackers", _kindFromClackers, false];

["ace_explosives_setup", {
    params ["_placeholder", "_magClassname", "_unit"];
    if (!hasInterface) exitWith {};
    if (isNull player) exitWith {};
    if (!(_unit isEqualTo player)) exitWith {};
    if (_magClassname isEqualType "" && {_magClassname isNotEqualTo ""}) then {
        missionNamespace setVariable ["COMSPEC_LastAceExpMag", _magClassname, false];
    };
    if (isNull _placeholder) exitWith {};
    if (_placeholder getVariable ["COMSPEC_atakActionOnPlaceholder", false]) exitWith {};
    _placeholder setVariable ["COMSPEC_atakActionOnPlaceholder", true, false];
    if (!isNil "ace_interact_menu_fnc_createAction" && {!isNil "ace_interact_menu_fnc_addActionToObject"}) then {
        private _arm = [
            "COMSPEC_ArmAtakObj",
            "Uniquement depuis ATAK",
            "\a3\ui_f\data\igui\cfg\simpletasks\types\destroy_ca.paa",
            {
                params ["_target", "_player"];
                [_target, _player] call comspec_overwatch_connect_fnc_chargeArmAtak;
            },
            { true },
            { [] }
        ] call ace_interact_menu_fnc_createAction;
        _arm = [_arm] call comspec_overwatch_connect_fnc_acePadAction;
        if (_arm isNotEqualTo []) then {
            [_placeholder, 0, ["ACE_MainActions", "ACE_SetTrigger"], _arm] call ace_interact_menu_fnc_addActionToObject;
            [_placeholder, 0, ["ACE_MainActions"], _arm] call ace_interact_menu_fnc_addActionToObject;
        };
    };
}] call CBA_fnc_addEventHandler;

["ace_explosives_place", {
    params ["_explosive", "_dir", "_pitch", "_unit"];
    if (!hasInterface) exitWith {};
    if (isNull _explosive) exitWith {};
    if (isNull player) exitWith {};
    if (!(_unit isEqualTo player)) exitWith {};
    if (_explosive getVariable ["COMSPEC_timerReported", false]) exitWith {};

    private _mag = _explosive getVariable ["ace_explosives_class", ""];
    if (!(_mag isEqualType "") || {_mag isEqualTo ""}) then {
        _mag = missionNamespace getVariable ["COMSPEC_LastAceExpMag", ""];
    };
    if (_mag isEqualType "" && {_mag isNotEqualTo ""}) then {
        _explosive setVariable ["ace_explosives_magazineClass", _mag, true];
        _explosive setVariable ["ace_explosives_class", _mag, true];
    };

    // Le minuteur ACE appelle placeExplosive avant closeDialog : le curseur est encore lisible.
    private _timerDisplay = uiNamespace getVariable ["ace_explosives_timerDisplay", displayNull];
    if (!isNull _timerDisplay) then {
        private _slider = _timerDisplay displayCtrl 8505;
        if (!isNull _slider) then {
            private _secNow = floor (sliderPosition _slider);
            if (_secNow >= 1) then {
                missionNamespace setVariable ["COMSPEC_LastAceTimerSec", _secNow, false];
                missionNamespace setVariable ["COMSPEC_LastAceTimerAt", diag_tickTime, false];
            };
        };
    };

    private _kindFn = missionNamespace getVariable ["COMSPEC_ExplosiveKindFromClackers", { "" }];
    private _kind = [_explosive, _unit] call _kindFn;
    private _delay = 0;
    private _timerAt = missionNamespace getVariable ["COMSPEC_LastAceTimerAt", -1e9];
    private _timerSec = missionNamespace getVariable ["COMSPEC_LastAceTimerSec", 0];
    private _timerUiOpen = !isNull _timerDisplay;
    private _timerFresh = ((diag_tickTime - _timerAt) <= 8 && {_timerSec >= 1});
    private _wantAtak = missionNamespace getVariable ["COMSPEC_ArmAsAtak", false];
    if (_wantAtak) then {
        missionNamespace setVariable ["COMSPEC_ArmAsAtak", false, false];
        _kind = "atak";
    };
    if (!_wantAtak && {_timerUiOpen || {_kind isEqualTo "" && {_timerFresh}}}) then {
        _kind = "timer";
        _delay = _timerSec;
    };
    if (_kind isEqualTo "") then {
        _kind = "command";
    };

    _explosive setVariable ["COMSPEC_timerReported", true, true];
    _explosive setVariable ["COMSPEC_triggerKind", _kind, true];
    _explosive setVariable ["COMSPEC_chargeOwnerUid", getPlayerUID _unit, true];
    if (_kind isEqualTo "timer") then {
        _explosive setVariable ["COMSPEC_fuseSeconds", _delay, true];
    };
    if (_kind isEqualTo "atak") then {
        [_explosive, _unit] call comspec_overwatch_connect_fnc_chargeUnhookClacker;
    };

    private _cid = [_explosive, _delay, _unit, "armed", "", _kind] call comspec_overwatch_connect_fnc_reportExplosiveTimer;
    if (_kind isEqualTo "timer" && {_cid isEqualType ""} && {_cid isNotEqualTo ""} && {_delay >= 1}) then {
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
}] call CBA_fnc_addEventHandler;

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

if (!isNil "CBA_fnc_addPerFrameHandler") then {
    [{
        private _display = uiNamespace getVariable ["ace_explosives_timerDisplay", displayNull];
        if (isNull _display) exitWith {};
        private _slider = _display displayCtrl 8505;
        if (isNull _slider) exitWith {};
        private _sec = floor (sliderPosition _slider);
        if (_sec >= 1) then {
            missionNamespace setVariable ["COMSPEC_LastAceTimerSec", _sec, false];
            missionNamespace setVariable ["COMSPEC_LastAceTimerAt", diag_tickTime, false];
        };
    }, 0.2] call CBA_fnc_addPerFrameHandler;
};

if (!isNil "ace_explosives_fnc_addDetonateHandler") then {
    [{
        params ["_unit", "_range", "_explosive", "_fuse", "_triggerItem"];
        if (isNull _explosive) exitWith { true };
        if ((toLower (_explosive getVariable ["COMSPEC_triggerKind", ""])) isNotEqualTo "atak") exitWith { true };
        if (_explosive getVariable ["COMSPEC_atakFireOk", false]) exitWith { true };
        false
    }] call ace_explosives_fnc_addDetonateHandler;
};

[] call comspec_overwatch_connect_fnc_initChargeAceActions;

["INFO", "Explosives", "Suivi des charges ACE (événements, pas de wrap)"] call comspec_overwatch_connect_fnc_log;
