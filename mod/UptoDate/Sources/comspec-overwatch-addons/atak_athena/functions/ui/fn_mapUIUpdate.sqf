/*
    Boucle UI carte : DATA seulement. Pas de menu clic droit, pas de rail Mes/Gril/Zone.
    Le cartouche identité reste dans fn_athena_updateMapHud (99887812).
*/
params [
    ["_disp", displayNull],
    ["_mapCtrl", controlNull],
    ["_vis", []]
];
if (isNull _disp) exitWith {};

private _ranges = [
    [88540, 88540],
    [88550, 88559],
    [88600, 88640],
    [88650, 88650],
    [88700, 88700],
    [88800, 88815],
    [88900, 88924]
];
{
    _x params ["_a", "_b"];
    for "_i" from _a to _b do {
        private _c = _disp displayCtrl _i;
        if (!isNull _c) then {
            _c ctrlShow false;
            _c ctrlEnable false;
        };
    };
} forEach _ranges;

if (!(missionNamespace getVariable ["COMSPEC_MapUI_ChromeCleared", false])) then {
    missionNamespace setVariable ["COMSPEC_MapUI_ChromeCleared", true, false];
    [_disp] call comspec_overwatch_atak_athena_fnc_mapUIDestroy;
};

[] call comspec_overwatch_atak_athena_fnc_collectMapState;
[] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
