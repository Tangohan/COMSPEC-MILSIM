/*
    Envoi rapide depuis l’app Athena cTab → canal Athena (+ miroir Iceman via event connect).
*/
params [["_kind", "TIC", [""]]];

if (!hasInterface) exitWith {};
if (isNil "comspec_overwatch_connect_fnc_sendTacticalAlert") exitWith {
    ["ATHENA", "Module Overwatch indisponible.", 5] call cTab_fnc_addNotification;
};

private _kindKey = toUpper (trim _kind);

if (_kindKey isEqualTo "SALUTE" && {!isNil "comspec_overwatch_connect_fnc_saluteDialogShow"}) exitWith {
    [] call comspec_overwatch_connect_fnc_saluteDialogShow;
};

private _body = switch (_kindKey) do {
    case "FRAGO": { "Ordre fragmentaire — détail à préciser sur la tablette." };
    case "SALUTE": { "Compte rendu SALUTE — voir tablette pour le détail." };
    default { "" };
};

[_kindKey, _body, getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;

private _label = switch (_kindKey) do {
    case "TIC": { "Contact" };
    case "FRAGO": { "Ordre fragmentaire" };
    case "SALUTE": { "SALUTE" };
    case "EAGLE_DOWN": { "Opérateur à terre" };
    default { "Alerte" };
};
["ATHENA", format ["%1 transmis vers Athena.", _label], 4] call cTab_fnc_addNotification;

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
