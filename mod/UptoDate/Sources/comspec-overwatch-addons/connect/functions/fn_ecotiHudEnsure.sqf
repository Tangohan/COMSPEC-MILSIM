/*
    Affiche (ou réaffiche) le calque HUD 2D des pastilles situation.
    Retourne le display ou displayNull.
*/
if (!hasInterface) exitWith { displayNull };

private _disp = uiNamespace getVariable ["COMSPEC_EcotiHudDisp", displayNull];
if (!isNull _disp) exitWith { _disp };

7755 cutRsc ["COMSPEC_EcotiScreenHud", "PLAIN", 0, false];
_disp = uiNamespace getVariable ["COMSPEC_EcotiHudDisp", displayNull];
_disp
