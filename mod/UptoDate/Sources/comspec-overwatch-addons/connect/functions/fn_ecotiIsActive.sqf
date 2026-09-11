/*
    Affichage situation actif maintenant (JVN / vision nocturne, hors carte).
    currentVisionMode == 1 couvre toutes les JVN / NVG, pas une classe d’optique.
*/
if (!hasInterface) exitWith { false };
if (isNull player || {!alive player}) exitWith { false };
if (visibleMap) exitWith { false };
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith { false };

private _nvgOnly = missionNamespace getVariable ["comspec_overwatch_ecoti_nvg_only", true];
if (!_nvgOnly) exitWith { true };

private _vision = currentVisionMode player;
// 1 = NVG / JVN (toutes classes équipées qui basculent en vision nocturne)
_vision isEqualTo 1
