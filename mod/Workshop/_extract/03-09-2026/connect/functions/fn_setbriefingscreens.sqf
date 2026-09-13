/*
    Enregistre les écrans Eden qui afficheront le briefing (Athena ou Google).
    Params: [[objet, selectionIndex], ...]
    Exemple Init Eden :
      [[briefingScreen1, 0], [briefingScreen2, 0]] call comspec_overwatch_connect_fnc_setBriefingScreens;
*/
private _screens = _this;

if !(_screens isEqualType []) exitWith {
    diag_log "[COMSPEC] Liste d'écrans briefing invalide.";
    []
};

// Compat forme [[[obj,0],[obj2,0]]]
if (
    (count _screens) isEqualTo 1 &&
    {(_screens # 0) isEqualType []} &&
    {count (_screens # 0) > 0} &&
    {((_screens # 0) # 0) isEqualType []}
) then {
    _screens = _screens # 0;
};

private _valid = [];
{
    if (_x isEqualType [] && {count _x >= 1}) then {
        _x params [
            ["_object", objNull, [objNull]],
            ["_selection", 0, [0]]
        ];
        if (!isNull _object && {_selection >= 0}) then {
            _valid pushBack [_object, floor _selection];
        };
    };
} forEach _screens;

missionNamespace setVariable ["COMSPEC_BriefingScreens", _valid];
diag_log format ["[COMSPEC] %1 écran(s) de briefing enregistré(s).", count _valid];
_valid
