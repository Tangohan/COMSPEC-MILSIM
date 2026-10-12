/*
    Enregistre et applique le choix du service Athéna (profil Arma, COMSPEC_AthenaMode).
    "off" : Overwatch coupé (comspec_overwatch_enabled = false), plus de session ni de synchro ;
            la clé enregistrée est gardée pour pouvoir revenir à « Oui » sans relier le compte.
    "on"  : Overwatch actif ; la session est restaurée (ou la liaison demandée) et les synchros repartent.
    Params : ["on" | "off"]. Retour : true si le choix est valide.
*/
params [["_mode", ""]];
if (!(_mode isEqualType "")) exitWith { false };
_mode = toLower _mode;
if !(_mode in ["on", "off"]) exitWith { false };

private _prev = [] call comspec_overwatch_connect_fnc_athenaMode;
profileNamespace setVariable ["COMSPEC_AthenaMode", _mode];
saveProfileNamespace;
["INFO", "Athena", format ["Service Athéna : %1 (avant : %2)", _mode, [_prev, "aucun"] select (_prev isEqualTo "")]] call comspec_overwatch_connect_fnc_log;

if (_mode isEqualTo "off") then {
    missionNamespace setVariable ["comspec_overwatch_enabled", false];
    missionNamespace setVariable ["comspec_overwatch_auth_state", "DISABLED", false];
    missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Service Athéna désactivé", false];
    missionNamespace setVariable ["COMSPEC_AuthInitStarted", false, false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    ["COMSPEC_AthenaLinkChanged", ["off"]] call CBA_fnc_localEvent;
} else {
    missionNamespace setVariable ["comspec_overwatch_enabled", true];
    if ((missionNamespace getVariable ["comspec_overwatch_auth_state", ""]) isEqualTo "DISABLED") then {
        missionNamespace setVariable ["comspec_overwatch_auth_state", "INITIALIZING", false];
    };
    missionNamespace setVariable ["COMSPEC_LinkDetail", "", false];
    // Au démarrage de mission, le handshake attend ce choix (XEH_postInit) : il fait lui-même la suite.
    if !(missionNamespace getVariable ["COMSPEC_AthenaChoiceWaiting", false]) then {
        if (isNull findDisplay 46) then {
            0 spawn { [] call comspec_overwatch_connect_fnc_initAuth; };
        } else {
            0 spawn {
                if !([] call comspec_overwatch_connect_fnc_isReady) then {
                    [] call comspec_overwatch_connect_fnc_waitAthenaReady;
                } else {
                    [] call comspec_overwatch_connect_fnc_startSyncLoops;
                };
            };
        };
    };
};
["COMSPEC_AthenaModeChanged", [_mode, _prev]] call CBA_fnc_localEvent;
true
