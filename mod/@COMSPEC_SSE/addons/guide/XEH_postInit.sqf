#include "script_component.hpp"
/*
    Tutoriels : manuel de terrain (ACE, Zeus), exercice Zeus.
*/
// Exercice Zeus : appel distant de comspec_sse_fnc_guideExercise (liste blanche CfgRemoteExec, Zeus ou administrateur
// seulement). Plus d'événement CBA portant un demandeur choisi par le client.
if (!hasInterface) exitWith {};

// Steam du joueur gardé dans le profil : la connexion Athena et le temps passé dans Eden (comspec_sse_fnc_athenaEden)
// s'en servent dans l'éditeur, où il n'y a pas de joueur.
[{ !isNull player }, {
    private _uid = getPlayerUID player;
    if (_uid regexMatch "^[0-9]{17}$" && {(profileNamespace getVariable ["comspec_sse_steam_uid", ""]) isNotEqualTo _uid}) then {
        profileNamespace setVariable ["comspec_sse_steam_uid", _uid];
        saveProfileNamespace;
    };
}, [], 120] call CBA_fnc_waitUntilAndExecute;

// Guide SSE : bouton de l'onglet JOURNAL de l'app « SSE terrain » (plus d'action ACE personnelle).

[{ !isNil "zen_custom_modules_fnc_register" }, {
    ["COMSPEC_Cat_Sse", "Ouvrir le manuel", { ["Zeus"] call comspec_sse_fnc_openGuide }, "\z\comspec_sse\addons\zeus\data\mod_doc.paa"] call comspec_sse_fnc_zenModule;
    ["COMSPEC_Cat_Sse", "Choisir un chapitre du manuel", {
        if (isNil "zen_dialog_fnc_create") exitWith { ["Zeus"] call comspec_sse_fnc_openGuide; };
        // Tous les chapitres du MANUEL SSE, dans l'ordre de config (pages des outils d'abord).
        private _ids = ("true" configClasses (configFile >> "CfgHints" >> "COMSPEC_SSE")) apply { configName _x };
        private _lbls = _ids apply { getText (configFile >> "CfgHints" >> "COMSPEC_SSE" >> _x >> "displayName") };
        [
            ("Manuel SSE") call comspec_sse_fnc_tr,
            [["COMBO", ("Chapitre") call comspec_sse_fnc_tr, [_ids, _lbls, 0]]],
            { params ["_v"]; [_v select 0] call comspec_sse_fnc_openGuide; }
        ] call comspec_sse_fnc_zenDialog;
    }, "\z\comspec_sse\addons\zeus\data\mod_doc.paa"] call comspec_sse_fnc_zenModule;
    ["COMSPEC_Cat_Sse", "Poser un exercice", {
        params ["_pos"];
        [ASLToAGL _pos, player] call comspec_sse_fnc_guideExercise;
        // Retour immédiat dans Zeus ; la consigne complète arrive quand le serveur a posé la cellule.
        if (!isNil "zen_common_fnc_showMessage") then { [("Exercice en cours de pose : 5 civils, téléphone, documents et caisse.") call comspec_sse_fnc_tr] call zen_common_fnc_showMessage; };
    }, "\z\comspec_sse\addons\zeus\data\mod_check.paa"] call comspec_sse_fnc_zenModule;
}, [], 45] call CBA_fnc_waitUntilAndExecute;
