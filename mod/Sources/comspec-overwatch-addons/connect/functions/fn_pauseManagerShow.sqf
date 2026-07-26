/*
    Ouvre (ou ferme si déjà ouvert) le panneau "Gestion du mod" COMSPEC Overwatch.
    Appelé depuis le bouton ajouté au menu Échap (RscDisplayInterrupt).
    UI HTML dédiée (web/pause_manager.html) — indépendante de la tablette Athena (idd 9974).
*/

if (!hasInterface) exitWith {};

if (!isNull (findDisplay 9979)) exitWith {
    (findDisplay 9979) closeDisplay 0;
};

private _ok = createDialog "COMSPEC_PauseManager_Dialog";
if (!_ok) exitWith {
    ["COMSPEC_Warning", ["Impossible d'ouvrir le panneau COMSPEC Overwatch."]] call comspec_overwatch_connect_fnc_showNotification;
};
