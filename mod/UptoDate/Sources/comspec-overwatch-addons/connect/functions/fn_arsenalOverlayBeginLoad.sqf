/*
    Ouverture du panneau Tenues Athena : cartes en squelette (cadres et barres grises, rien de cliquable) pendant le
    chargement, puis chargement en différé (aucune synchronisation tant que le panneau est fermé). La ligne de retour
    dit ensuite ce qui a été chargé. Aucun contrôle créé : ce sont les cartes d'arsenalOverlayShow.
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};
// Chargement déjà en cours (une ouverture précédente) : le panneau rouvert montre ce qui est déjà chargé, jamais des
// contrôles vides. Au-delà de 30 s, l'ancien chargement est considéré comme perdu.
if ((missionNamespace getVariable ["COMSPEC_ArsenalOverlayLoadBusy", false]) && {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ArsenalOverlayLoadAt", -100])) < 30}) exitWith {
    [_display, ""] call comspec_overwatch_connect_fnc_arsenalOverlayFill;
};
{
    _x params ["_hit", "_outer", "_inner", "_stripe", "_pbg", "", "", "_name", "", "_meta", "", "", "_all", "_tW"];
    { _x ctrlShow false; } forEach _all;
    _hit setVariable ["idx", -1];
    _outer ctrlSetBackgroundColor [0.12, 0.15, 0.13, 1];
    _inner ctrlSetBackgroundColor [0.052, 0.063, 0.056, 1];
    _stripe ctrlSetBackgroundColor [0.16, 0.20, 0.17, 1];
    _name ctrlSetText (("") call comspec_overwatch_connect_fnc_tr);
    _meta ctrlSetText (("") call comspec_overwatch_connect_fnc_tr);
    _name ctrlSetBackgroundColor [0.12, 0.15, 0.13, 1];
    _meta ctrlSetBackgroundColor [0.09, 0.11, 0.10, 1];
    (_name getVariable ["rect", ctrlPosition _name]) params ["_nx", "_ny", "", "_nh"];
    _name ctrlSetPosition [_nx, _ny + _nh * 0.2, _tW * 0.62, _nh * 0.6];
    _name ctrlCommit 0;
    (_meta getVariable ["rect", ctrlPosition _meta]) params ["_mx", "_my", "", "_mh"];
    _meta ctrlSetPosition [_mx, _my, _tW * 0.4, _mh * 0.6];
    _meta ctrlCommit 0;
    { _x ctrlShow true; } forEach [_outer, _inner, _stripe, _pbg, _name, _meta];
} forEach (_grp getVariable ["COMSPEC_ArsenalCards", []]);
private _empty = _grp getVariable ["COMSPEC_ArsenalEmpty", controlNull];
if (!isNull _empty) then { _empty ctrlShow false; };
[_grp, "Chargement depuis Athena…", "busy"] call (_grp getVariable ["COMSPEC_ArsenalSetHint", {}]);
missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", true, false];
missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadAt", diag_tickTime, false];
[_display] spawn {
    params ["_disp"];
    uiSleep 0.05;
    if (!isNull _disp && {_disp getVariable ["COMSPEC_ArsenalOverlayOpen", false]}) then {
        [_disp] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
        ["loaded"] call comspec_overwatch_connect_fnc_arsenalOverlayAction;
    };
    missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", false, false];
};
