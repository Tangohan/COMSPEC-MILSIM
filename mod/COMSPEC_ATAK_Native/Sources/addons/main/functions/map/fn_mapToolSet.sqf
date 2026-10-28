params ["_tool"];
_tool = toUpper _tool;
if !(_tool in ["SELECT","MARKER","PING","MEASURE","HOUSES","HEIGHT","FLAT","LOS","LINE","DRAW","ROUTE","WP","DRONE","IPTGT"]) exitWith {false};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
_s set ["mapMode",_tool];
if (_tool isNotEqualTo "MEASURE") then { _s set ["mapMeasure",[]]; };
_s set ["drawStroke",[]];
_s set ["drawing",false];
// Point de pose de la palette (fn_markerPlace) : oublié en quittant le mode MARQUEUR.
if (_tool isNotEqualTo "MARKER") then { _s set ["markerAnchor",[]]; };
// Fiche des points de passage (fn_wpCard) : point choisi et étape modifiée oubliés en quittant le mode.
if (_tool isNotEqualTo "WP") then { _s set ["wpAnchor",[]]; _s set ["wpSel",-1]; };
missionNamespace setVariable ["COMSPEC_ATAK_MapTool",_tool,false];
true
