/*
    Attend AUTH_READY (session Athena restaurée ou connexion réussie).
    Aucun flux opérationnel tant que comspec_overwatch_auth_state != READY.
*/
if (!hasInterface) exitWith { false };

missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];
missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "Connexion à Athena…", false];
missionNamespace setVariable ["COMSPEC_HandshakeQuiet", true, false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

[] call comspec_overwatch_connect_fnc_initAuth;

private _deadline = diag_tickTime + 180;
while {diag_tickTime < _deadline} do {
    if ([] call comspec_overwatch_connect_fnc_isReady) then { break };
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    uiSleep 0.5;
};

missionNamespace setVariable ["COMSPEC_HandshakeQuiet", false, false];

if ([] call comspec_overwatch_connect_fnc_isReady) then {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    ["INFO", "Athena", "Session Athena prête"] call comspec_overwatch_connect_fnc_log;
    true
} else {
    missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", "Connexion Athena requise", false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    ["WARN", "Athena", "Pas de session — les transmissions restent coupées"] call comspec_overwatch_connect_fnc_log;
    ["Connectez-vous à Athena pour activer la carte, le suivi et les transmissions.", "link", "warn"] call comspec_overwatch_connect_fnc_announce;
    false
};
