/*
    Après un scan BII : rattache le record à l’unité cible SSE.
    [_target, _record] call comspec_sse_fnc_biiImportScan
*/
params [
    ["_target", objNull, [objNull]],
    ["_record", [], [[]]]
];

if (isNull _target || {_record isEqualTo []}) exitWith { false };
if !(missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]) exitWith { false };

[_target, _record] call comspec_sse_fnc_biiRecordToSse
