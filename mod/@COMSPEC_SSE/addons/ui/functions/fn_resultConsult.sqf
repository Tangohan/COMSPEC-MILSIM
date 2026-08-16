/*
    Passe la visionneuse en mode « feuille » (consultation détaillée).
    Remplace l’ancien hint brut CONSULTATION SSE.
*/
private _fog = missionNamespace getVariable ["comspec_sse_lastResult", createHashMap];
if (!(_fog isEqualType createHashMap)) exitWith {
    hint "Aucune pièce à consulter.";
    false
};

missionNamespace setVariable ["comspec_sse_resultMode", "feuille"];

private _disp = uiNamespace getVariable ["COMSPEC_SSE_ResultDisplay", displayNull];
if (isNull _disp) then { _disp = findDisplay 93010; };

if (isNull _disp) then {
    [_fog] call comspec_sse_fnc_showResult;
};

[_fog, "feuille"] call comspec_sse_fnc_fillResultDialog;
true
