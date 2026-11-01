/*
    Ouverture du panneau Tenues Athena : affiche « chargement » à la place des cartes puis charge en différé
    (aucune synchronisation tant que le panneau est fermé). La ligne de retour dit ensuite ce qui a été chargé.
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
if (missionNamespace getVariable ["COMSPEC_ArsenalOverlayLoadBusy", false]) exitWith {};
{ { _x ctrlShow false; } forEach (_x select 12); (_x select 0) setVariable ["idx", -1]; } forEach (_grp getVariable ["COMSPEC_ArsenalCards", []]);
private _empty = _grp getVariable ["COMSPEC_ArsenalEmpty", controlNull];
if (!isNull _empty) then {
    _empty ctrlSetStructuredText parseText (((format ["<t align='center' size='0.85' color='#a9b8ae'>%1</t>", ("Chargement des tenues…") call comspec_overwatch_connect_fnc_tr])) call comspec_overwatch_connect_fnc_tr);
    _empty ctrlShow true;
};
[_grp, "Chargement depuis Athena…", "busy"] call (_grp getVariable ["COMSPEC_ArsenalSetHint", {}]);
missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", true, false];
[_display] spawn {
    params ["_disp"];
    uiSleep 0.05;
    if (!isNull _disp && {_disp getVariable ["COMSPEC_ArsenalOverlayOpen", false]}) then {
        [_disp] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
        ["loaded"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
    };
    missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", false, false];
};
