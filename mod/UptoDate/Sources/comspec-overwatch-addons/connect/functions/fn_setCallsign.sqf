/*
    Enregistre l’indicatif tactique (mission + profil).
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

// Limite lisible (alignée préférences Athena ~50)
if ((count _callsign) > 50) then {
    _callsign = _callsign select [0, 50];
};

missionNamespace setVariable ["COMSPEC_Callsign", _callsign, false];

if (_persistProfile) then {
    profileNamespace setVariable ["COMSPEC_Callsign", _callsign];
    saveProfileNamespace;
};

// Manifeste / ops aériennes lisent souvent la variable véhicule
private _veh = vehicle player;
if (_veh != player && {driver _veh == player}) then {
    _veh setVariable ["COMSPEC_Callsign", _callsign, true];
};

// Blue Force Tracking : aligner le nom de groupe sur l’indicatif, sauf depuis
// l’écran Paramètres (le joueur peut lier un autre groupe ATAK sans le renommer).
if (_source isNotEqualTo "settings" && {!isNull player} && {local player}) then {
    private _grp = group player;
    if (!isNull _grp && {local _grp}) then {
        private _curGid = trim (groupId _grp);
        if (!(_curGid isEqualTo _callsign)) then {
            _grp setGroupIdGlobal [_callsign];
        };
    };
};

[format ["[Athena] Callsign registered : %1 (%2)", _callsign, _source]] call comspec_overwatch_connect_fnc_appendLinkLog;

true
