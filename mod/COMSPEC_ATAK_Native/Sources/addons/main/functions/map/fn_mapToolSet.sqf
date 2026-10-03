params ["_tool"];
_tool = toUpper _tool;
if !(_tool in ["SELECT","MARKER","PING","MEASURE","HOUSES","HEIGHT","FLAT","LOS","LINE","DRAW","ROUTE"]) exitWith {false};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
_s set ["mapMode",_tool];
if (_tool isNotEqualTo "MEASURE") then { _s set ["mapMeasure",[]]; };
_s set ["drawStroke",[]];
_s set ["drawing",false];
missionNamespace setVariable ["COMSPEC_ATAK_MapTool",_tool,false];
true
