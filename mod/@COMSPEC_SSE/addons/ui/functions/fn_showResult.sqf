/*
    Affiche le résultat d'exploitation — visionneuse dossier / feuille.
    [_fogHashMap] call comspec_sse_fnc_showResult
*/
params [
    ["_fog", createHashMap, [createHashMap]]
];

if (!hasInterface) exitWith { false };

missionNamespace setVariable ["comspec_sse_lastResult", _fog];
missionNamespace setVariable ["comspec_sse_resultMode", "dossier"];
private _ent = missionNamespace getVariable ["comspec_sse_uiRecord", objNull];
if (!isNull _ent) then {
    missionNamespace setVariable ["comspec_sse_lastResultEntity", _ent];
};

if !(createDialog "COMSPEC_SSE_ResultDialog") exitWith {
    private _lines = _fog getOrDefault ["lines", []];
    hint ((_fog getOrDefault ["title", "SSE"]) + endl + (_lines joinString endl));
    true
};

[_fog, "dossier"] call comspec_sse_fnc_fillResultDialog;
true
