// Parse JSON fire solution string and store/display. Param: [jsonString]
params [["_jsonStr", "", [""]]];
if (_jsonStr isEqualTo "") exitWith { nil };

missionNamespace setVariable ["COMSPEC_LastFireSolution", _jsonStr, true];
[_jsonStr] call comspec_overwatch_connect_fnc_displayFireSolution;
_jsonStr
