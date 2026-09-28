/*
    Affiche un loader puis charge la collection en différé (pas de sync à l’ouverture arsenal).
*/
params [["_display", displayNull, [displayNull]]];

if (isNull _display) exitWith {};
private _grp = _display getVariable ["COMSPEC_ArsenalOverlay", controlNull];
if (isNull _grp) exitWith {};

if (missionNamespace getVariable ["COMSPEC_ArsenalOverlayLoadBusy", false]) exitWith {};

private _listLocal = _grp getVariable ["COMSPEC_ArsenalLocalList", controlNull];
private _listCloud = _grp getVariable ["COMSPEC_ArsenalList", controlNull];
private _fnc_setHint = _grp getVariable ["COMSPEC_ArsenalSetHint", {}];

if (!isNull _listLocal) then {
    lbClear _listLocal;
    private _i = _listLocal lbAdd "Chargement des tenues locales…";
    _listLocal lbSetValue [_i, -1];
};
if (!isNull _listCloud) then {
    lbClear _listCloud;
    private _i = _listCloud lbAdd "Chargement de la collection organisation…";
    _listCloud lbSetValue [_i, -1];
};
if (_fnc_setHint isEqualType {}) then {
    [_grp, "Chargement en cours — aucune synchronisation tant que ce panneau n’est pas ouvert."] call _fnc_setHint;
};

missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", true, false];

[_display] spawn {
    params ["_disp"];
    uiSleep 0.05;
    if (isNull _disp) exitWith {
        missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", false, false];
    };
    if (!(_disp getVariable ["COMSPEC_ArsenalOverlayOpen", false])) exitWith {
        missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", false, false];
    };
    [_disp] call comspec_overwatch_connect_fnc_arsenalOverlayRefresh;
    missionNamespace setVariable ["COMSPEC_ArsenalOverlayLoadBusy", false, false];
};
