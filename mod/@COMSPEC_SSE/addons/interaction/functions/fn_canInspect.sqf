/*
    [_target] call comspec_sse_fnc_canInspect

    Ne pas exposer le menu SSE sur TOUT CAManBase/véhicule : ça force ACE à
    évaluer des dizaines d’actions sur chaque unité et déclenche ensureGenerated
    hors intention mission maker → pic de pile / crash.
*/
params [
    ["_target", objNull, [objNull]]
];

if (isNull _target) exitWith { false };
if (!alive player) exitWith { false };
if (_target getVariable ["comspec_sse_generating", false]) exitWith { false };

// SSE explicitement activé (Eden / Zeus / script)
if (_target getVariable ["comspec_sse_enabled", false]) exitWith { true };

// Données déjà présentes
if (!isNil {[_target] call comspec_sse_fnc_getData}) exitWith { true };

// Marqué searchable (lazy sans enabled)
if (_target getVariable ["comspec_sse_searchable", false]) exitWith { true };

false
