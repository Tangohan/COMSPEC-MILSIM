/*
    Dialogue Zeus de génération (V0.1 — interface légère).
*/
params [
    ["_targets", [], [[]]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]]
];

if (!hasInterface) exitWith { false };

// Si le display custom n'est pas dispo, génération directe (file étalée)
if !(createDialog "COMSPEC_SSE_GenerateDialog") exitWith {
    private _jobs = _targets apply { [_x, _profile, _complexity, "ZEUS"] };
    [
        _jobs,
        {
            params ["_ent", "_profile", "_complexity", "_by"];
            if (isNull _ent) exitWith {};
            if (_ent getVariable ["comspec_sse_generating", false]) exitWith {};
            [_ent, _profile, _complexity, _by] call comspec_sse_fnc_generateData;
        },
        0.28
    ] call comspec_sse_fnc_queueEntityJobs;
    hint format ["Profil SSE en file sur %1 cible(s) [%2 / %3]", count _jobs, _profile, _complexity];
    true
};

private _display = findDisplay 93001;
if (isNull _display) exitWith { true };

(_display displayCtrl 93010) lbAdd "INSURGENT";
(_display displayCtrl 93010) lbAdd "CIVILIAN";
(_display displayCtrl 93010) lbAdd "MILITARY";
(_display displayCtrl 93010) lbAdd "COMMANDER";
(_display displayCtrl 93010) lbAdd "COURIER";
(_display displayCtrl 93010) lbAdd "FINANCIER";
(_display displayCtrl 93010) lbAdd "TECHNICIAN";
(_display displayCtrl 93010) lbAdd "INTELLIGENCE";
(_display displayCtrl 93010) lbAdd "LOGISTICS";
(_display displayCtrl 93010) lbAdd "RANDOM";
(_display displayCtrl 93010) lbSetCurSel 0;

(_display displayCtrl 93011) lbAdd "LIGHT";
(_display displayCtrl 93011) lbAdd "STANDARD";
(_display displayCtrl 93011) lbAdd "DETAILED";
(_display displayCtrl 93011) lbAdd "HIGH_VALUE";
(_display displayCtrl 93011) lbSetCurSel 1;

(_display displayCtrl 93012) cbSetChecked true;
(_display displayCtrl 93013) cbSetChecked true;
(_display displayCtrl 93014) cbSetChecked true;
(_display displayCtrl 93015) cbSetChecked true;
(_display displayCtrl 93016) cbSetChecked true;
(_display displayCtrl 93017) sliderSetRange [0, 100];
(_display displayCtrl 93017) sliderSetPosition 25;

true
