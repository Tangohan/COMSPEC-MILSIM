/*
    Dialogue Zeus « Appliquer un modèle SSE » (idd 93030).
    Cibles : comspec_sse_zeusPendingTargets.
*/
if (!hasInterface) exitWith { false };

private _models = [] call comspec_sse_fnc_listModels;
missionNamespace setVariable ["comspec_sse_dialogModels", _models];
private _targets = missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []];

if (_models isEqualTo []) exitWith {
    ["Aucun modèle SSE disponible.", "warn"] call comspec_sse_fnc_zeusNotify;
    false
};

if !(createDialog "COMSPEC_SSE_ModelDialog") exitWith {
    // Repli : premier modèle intégré
    if (_targets isNotEqualTo []) then {
        private _id = (_models select 0) getOrDefault ["id", ""];
        { [_x, _id, "ZEUS"] call comspec_sse_fnc_applyModel; } forEach _targets;
        [format ["Modèle appliqué (repli) : %1", (_models select 0) getOrDefault ["name", _id]], "warn"] call comspec_sse_fnc_zeusNotify;
    };
    true
};

private _display = findDisplay 93030;
if (isNull _display) exitWith { true };

private _srcLabel = createHashMapFromArray [["builtin", "intégré"], ["mission", "mission"], ["local", "local"], ["profile", "local"]];
private _lb = _display displayCtrl 93031;
{
    private _src = _x getOrDefault ["source", "?"];
    private _idx = _lb lbAdd format ["%1", _x getOrDefault ["name", "?"]];
    _lb lbSetData [_idx, _x getOrDefault ["id", ""]];
    _lb lbSetTextRight [_idx, _srcLabel getOrDefault [toLower _src, _src]];
    _lb lbSetTooltip [_idx, format ["%1 — %2 / %3", _x getOrDefault ["id", ""], _x getOrDefault ["profile", "?"], _x getOrDefault ["theme", "?"]]];
} forEach _models;
_lb lbSetCurSel 0;

(_display displayCtrl 93034) ctrlSetText (if (_targets isEqualTo []) then {
    "Aucune cible : posez le module sur une personne ou un objet."
} else {
    format ["%1 cible(s) sélectionnée(s)", count _targets]
});

true
