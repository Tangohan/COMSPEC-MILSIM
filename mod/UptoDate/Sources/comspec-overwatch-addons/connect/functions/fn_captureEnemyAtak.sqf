/*
    Action ACE : marquer l’ATAK d’une unité proche comme capturé (clé incorrecte).
    Params: [_target]
*/
params [["_target", objNull, [objNull]]];

if (!hasInterface) exitWith { false };
if (isNull _target || {!isPlayer _target}) exitWith { false };
if (_target isEqualTo player) exitWith { false };
if ((player distance _target) > 4) exitWith {
    ["Trop loin pour saisir l’appareil.", "system", "warn"] call comspec_overwatch_connect_fnc_ambientHint;
    false
};

["capture", 30] remoteExecCall ["comspec_overwatch_connect_fnc_applyZeusAtakEffect", _target];
[format ["Appareil de %1 marqué comme capturé", name _target], "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
true
