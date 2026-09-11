/*
    Après appairage / Entrer réussi : lever le frein Tx et relancer les boucles
    seulement si elles ne tournent pas déjà (évite d’empiler les PFH → gel).
*/
if (!hasInterface) exitWith { false };

missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_VideoFeedsBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_SendBackoffSec", 0, false];
missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
missionNamespace setVariable ["COMSPEC_OperatorProfileBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_LastFactionSettingsBody", "", false];

[] call comspec_overwatch_connect_fnc_applyBootstrap;
[] call comspec_overwatch_connect_fnc_pollAuth;

// Un AuthInvalidated a pu laisser C2_UNAUTHORIZED alors que le jeton est déjà bon.
private _dllState = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
if (_dllState isEqualTo "READY") then {
    missionNamespace setVariable ["comspec_overwatch_auth_error", "", false];
};

if (!([] call comspec_overwatch_connect_fnc_canStartSync)) exitWith { false };

if (missionNamespace getVariable ["COMSPEC_SyncLoopsStarted", false]) exitWith {
    ["INFO", "Athena", "Canal poste déjà ouvert — freins API levés"] call comspec_overwatch_connect_fnc_log;
    true
};

[] call comspec_overwatch_connect_fnc_startSyncLoops;
["INFO", "Athena", "Canal poste rouvert — transmissions reprises"] call comspec_overwatch_connect_fnc_log;
true
