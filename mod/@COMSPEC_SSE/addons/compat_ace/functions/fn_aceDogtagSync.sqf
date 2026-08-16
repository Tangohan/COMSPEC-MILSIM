/*
    Écrit ace_dogtags_dogtagData depuis le profil SSE (si présent).
    [_entity] call comspec_sse_fnc_aceDogtagSync
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]) exitWith { false };
if (_entity getVariable ["comspec_sse_generating", false]) exitWith { false };
if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith { false };
if !(_entity isKindOf "CAManBase") exitWith { false };

private _tag = [_entity] call comspec_sse_fnc_aceDogtagFromSse;
if (_tag isEqualTo []) exitWith { false };

// Local d’abord, public ensuite (réduit le pic EH réseau pendant Init).
_entity setVariable ["ace_dogtags_dogtagData", _tag, false];
if (!isNil "CBA_fnc_waitAndExecute") then {
    [{
        params ["_e", "_t"];
        if (isNull _e) exitWith {};
        if (_e getVariable ["comspec_sse_generating", false]) exitWith {};
        _e setVariable ["ace_dogtags_dogtagData", _t, true];
    }, [_entity, _tag], 0.05] call CBA_fnc_waitAndExecute;
} else {
    _entity setVariable ["ace_dogtags_dogtagData", _tag, true];
};
true
