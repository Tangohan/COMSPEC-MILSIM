/*
    Canal C2 (transmissions ATAK) — info diagnostic.
    Le handshake et les boucles suivent isReady (READY), comme le pack Workshop
    du 06-09-2026. Cette fonction reste disponible pour l’UI / diagnostics.
*/
private _state = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
if !(_state isEqualTo "READY") exitWith { false };

private _err = toUpper (missionNamespace getVariable ["comspec_overwatch_auth_error", ""]);
if (_err isEqualTo "") exitWith { true };
if ((_err find "C2_") == 0) exitWith { false };

true
