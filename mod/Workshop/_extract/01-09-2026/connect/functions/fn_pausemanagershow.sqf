/*
    Ouvre (ou ferme si déjà ouvert) le panneau "Gestion du mod" COMSPEC Overwatch.
    Appelé depuis le bouton ajouté au menu Échap (RscDisplayInterrupt).
    UI HTML dédiée (web/pause_manager.html) — indépendante de la tablette Athena (idd 9974).
*/

if (!hasInterface) exitWith {};

if (!isNull (findDisplay 9979)) exitWith {
    ["DEBUG", "Pause", "Panneau déjà ouvert — fermeture"] call comspec_overwatch_connect_fnc_log;
    (findDisplay 9979) closeDisplay 0;
};

["INFO", "Pause", "Ouverture panneau Gestion du mod"] call comspec_overwatch_connect_fnc_log;

private _ok = createDialog "COMSPEC_PauseManager_Dialog";
if (!_ok) exitWith {
    ["ERROR", "Pause", "createDialog COMSPEC_PauseManager_Dialog a échoué"] call comspec_overwatch_connect_fnc_log;
    ["COMSPEC_Warning", ["Impossible d'ouvrir le panneau COMSPEC Overwatch."]] call comspec_overwatch_connect_fnc_showNotification;
};
