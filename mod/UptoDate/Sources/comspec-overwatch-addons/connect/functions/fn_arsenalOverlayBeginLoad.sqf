/*
    Ouverture du panneau Tenues Athena : affiche « chargement » puis charge en différé
    (aucune synchronisation tant que le panneau est fermé).
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ArsenalOverlayLoadBusy", false]) exitWith {};
private _list = _grp getVariable ["COMSPEC_ArsenalList", controlNull];
if (!isNull _list) then { lnbClear _list; _list lnbAddRow ["Chargement des tenues…", "", ""]; _list lnbSetValue [[0, 0], -1]; };
[_grp, "Chargement depuis Athena…"] call (_grp getVariable ["COMSPEC_ArsenalSetHint", {}]);
missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", true, false];
[_display] spawn {
    params ["_disp"];
    uiSleep 0.05;
    if (!isNull _disp && {_disp getVariable ["COMSPEC_ArsenalOverlayOpen", false]}) then {
        [_disp] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
    };
    missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", false, false];
};
