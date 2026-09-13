/*
    Ouvre le terminal SEEK — point d’entrée unique (menu ATAK, interaction ACE, objet).
    Vérifie la possession du terminal avant d’ouvrir la fiche.

    Params: [_target, _page]
      _page  page d'ouverture (0 = accueil). Permet d'aller droit au dossier.
*/
params [["_target", objNull, [objNull]], ["_page", 0, [0]]];

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

// L'ouverture pose la page 0 ; on bascule ensuite si une autre est demandée.
if (_page > 0) then {
    [{ [_this] call comspec_overwatch_connect_fnc_sseTerminalPage; }, _page, 0.05] call CBA_fnc_waitAndExecute;
};
true
