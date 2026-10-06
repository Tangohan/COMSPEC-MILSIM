/*
    Valide le dialogue Zeus « Générer un profil SSE » (idd 93001).
*/
private _display = findDisplay 93001;
if (isNull _display) exitWith { false };

private _comboData = {
    params ["_ctrl", "_default"];
    private _i = lbCurSel _ctrl;
    if (_i < 0) exitWith { _default };
    private _d = _ctrl lbData _i;
    if (_d isEqualTo "") then { _default } else { _d }
};

private _profile = [_display displayCtrl 93010, "INSURGENT"] call _comboData;
private _complexity = [_display displayCtrl 93011, "STANDARD"] call _comboData;
private _options = createHashMapFromArray [
    ["identity", cbChecked (_display displayCtrl 93012)],
    ["phone", cbChecked (_display displayCtrl 93013)],
    ["documents", cbChecked (_display displayCtrl 93014)],
    ["bio", cbChecked (_display displayCtrl 93015)],
    ["network", cbChecked (_display displayCtrl 93016)],
    ["noise", round (sliderPosition (_display displayCtrl 93017))]
];

private _targets = missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []];
closeDialog 1;

if (_targets isEqualTo []) exitWith {
    ["Aucune cible à générer.", "error"] call comspec_sse_fnc_zeusNotify;
    false
};

private _n = [_targets, _profile, _complexity, _options] call comspec_sse_fnc_zeusGenerateTargets;
missionNamespace setVariable ["comspec_sse_zeusPendingTargets", []];
[format ["Génération en file : %1 cible(s)\nProfil %2 · richesse %3", _n, _profile, _complexity]] call comspec_sse_fnc_zeusNotify;
true
