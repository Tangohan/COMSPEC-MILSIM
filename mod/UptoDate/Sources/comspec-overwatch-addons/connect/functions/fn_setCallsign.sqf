/*
    Enregistre l’indicatif tactique (mission + profil).
    Ne renomme pas le groupe Arma : l’indicatif n’est pas le groupe en jeu.
    Params: [_callsign, _persistProfile (défaut true), _source (optionnel)]
*/
params [
    ["_callsign", "", [""]],
    ["_persistProfile", true, [true]],
    ["_source", "local", [""]]
];

if (!hasInterface) exitWith { false };

_callsign = trim _callsign;
if (_callsign isEqualTo "") exitWith { false };

if (!([_callsign] call comspec_overwatch_connect_fnc_isUsableCallsign)) exitWith { false };

if ((count _callsign) > 40) then {
    _callsign = _callsign select [0, 40];
};

missionNamespace setVariable ["COMSPEC_Callsign", _callsign, false];

if (_persistProfile) then {
    profileNamespace setVariable ["COMSPEC_Callsign", _callsign];
    saveProfileNamespace;
};

private _veh = vehicle player;
if (_veh != player && {driver _veh == player}) then {
    _veh setVariable ["COMSPEC_Callsign", _callsign, true];
};

[format ["[Athena] Callsign registered : %1 (%2)", _callsign, _source]] call comspec_overwatch_connect_fnc_appendLinkLog;

if (
    missionNamespace getVariable ["COMSPEC_AthenaReady", false]
    && {!isNil "comspec_overwatch_connect_fnc_operatorProfileTick"}
) then {
    [{
        ["callsign_changed"] call comspec_overwatch_connect_fnc_operatorProfileTick;
    }, [], 0.8] call CBA_fnc_waitAndExecute;
};

true
