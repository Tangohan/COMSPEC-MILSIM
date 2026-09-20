/*
    Identité Athena pour un ordre C2.
    Jamais le nom de profil Arma (ex. NewPI).
*/
if (!hasInterface) exitWith { "Operateur" };

private _athena = missionNamespace getVariable ["comspec_profile_name", ""];
if (!(_athena isEqualType "")) then { _athena = str _athena; };
_athena = trim _athena;
if (_athena isNotEqualTo "") exitWith { _athena };

private _cs = [true] call comspec_overwatch_connect_fnc_getCallsign;
if (_cs isNotEqualTo "") exitWith { _cs };

"Operateur"
