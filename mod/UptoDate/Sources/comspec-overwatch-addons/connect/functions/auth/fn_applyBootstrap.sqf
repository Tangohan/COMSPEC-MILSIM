/*
    Applique le profil et la communauté renvoyés par Athena. Jamais d’identifiant saisi par le joueur.
*/
private _auth = [] call comspec_overwatch_connect_fnc_authStateCells;
private _state = _auth getOrDefault ["state", ""];
private _name = _auth getOrDefault ["name", ""];
private _cs = _auth getOrDefault ["callsign", ""];
private _tenant = _auth getOrDefault ["tenant", ""];
private _unit = _auth getOrDefault ["unit", ""];
private _grade = _auth getOrDefault ["grade", ""];
private _role = _auth getOrDefault ["role", ""];
private _function = _auth getOrDefault ["function", ""];
private _avatar = _auth getOrDefault ["avatar", ""];

missionNamespace setVariable ["comspec_overwatch_auth_state", _state, false];
missionNamespace setVariable ["comspec_profile_name", _name, false];
missionNamespace setVariable ["comspec_tenant_name", _tenant, false];
missionNamespace setVariable ["comspec_profile_unit", _unit, false];
missionNamespace setVariable ["comspec_profile_grade", _grade, false];
missionNamespace setVariable ["comspec_profile_role", _role, false];
missionNamespace setVariable ["comspec_profile_function", _function, false];
if (!(_avatar isEqualTo "")) then {
    missionNamespace setVariable ["comspec_profile_avatar", _avatar, false];
};
// Ne plus considérer un identifiant CBA comme autorité.
missionNamespace setVariable ["comspec_overwatch_tenant_id", "", false];

if ([_cs] call comspec_overwatch_connect_fnc_isUsableCallsign) then {
    missionNamespace setVariable ["comspec_profile_callsign", _cs, false];
    [_cs, true, "athena"] call comspec_overwatch_connect_fnc_setCallsign;
} else {
    missionNamespace setVariable ["comspec_profile_callsign", "", false];
    private _local = missionNamespace getVariable ["COMSPEC_Callsign", ""];
    if (!(_local isEqualType "")) then { _local = ""; };
    if (!([_local] call comspec_overwatch_connect_fnc_isUsableCallsign)) then {
        missionNamespace setVariable ["COMSPEC_Callsign", "", false];
    };
    private _prof = profileNamespace getVariable ["COMSPEC_Callsign", ""];
    if (!(_prof isEqualType "")) then { _prof = ""; };
    if (!([_prof] call comspec_overwatch_connect_fnc_isUsableCallsign)) then {
        profileNamespace setVariable ["COMSPEC_Callsign", ""];
        saveProfileNamespace;
    };
};

if (_state isEqualTo "READY") then {
    private _wasReady = missionNamespace getVariable ["COMSPEC_AthenaReady", false];
    if (!(_wasReady isEqualType true)) then { _wasReady = false; };
    missionNamespace setVariable ["COMSPEC_AthenaReady", true, false];
    missionNamespace setVariable ["COMSPEC_AthenaReadyAt", diag_tickTime, false];
    missionNamespace setVariable ["COMSPEC_LinkState", "linked", false];
    private _linkCs = [true] call comspec_overwatch_connect_fnc_getCallsign;
    private _detail = _tenant;
    if (!(_linkCs isEqualTo "")) then {
        _detail = if (_detail isEqualTo "") then { _linkCs } else { format ["%1 — %2", _tenant, _linkCs] };
    } else {
        if (_detail isEqualTo "") then { _detail = "Opérateur"; };
    };
    missionNamespace setVariable ["COMSPEC_LinkDetail", _detail, false];
    [] call comspec_overwatch_connect_fnc_updateStatusBadges;
    if (!_wasReady) then {
        ["COMSPEC_AthenaLinkChanged", ["ready"]] call CBA_fnc_localEvent;
    };
};
