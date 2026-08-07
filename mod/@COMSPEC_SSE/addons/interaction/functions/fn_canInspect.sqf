/*
    [_target] call comspec_sse_fnc_canInspect
*/
params [
    ["_target", objNull, [objNull]]
];

if (isNull _target) exitWith { false };
if (!alive player) exitWith { false };

// Toujours autorisé si SSE activé, ou données déjà présentes
if (_target getVariable ["comspec_sse_enabled", false]) exitWith { true };
if (!isNil {[_target] call comspec_sse_fnc_getData}) exitWith { true };

// Lazy gen au premier inspect
if (_target isKindOf "CAManBase") exitWith { true };
if (_target isKindOf "LandVehicle" || {_target isKindOf "Air"} || {_target isKindOf "Ship"}) exitWith { true };
if (_target isKindOf "House" || {_target isKindOf "Building"}) exitWith {
    // Bâtiments : seulement si explicitement marqués (évite spam ACE sur toute maison)
    _target getVariable ["comspec_sse_searchable", false]
};
if (_target isKindOf "ReammoBox_F" || {_target isKindOf "WeaponHolder"} || {_target isKindOf "WeaponHolderSimulated"}) exitWith { true };

// Objets explicitement searchable
_target getVariable ["comspec_sse_searchable", false]
