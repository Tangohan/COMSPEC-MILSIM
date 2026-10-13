/*
    Ouvre la fenêtre Dépannage liaison (diagnostic immédiat, corrections, pas à pas).
    Ferme d'abord le menu Échap et le panneau de gestion : une fenêtre ouverte par-dessus la pause
    peut rester inerte. Appelé par le menu Échap, le panneau de gestion du mod et l'action ACE.
*/
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };

["INFO", "Diag", "Dépannage liaison demandé"] call comspec_overwatch_connect_fnc_log;

{
    private _d = findDisplay _x;
    if (!isNull _d) then { _d closeDisplay 1; };
} forEach [9979, 9995, 49];

[] spawn {
    uiSleep 0.25;
    if (!hasInterface || {isNull player}) exitWith {};
    if (!isNull (uiNamespace getVariable ["COMSPEC_DiagIsolate_Display", displayNull])) exitWith {};
    createDialog "COMSPEC_DiagIsolate_Dialog";
};

true
