/*
    Envoi rapide depuis l’app Athena cTab → canal Athena (+ miroir Iceman via event connect).
*/
params [["_kind", "TIC", [""]]];

if (!hasInterface) exitWith {};
if (isNil "comspec_overwatch_connect_fnc_sendTacticalAlert") exitWith {
    ["ATHENA", "Module Overwatch indisponible.", 5] call comspec_overwatch_connect_fnc_addScreenToast;
};

private _kindKey = toUpper (trim _kind);
if (_kindKey in ["CLEAR", "TIC CLEAR", "TICCLEAR"]) then { _kindKey = "TIC_CLEAR"; };

private _grid = mapGridPosition player;
private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };
private _timeStr = [daytime, "HH:MM"] call BIS_fnc_timeToString;

private _body = switch (_kindKey) do {
    case "FRAGO": { "" };
    case "BDA": { "Bilan des dégâts à la position actuelle." };
    case "EAGLE_DOWN": { "Opérateur nécessite une assistance immédiate." };
    case "TIC": { "Contact ennemi signalé." };
    case "TIC_CLEAR": { "Fin de contact." };
    default { "" };
};

// SALUTE : formulaire structuré (pas d’envoi instantané vide)
if (_kindKey isEqualTo "SALUTE"
    && { !isNil "comspec_overwatch_connect_fnc_saluteDialogShow" }
) exitWith {
    [] call comspec_overwatch_connect_fnc_saluteDialogShow;
};

// FRAGO : ouvrir la mini-fenêtre de rédaction si activée (chefs d’unité)
if (_kindKey isEqualTo "FRAGO"
    && { missionNamespace getVariable ["comspec_overwatch_order_compose_enabled", true] }
    && { !isNil "comspec_overwatch_connect_fnc_orderComposeShow" }
) exitWith {
    ["FRAGO"] call comspec_overwatch_connect_fnc_orderComposeShow;
};

// Appui aérien / manifeste / briefing : formulaires dédiés
if (_kindKey isEqualTo "CAS" || {_kindKey isEqualTo "APPUIAERIEN"} || {_kindKey isEqualTo "APPUI"}) exitWith {
    [] call comspec_overwatch_connect_fnc_casRequestShow;
};
if (_kindKey isEqualTo "MANIFEST" || {_kindKey isEqualTo "MANIFESTE"} || {_kindKey isEqualTo "FLIGHT"}) exitWith {
    [] call comspec_overwatch_connect_fnc_flightManifestShow;
};
if (_kindKey isEqualTo "BRIEFING" || {_kindKey isEqualTo "BRIEF"}) exitWith {
    [] call comspec_overwatch_atak_athena_fnc_athena_openBriefing;
};

[_kindKey, _body, getPos player] call comspec_overwatch_connect_fnc_sendTacticalAlert;

private _label = switch (_kindKey) do {
    case "TIC": { "Contact" };
    case "TIC_CLEAR": { "Fin de contact" };
    case "FRAGO": { "Ordre fragmentaire" };
    case "SALUTE": { "SALUTE" };
    case "EAGLE_DOWN": { "Opérateur à terre" };
    case "BDA": { "Bilan des dégâts" };
    default { "Alerte" };
};
["ATHENA", format ["%1 transmis vers Athena.", _label], 4] call comspec_overwatch_connect_fnc_addScreenToast;

[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;

