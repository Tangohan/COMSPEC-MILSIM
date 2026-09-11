/*
    Attend AUTH_READY avec canal C2 OK (session restaurée ou loginSteam).
    Aucun flux opérationnel tant que comspec_overwatch_auth_state != READY
    ou que l’erreur C2_DEGRADED / C2_UNAUTHORIZED est posée.
    La fenêtre de connexion ne s’ouvre pas ici (tuile Connexion Athena en secours).
*/
if (!hasInterface) exitWith { false };

missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];
missionNamespace setVariable ["COMSPEC_LinkState", "connecting", false];
missionNamespace setVariable ["COMSPEC_LinkDetail", "Connexion à Athena…", false];
missionNamespace setVariable ["COMSPEC_HandshakeQuiet", true, false];
[] call comspec_overwatch_connect_fnc_updateStatusBadges;

[] call comspec_overwatch_connect_fnc_initAuth;

private _deadline = diag_tickTime + 20;
private _sawAccountReady = false;
while {diag_tickTime < _deadline} do {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    if ([] call comspec_overwatch_connect_fnc_isReady) then { break };
    private _st = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
    if (_st isEqualTo "READY") then { _sawAccountReady = true; };
    uiSleep 0.5;
};

missionNamespace setVariable ["COMSPEC_HandshakeQuiet", false, false];

if ([] call comspec_overwatch_connect_fnc_isReady) then {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    ["INFO", "Athena", "Session Athena prête"] call comspec_overwatch_connect_fnc_log;
    true
} else {
    if (_sawAccountReady) then {
        private _err = missionNamespace getVariable ["comspec_overwatch_auth_error", ""];
        ["WARN", "Athena", format ["Compte lié — transmissions coupées (%1)", _err]] call comspec_overwatch_connect_fnc_log;
        ["Compte Athena lié, mais les transmissions vers le poste restent coupées. Réessayez Connexion Athena, ou Lier le jeu.", "link", "warn"] call comspec_overwatch_connect_fnc_announce;
    } else {
        missionNamespace setVariable ["COMSPEC_LinkState", "offline", false];
        missionNamespace setVariable ["COMSPEC_LinkDetail", "Connexion Athena requise", false];
        [] call comspec_overwatch_connect_fnc_updateStatusBadges;
        ["WARN", "Athena", "Pas de session — les transmissions restent coupées"] call comspec_overwatch_connect_fnc_log;
        ["Ouvrez le téléphone ATAK, tuile Connexion Athena, si la liaison Steam n’a pas abouti.", "link", "warn"] call comspec_overwatch_connect_fnc_announce;
    };
    false
};
