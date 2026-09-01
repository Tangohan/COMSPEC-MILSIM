/*
    Applique le profil et la communauté renvoyés par Athena. Jamais d’identifiant saisi par le joueur.
*/
private _raw = ["COMSPECExtension" callExtension ["GetAuthState", []]] call comspec_overwatch_connect_fnc_extResult;
private _parts = _raw splitString (toString [9]);
if ((count _parts) < 2) exitWith {};
private _state = _parts select 1;
private _name = if ((count _parts) > 5) then { _parts select 5 } else { "" };
private _cs = if ((count _parts) > 6) then { _parts select 6 } else { "" };
private _tenant = if ((count _parts) > 7) then { _parts select 7 } else { "" };
private _unit = if ((count _parts) > 8) then { _parts select 8 } else { "" };
private _grade = if ((count _parts) > 9) then { _parts select 9 } else { "" };

missionNamespace setVariable ["comspec_overwatch_auth_state", _state, false];
missionNamespace setVariable ["comspec_profile_name", _name, false];
missionNamespace setVariable ["comspec_profile_callsign", _cs, false];
missionNamespace setVariable ["comspec_tenant_name", _tenant, false];
missionNamespace setVariable ["comspec_profile_unit", _unit, false];
missionNamespace setVariable ["comspec_profile_grade", _grade, false];
// Ne plus considérer un identifiant CBA comme autorité.
missionNamespace setVariable ["comspec_overwatch_tenant_id", "", false];

if (!(_cs isEqualTo "")) then {
    [_cs, false, "athena"] call comspec_overwatch_connect_fnc_setCallsign;
};

if (_state isEqualTo "READY") then {
    missionNamespace setVariable ["COMSPEC_AthenaReady", true, false];
    missionNamespace setVariable ["COMSPEC_AthenaReadyAt", diag_tickTime, false];
    missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
    missionNamespace setVariable ["COMSPEC_LinkDetail", format ["%1 — %2", _tenant, _cs], false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    ["COMSPEC_AthenaLinkChanged", ["ready"]] call CBA_fnc_localEvent;
};
