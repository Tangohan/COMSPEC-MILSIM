/*
    Rôle personnalisé du joueur local (ancien réglage web / Overwatch). Même stockage que « Rôle affiché » de l'app
    Profil de l'ATAK natif : texte non vide = rôle personnalisé, vide = retour à la fonction de la communauté.
    Params: [_role, _persistProfile]
*/
params [
    ["_role", "", [""]],
    ["_persistProfile", true, [true]]
];

if (!hasInterface) exitWith { false };

_role = trim _role;
if ((count _role) > 40) then { _role = _role select [0, 40]; };

profileNamespace setVariable ["COMSPEC_ATAK_RoleMode", ["custom", "athena"] select (_role isEqualTo "")];
profileNamespace setVariable ["COMSPEC_ATAK_RoleCustom", _role];
if (_persistProfile) then { [] call comspec_overwatch_connect_fnc_saveProfile; };

missionNamespace setVariable ["COMSPEC_lastRole", "", false]; // force renvoi position
true
