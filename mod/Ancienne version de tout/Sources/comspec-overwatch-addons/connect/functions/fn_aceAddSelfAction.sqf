/*
    Ajoute une action ACE self-interact de façon isolée.
    - Copie le tableau action (+ _) pour éviter la mutation partagée (reco ACE)
    - Ignore si joueur / API ACE absents
    Retour: true si ajoutée
*/
params [
    ["_action", [], [[]]],
    ["_path", ["ACE_SelfActions"], [[]]]
];

if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };
if (isNil "ace_interact_menu_fnc_addActionToObject") exitWith { false };
if (_action isEqualTo []) exitWith { false };

[player, 1, _path, +_action] call ace_interact_menu_fnc_addActionToObject;
true
