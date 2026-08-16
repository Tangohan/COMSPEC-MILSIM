/*
    Écrit ace_dogtags_dogtagData depuis le profil SSE (si présent).
    [_entity] call comspec_sse_fnc_aceDogtagSync
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNull _entity) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_aceDogtagBridgeEnabled", true]) exitWith { false };
if !([] call comspec_sse_fnc_aceDogtagIsPresent) exitWith { false };
if !(_entity isKindOf "CAManBase") exitWith { false };

private _tag = [_entity] call comspec_sse_fnc_aceDogtagFromSse;
if (_tag isEqualTo []) exitWith { false };

_entity setVariable ["ace_dogtags_dogtagData", _tag, true];
true
