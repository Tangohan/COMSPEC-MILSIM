/*
    Dialogue Zeus — choix d'un modèle SSE.
*/
if (!hasInterface) exitWith { false };

private _models = [] call comspec_sse_fnc_listModels;
missionNamespace setVariable ["comspec_sse_dialogModels", _models];

if !(createDialog "COMSPEC_SSE_ModelDialog") exitWith {
    // Fallback : appliquer le premier builtin
    if (count _models > 0 && {count (missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []]) > 0}) then {
        private _id = (_models select 0) getOrDefault ["id", ""];
        {
            [_x, _id, "ZEUS"] call comspec_sse_fnc_applyModel;
        } forEach (missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []]);
        hint format ["Modèle appliqué (fallback) : %1", (_models select 0) getOrDefault ["name", _id]];
    };
    true
};

private _display = findDisplay 93030;
if (isNull _display) exitWith { true };

private _lb = _display displayCtrl 93031;
{
    private _idx = _lb lbAdd format ["%1 [%2]", _x getOrDefault ["name", "?"], _x getOrDefault ["source", "?"]];
    _lb lbSetData [_idx, _x getOrDefault ["id", ""]];
} forEach _models;
if (count _models > 0) then { _lb lbSetCurSel 0; };

true
