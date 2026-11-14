/*
    CLIENT (Zeus). Module Zeus Enhanced « COMSPEC Lobby > Code du poste » : pose, change ou retire le code d'un poste (mode local, sans
    Athena ni fichier). Posé sur une unité : l'étiquette est lue dans sa description de rôle ([A-1|P]) ; posé ailleurs : saisie
    de l'étiquette. Quatre chiffres ; vide : le poste n'a plus de code venu de Zeus.
    Sans ZEN : écrire le code dans userconfig\comspec_lobby\pins.txt (une ligne ÉTIQUETTE=CODE, lue au démarrage du serveur) ou, depuis
    la console du serveur, ["comspec_lobby_pinSet", ["A-1", "4821"]] call CBA_fnc_localEvent.
    [] call comspec_lobby_fnc_zenInit
*/
if (!hasInterface) exitWith {};
[{ !isNil "zen_custom_modules_fnc_register" && {!isNil "zen_dialog_fnc_create"} }, {
    [localize "STR_COMSPEC_LOBBY_ZenCategory", localize "STR_COMSPEC_LOBBY_ZenModule", {
        params [["_posASL", [0, 0, 0]], ["_obj", objNull]];
        private _tag = "";
        if (!isNull _obj) then { _tag = ([roleDescription _obj] call comspec_lobby_fnc_tagParse) select 0; };
        [localize "STR_COMSPEC_LOBBY_ZenModule", [
            ["EDIT", [localize "STR_COMSPEC_LOBBY_ZenTag", localize "STR_COMSPEC_LOBBY_ZenTagTip"], [_tag, 1]],
            ["EDIT", [localize "STR_COMSPEC_LOBBY_ZenPin", localize "STR_COMSPEC_LOBBY_ZenPinTip"], ["", 1]]
        ], {
            params ["_values"];
            _values params ["_tag", "_pin"];
            ["comspec_lobby_pinSet", [_tag, _pin]] call CBA_fnc_serverEvent;
        }, {}, []] call zen_dialog_fnc_create;
    }, "\a3\ui_f\data\igui\cfg\simpletasks\types\lock_ca.paa"] call zen_custom_modules_fnc_register;
}, [], 60] call CBA_fnc_waitUntilAndExecute;
