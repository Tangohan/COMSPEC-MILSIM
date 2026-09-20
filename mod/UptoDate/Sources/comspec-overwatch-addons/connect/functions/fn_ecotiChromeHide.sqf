/*
    Masque le calque tube (vignette + boussole).
*/
if (!hasInterface) exitWith {};

if (missionNamespace getVariable ["COMSPEC_EcotiChromeLayerOn", false]) then {
    7756 cutText ["", "PLAIN"];
    missionNamespace setVariable ["COMSPEC_EcotiChromeLayerOn", false, false];
    uiNamespace setVariable ["COMSPEC_EcotiChromeDisp", displayNull];
};
