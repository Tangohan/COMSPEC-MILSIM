/*
    Canal C2 (transmissions ATAK) utilisable.
    READY avec erreur C2_DEGRADED / C2_UNAUTHORIZED = compte lié mais Tx coupées.
*/
private _state = missionNamespace getVariable ["comspec_overwatch_auth_state", ""];
if !(_state isEqualTo "READY") exitWith { false };

private _err = toUpper (missionNamespace getVariable ["comspec_overwatch_auth_error", ""]);
if (_err isEqualTo "C2_DEGRADED") exitWith { false };
if (_err isEqualTo "C2_UNAUTHORIZED") exitWith { false };
if ((_err find "C2_") == 0) exitWith { false };

true
