/*
    Après appairage / Entrer réussi : lever le frein Tx et relancer les boucles.
*/
if (!hasInterface) exitWith { false };

missionNamespace setVariable ["COMSPEC_ApiBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_VideoFeedsBackoffUntil", 0, false];
missionNamespace setVariable ["COMSPEC_SendBackoffSec", 0, false];
missionNamespace setVariable ["COMSPEC_ApiBackoffSec", 2, false];
// Autoriser un nouveau démarrage des boucles (sinon restées figées après un 401 précoce).
missionNamespace setVariable ["COMSPEC_SyncLoopsStarted", false, false];
missionNamespace setVariable ["COMSPEC_OperatorProfileSyncStarted", false, false];
missionNamespace setVariable ["COMSPEC_LastFactionSettingsBody", "", false];

[] call comspec_overwatch_connect_fnc_applyBootstrap;
[] call comspec_overwatch_connect_fnc_pollAuth;

if (!([] call comspec_overwatch_connect_fnc_canStartSync)) exitWith { false };

[] call comspec_overwatch_connect_fnc_startSyncLoops;
["INFO", "Athena", "Canal poste rouvert — transmissions reprises"] call comspec_overwatch_connect_fnc_log;
true
