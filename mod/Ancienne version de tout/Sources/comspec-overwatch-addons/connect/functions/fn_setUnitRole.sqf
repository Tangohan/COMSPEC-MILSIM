/*
    Enregistre le rôle tactique du joueur local (mission + profil).
    Params: [_role, _persistProfile]
*/
params [
    ["_role", "", [""]],
    ["_persistProfile", true, [true]]
];

if (!hasInterface) exitWith { false };

_role = trim _role;
if ((count _role) > 64) then { _role = _role select [0, 64]; };

missionNamespace setVariable ["COMSPEC_Role", _role, false];
player setVariable ["COMSPEC_Role", _role, true];

if (_persistProfile) then {
    profileNamespace setVariable ["COMSPEC_Role", _role];
    saveProfileNamespace;
};

missionNamespace setVariable ["COMSPEC_lastRole", "", false]; // force renvoi position
true
