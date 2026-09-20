/*
    Applique le réglage découpage d’étage.
    Source de vérité : le réglage CBA (missionNamespace + persistance CBA).
    Params: [_enabled, _persist]
*/
params [["_enabled", false, [true]], ["_persist", true, [true]]];

if (!(_enabled isEqualType true)) then { _enabled = false; };

missionNamespace setVariable ["comspec_overwatch_ecoti_building_cutaway", _enabled, false];
if (!_enabled) then {
    missionNamespace setVariable ["COMSPEC_EcotiCutawayFloor", 0, false];
};

if (_persist && {!isNil "cba_settings_fnc_set"}) then {
    ["comspec_overwatch_ecoti_building_cutaway", _enabled, 2, "client"] call cba_settings_fnc_set;
};

_enabled
