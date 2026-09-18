/*
    Lance le dépannage liaison sans empiler de fenêtre.
    Ferme d’abord pause / confirm / Échap, puis démarre le bandeau 55 s.
*/
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };
if (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]) exitWith {
    ["COMSPEC_Warning", ["Un dépannage est déjà en cours."]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

["WARN", "Diag", "Dépannage liaison demandé"] call comspec_overwatch_connect_fnc_log;

{
    private _d = findDisplay _x;
    if (!isNull _d) then { _d closeDisplay 1; };
} forEach [9979, 9995, 49];
closeDialog 0;

[] spawn {
    uiSleep 0.25;
    if (!hasInterface) exitWith {};
    if (isNull player) exitWith {};
    if (missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false]) exitWith {};
    [] call comspec_overwatch_connect_fnc_diagIsolateStart;
};

true
