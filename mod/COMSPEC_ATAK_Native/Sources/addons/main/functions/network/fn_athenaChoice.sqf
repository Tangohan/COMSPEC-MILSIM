/*
    Revoir le choix du service Athéna depuis le téléphone (Réglages > Service Athéna).
    Params : ["show"] ouvre le téléphone sur Réglages (question Oui / Non) ; ["notify"] premier lancement en mission
    (Overwatch, XEH_postInitClient) : notification qui mène à Réglages, au lieu de l'ancienne fenêtre par-dessus le jeu ;
    ["on" | "off"] applique directement ; ["link"] ouvre Connexion Athena.
    Sans téléphone (Zeus, unité sans ATAK) : rien ne s'affiche, la question reste au menu principal et au menu Échap.
*/
params [["_what", "show"]];
switch (_what) do {
    case "show": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        if (isNull ([] call comspec_atak_native_fnc_display)) then { [true] call comspec_atak_native_fnc_open; };
        [{ ["SETTINGS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "notify": {
        if !([player] call comspec_atak_native_fnc_hasDevice) exitWith {};
        ["INFO", "Service Athéna : votre unité utilise-t-elle Athéna ? Répondez dans Réglages > Service Athéna.", 15, 50, "SETTINGS"] call comspec_atak_native_fnc_notify;
    };
    case "link": {
        ["show", true] call comspec_atak_native_fnc_athenaLink;
    };
    default {
        if (!isNil "comspec_overwatch_connect_fnc_athenaApply") then {
            [_what] call comspec_overwatch_connect_fnc_athenaApply;
        } else {
            profileNamespace setVariable ["COMSPEC_AthenaMode", _what];
            [] call comspec_atak_native_fnc_saveProfile;
        };
        ["INFO", ["Service Athéna désactivé : ATAK local", "Service Athéna activé"] select (_what isEqualTo "on"), 3, 20] call comspec_atak_native_fnc_notify;
    };
};
[{ [] call comspec_atak_native_fnc_dockRender; if (!isNull ([] call comspec_atak_native_fnc_display)) then { ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
