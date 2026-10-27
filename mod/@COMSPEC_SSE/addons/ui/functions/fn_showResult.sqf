/*
    Affiche le résultat d'exploitation — visionneuse dossier / feuille.
    [_fogHashMap, _entity] call comspec_sse_fnc_showResult
    _entity : sujet réellement affiché (cible de la fouille / exploitation).
    Sans 2e argument (appel legacy), retombe sur le sujet UI courant.
*/
params [
    ["_fog", createHashMap, [createHashMap]],
    ["_entArg", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };

missionNamespace setVariable ["comspec_sse_lastResult", _fog];
missionNamespace setVariable ["comspec_sse_resultMode", "dossier"];
// Toujours réécrire (même objNull) : jamais d'entité périmée d'un dialogue précédent.
private _ent = if (count _this > 1) then { _entArg } else {
    missionNamespace getVariable ["comspec_sse_uiRecord", objNull]
};
missionNamespace setVariable ["comspec_sse_lastResultEntity", _ent];

if !(createDialog "COMSPEC_SSE_ResultDialog") exitWith {
    private _lines = _fog getOrDefault ["lines", []];
    [(_fog getOrDefault ["title", "SSE"]) + endl + (_lines joinString endl), "INFO"] call comspec_sse_fnc_notify;
    true
};

[_fog, "dossier"] call comspec_sse_fnc_fillResultDialog;
true
