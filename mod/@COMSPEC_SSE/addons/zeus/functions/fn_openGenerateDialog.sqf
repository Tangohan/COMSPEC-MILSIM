/*
    Dialogue Zeus « Générer un profil SSE » (idd 93001).
    [_targets, _profile, _complexity, _noisePct] call comspec_sse_fnc_openGenerateDialog
    Les cibles sont gardées dans comspec_sse_zeusPendingTargets jusqu'à validation.
*/
params [
    ["_targets", [], [[]]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_noisePct", 25, [0]]
];

if (!hasInterface) exitWith { false };
missionNamespace setVariable ["comspec_sse_zeusPendingTargets", _targets];

// Display indisponible : génération directe avec les valeurs reçues.
if !(createDialog "COMSPEC_SSE_GenerateDialog") exitWith {
    private _n = [_targets, _profile, _complexity, createHashMapFromArray [["noise", _noisePct]]] call comspec_sse_fnc_zeusGenerateTargets;
    [format ["Profil SSE en file sur %1 cible(s) — %2 / %3", _n, _profile, _complexity]] call comspec_sse_fnc_zeusNotify;
    true
};

private _display = findDisplay 93001;
if (isNull _display) exitWith { true };

private _fill = {
    params ["_ctrl", "_kind", "_current"];
    ([_kind] call comspec_sse_fnc_zeusChoices) params ["_vals", "_labs"];
    private _sel = 0;
    {
        private _i = _ctrl lbAdd (_labs select _forEachIndex);
        _ctrl lbSetData [_i, _x];
        if (_x isEqualTo (toUpper _current)) then { _sel = _i; };
    } forEach _vals;
    _ctrl lbSetCurSel _sel;
};
[_display displayCtrl 93010, "profile", _profile] call _fill;
[_display displayCtrl 93011, "complexity", _complexity] call _fill;

private _names = (_targets select [0, 3]) apply {
    if (_x isKindOf "CAManBase") then { name _x } else { getText (configOf _x >> "displayName") }
};
private _more = if (count _targets > 3) then { format [" (+%1)", (count _targets) - 3] } else { "" };
(_display displayCtrl 93018) ctrlSetText format ["%1 cible(s) : %2%3", count _targets, _names joinString ", ", _more];

{ (_display displayCtrl _x) cbSetChecked true; } forEach [93012, 93013, 93014, 93015];
(_display displayCtrl 93016) cbSetChecked (count _targets > 1);

private _slider = _display displayCtrl 93017;
_slider sliderSetRange [0, 100];
_slider sliderSetSpeed [5, 10];
_slider sliderSetPosition (_noisePct max 0 min 100);
(_display displayCtrl 93019) ctrlSetText format ["%1 %2", round (_noisePct max 0 min 100), "%"];

true
