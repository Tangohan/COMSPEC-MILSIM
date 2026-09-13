/*
    Masque le calque HUD 2D situation (pastilles écran).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_EcotiHudDisp", displayNull];
if (!isNull _disp) then {
    private _pool = uiNamespace getVariable ["COMSPEC_EcotiHudPool", []];
    {
        if (!isNull _x) then {
            _x ctrlShow false;
            _x ctrlCommit 0;
        };
    } forEach _pool;
};

if (missionNamespace getVariable ["COMSPEC_EcotiHudLayerOn", false]) then {
    7755 cutText ["", "PLAIN"];
    missionNamespace setVariable ["COMSPEC_EcotiHudLayerOn", false, false];
    uiNamespace setVariable ["COMSPEC_EcotiHudDisp", displayNull];
    uiNamespace setVariable ["COMSPEC_EcotiHudPool", []];
};
