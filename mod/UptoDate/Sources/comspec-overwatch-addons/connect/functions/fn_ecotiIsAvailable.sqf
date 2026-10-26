/*
    Gates : réglage client (OFF par défaut), module Athena (si le catalogue est reçu), pas de F-PANO.
    Pas de gate « liaison Athena » : tout est calculé en local (unités, repères, véhicules), donc l'affichage
    marche aussi hors ligne, service Athéna « Non » et mode full local (TOC). Avant, il ne s'allumait jamais là.
*/
if (!(missionNamespace getVariable ["comspec_overwatch_ecoti_hud", false])) exitWith { false };
if (
    !isNil "comspec_overwatch_connect_fnc_isModModuleEnabled"
    && {!(["ecoti_hud"] call comspec_overwatch_connect_fnc_isModModuleEnabled)}
) exitWith { false };
if ([] call comspec_overwatch_connect_fnc_ecotiFpanoPresent) exitWith { false };
true
