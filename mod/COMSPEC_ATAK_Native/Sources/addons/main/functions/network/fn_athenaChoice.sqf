/*
    Revoir le choix du service Athéna depuis le téléphone (Réglages > Service Athéna).
    Params : ["show"] rouvre l'écran Oui / Non d'Overwatch ; ["on" | "off"] applique directement ; ["link"] ouvre Connexion Athena.
*/
params [["_what", "show"]];
switch (_what) do {
    case "show": {
        if (!isNil "comspec_overwatch_connect_fnc_athenaChoiceShow") then { [true] call comspec_overwatch_connect_fnc_athenaChoiceShow; };
    };
    case "link": {
        if (!isNil "comspec_overwatch_connect_fnc_accountLinkShow") then { [] call comspec_overwatch_connect_fnc_accountLinkShow; };
    };
    default {
        if (!isNil "comspec_overwatch_connect_fnc_athenaApply") then {
            [_what] call comspec_overwatch_connect_fnc_athenaApply;
        } else {
            profileNamespace setVariable ["COMSPEC_AthenaMode", _what];
            saveProfileNamespace;
        };
        ["INFO", ["Service Athéna désactivé : ATAK local", "Service Athéna activé"] select (_what isEqualTo "on"), 3, 20] call comspec_atak_native_fnc_notify;
    };
};
[{ [] call comspec_atak_native_fnc_dockRender; if (!isNull ([] call comspec_atak_native_fnc_display)) then { ["SETTINGS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
