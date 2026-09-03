/*
    Détruit les contrôles COMSPEC 88500+ du display ATAK (pas IceMan).
*/
params [["_disp", displayNull]];
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
};
if (isNull _disp) exitWith {};
{
    _x params ["_a", "_b"];
    for "_i" from _a to _b do {
        private _c = _disp displayCtrl _i;
        if (!isNull _c) then { ctrlDelete _c; };
    };
} forEach [
    [88540, 88540],
    [88550, 88559],
    [88600, 88640],
    [88650, 88650],
    [88700, 88700],
    [88800, 88815],
    [88900, 88924]
];
uiNamespace setVariable ["COMSPEC_MapUI_MouseWired", nil];
diag_log "[COMSPEC][MAP] Overlay destroyed";
