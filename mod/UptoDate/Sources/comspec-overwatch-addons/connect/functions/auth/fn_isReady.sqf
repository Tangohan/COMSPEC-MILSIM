/*
    Session Athena prête pour les transmissions (compte + canal C2).
    Profil / indicatif peuvent déjà être appliqués via applyBootstrap si state=READY
    même en C2_DEGRADED ; les boucles Tx exigent isC2Ok.
*/
private _state = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
if !(_state isEqualTo "READY") exitWith { false };
[] call comspec_overwatch_connect_fnc_isC2Ok
