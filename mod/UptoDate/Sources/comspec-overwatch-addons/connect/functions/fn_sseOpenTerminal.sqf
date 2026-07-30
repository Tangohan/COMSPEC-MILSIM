/*
    Ouvre le terminal SEEK — point d’entrée unique (menu ATAK, interaction ACE, objet).
    Vérifie la possession du terminal avant d’ouvrir la fiche.

    Params: [_target]
*/
params [["_target", objNull, [objNull]]];

if (!hasInterface) exitWith { false };

if (!([] call comspec_overwatch_connect_fnc_sseHasTerminalItem)) exitWith {
    [
        "Terminal SEEK absent — récupérez l’appareil dans votre sac ou votre gilet.",
        "tactical",
        "warn"
    ] call comspec_overwatch_connect_fnc_announce;
    false
};

[_target] call comspec_overwatch_connect_fnc_ssePersonDialogShow;
true
