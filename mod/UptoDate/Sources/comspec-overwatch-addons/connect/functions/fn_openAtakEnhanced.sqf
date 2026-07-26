/*
    Ouvre le téléphone tactique ATAK Enhanced (cTab Android).
    Point d’entrée principal quand l’interface Overwatch hors ATAK est désactivée.
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith {
    ["COMSPEC_Warning", ["Terminal ATAK manquant — emportez votre téléphone ou tablette tactique pour synchroniser et ouvrir l’interface."]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

// Ouverture standard cTab / ATAK Enhanced (même touche que le téléphone Android)
if (!isNil "cTab_fnc_onIfMainPressed") exitWith {
    [] call cTab_fnc_onIfMainPressed;
    true
};

if (!isNil "cTab_fnc_open") exitWith {
    [0, "cTab_Android_dlg", player, vehicle player] call cTab_fnc_open;
    true
};

["COMSPEC_Warning", [
    "Le téléphone tactique ATAK n’est pas disponible. Vérifiez que ATAK Enhanced est bien chargé avec Overwatch."
]] call comspec_overwatch_connect_fnc_showNotification;
false
