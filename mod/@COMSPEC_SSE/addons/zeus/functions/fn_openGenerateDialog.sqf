/*
    Dialogue Zeus de génération (V0.1 — interface légère).
*/
params [
    ["_targets", [], [[]]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]]
];

if (!hasInterface) exitWith { false };

// Si le display custom n'est pas dispo, génération directe
if !(createDialog "COMSPEC_SSE_GenerateDialog") exitWith {
    {
        [_x, _profile, _complexity, "ZEUS"] call comspec_sse_fnc_generateData;
    } forEach _targets;
    hint format ["Profil SSE généré sur %1 cible(s) [%2 / %3]", count _targets, _profile, _complexity];
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
