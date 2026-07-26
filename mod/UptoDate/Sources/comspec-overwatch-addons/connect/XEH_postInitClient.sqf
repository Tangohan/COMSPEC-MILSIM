// CBA Extended Event Handler - Post Init Client
// Lancé automatiquement après chargement mission côté client

if (!hasInterface) exitWith {};

// Différer ATAK / tracking : laisse MRH JIP + REAPP + ACE (~3k delayed) respirer
[{
    if (isNull player || {isNull findDisplay 46}) exitWith {
        [{ [] call comspec_overwatch_connect_fnc_initATAK; }, [], 5] call CBA_fnc_waitAndExecute;
    };
    // Grâce proactive si on arrive pile pendant l’écran respawn
    if (!(missionNamespace getVariable ["COMSPEC_MedicalAlertsArmed", false])) then {
        missionNamespace setVariable ["COMSPEC_SuppressWinMessageBoxUntil", (diag_tickTime + 30) max (missionNamespace getVariable ["COMSPEC_SuppressWinMessageBoxUntil", -1e9]), false];
    };
    [] call comspec_overwatch_connect_fnc_initATAK;
}, [], 10] call CBA_fnc_waitAndExecute;
