/*
    Dessine la zone de déplacement sur la carte ATAK IceMan / cTab.
*/
params [["_ctrl", controlNull, [controlNull]]];
if (isNull _ctrl) exitWith {};
[_ctrl] call comspec_overwatch_connect_fnc_reachOverlayDraw;
[_ctrl] call comspec_overwatch_connect_fnc_superPingDraw;
