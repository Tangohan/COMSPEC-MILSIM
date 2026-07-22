/*
    Author: COMSPEC
    Description: Menu rapide pour choisir le type d’alerte tactique (vanilla showCommandingMenu).
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

COMSPEC_TacAlert_Menu = [
    ["Signaler une situation", true],
    [
        "Contact",
        [2], "", -5,
        [["expression", "['TIC', '', getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert"]],
        "1", "1"
    ],
    [
        "Fin de contact",
        [3], "", -5,
        [["expression", "['TIC_CLEAR', '', getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert"]],
        "1", "1"
    ],
    [
        "Ordre fragmentaire",
        [4], "", -5,
        [["expression", "['FRAGO', 'Situation — Mission — Exécution — Soutien — Commandement', getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert"]],
        "1", "1"
    ],
    [
        "Compte rendu SALUTE",
        [5], "", -5,
        [["expression", "['SALUTE', 'Taille — Activité — Localisation — Unité — Heure — Équipement', getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert"]],
        "1", "1"
    ],
    [
        "Opérateur à terre",
        [6], "", -5,
        [["expression", "['EAGLE_DOWN', 'Opérateur nécessite assistance immédiate', getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert"]],
        "1", "1"
    ]
];

showCommandingMenu "#USER:COMSPEC_TacAlert_Menu";
