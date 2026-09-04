/*
    [_target] call comspec_sse_fnc_canInspect

    Véhicules / objets : uniquement s’ils sont marqués SSE (Eden, Zeus, site).
    Personnes : menu ACE proposé à tous (PNJ, joueurs, corps). La génération
    reste au clic (doInspect), pas dans cette condition — pour éviter le pic de pile.
*/
params [
    ["_target", objNull, [objNull]]
];

if (isNull _target) exitWith { false };
if (!alive player) exitWith { false };
if (_target getVariable ["comspec_sse_generating", false]) exitWith { false };

// Cockpit Hatchet : le clic et la molette appartiennent au tableau de bord.
if (!isNil "comspec_sse_fnc_playerInHatchetVehicle" && {[] call comspec_sse_fnc_playerInHatchetVehicle}) exitWith { false };
if (!isNil "comspec_sse_fnc_isHatchetVehicle" && {[_target] call comspec_sse_fnc_isHatchetVehicle}) exitWith { false };

if (_target isKindOf "CAManBase") exitWith {
    if (!isNull player && {_target isEqualTo player}) exitWith { false };
    true
};

if (_target getVariable ["comspec_sse_enabled", false]) exitWith { true };
if (!isNil {[_target] call comspec_sse_fnc_getData}) exitWith { true };
if (_target getVariable ["comspec_sse_searchable", false]) exitWith { true };

false
