/*
    Gates : Overwatch actif, module Athena, réglage client (OFF par défaut), pas de F-PANO.
*/
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_ecoti_hud", false])) exitWith { false };
if (
    !isNil "comspec_overwatch_connect_fnc_isModModuleEnabled"
    && {!(["ecoti_hud"] call comspec_overwatch_connect_fnc_isModModuleEnabled)}
) exitWith { false };
if ([] call comspec_overwatch_connect_fnc_ecotiFpanoPresent) exitWith { false };
true
