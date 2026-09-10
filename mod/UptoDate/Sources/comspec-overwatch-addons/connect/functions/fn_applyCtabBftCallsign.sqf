/*
    Recopie l’indicatif Athena dans l’emplacement BFT cTab (unité + véhicule)
    et le mémorise sur le profil. Sans cet écrit, le suivi d’effectif garde
    le nom de groupe Arma et l’oublie au changement de véhicule / relance.
*/
params [["_callsign", "", [""]]];

if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };

_callsign = trim _callsign;
if (_callsign isEqualTo "") then {
    _callsign = [true] call comspec_overwatch_connect_fnc_getCallsign;
};
if (!([_callsign] call comspec_overwatch_connect_fnc_isUsableCallsign)) exitWith { false };

private _fncSame = {
    params ["_a", "_b"];
    if (!(_a isEqualType "")) then { _a = str _a; };
    if (!(_b isEqualType "")) then { _b = str _b; };
    (toLower (trim _a)) isEqualTo (toLower (trim _b))
};

private _cur = player getVariable ["cTab_groupId", ""];
if (!([_cur, _callsign] call _fncSame)) then {
    player setVariable ["cTab_groupId", _callsign, true];
};

private _veh = vehicle player;
if (_veh isNotEqualTo player && {!isNull _veh}) then {
    private _vehCs = _veh getVariable ["cTab_groupId", ""];
    if (!(_vehCs isEqualType "")) then { _vehCs = str _vehCs; };
    _vehCs = trim _vehCs;
    private _prev = profileNamespace getVariable ["COMSPEC_CtabBftCallsign", ""];
    if (!(_prev isEqualType "")) then { _prev = str _prev; };
    _prev = trim _prev;
    if (
        _vehCs isEqualTo ""
        || {[_vehCs, _callsign] call _fncSame}
        || {[_vehCs, _prev] call _fncSame}
    ) then {
        _veh setVariable ["cTab_groupId", _callsign, true];
    };
};

private _stored = profileNamespace getVariable ["COMSPEC_CtabBftCallsign", ""];
if (!([_stored, _callsign] call _fncSame)) then {
    profileNamespace setVariable ["COMSPEC_CtabBftCallsign", _callsign];
    saveProfileNamespace;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_relabelBft") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
};

true
