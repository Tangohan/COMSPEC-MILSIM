#include "script_component.hpp"
/*
    Tutoriels : manuel de terrain (ACE, Zeus), exercice Zeus.
*/
if (isServer) then {
    ["comspec_sse_guideExercise", { params ["_pos", ["_req", objNull]]; [_pos, _req] call comspec_sse_fnc_guideExercise; }] call CBA_fnc_addEventHandler;
};
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

[{ !isNil "ace_interact_menu_fnc_createAction" && {!isNull player} }, {
    private _mk = {
        ["COMSPEC_SSE_Guide", "Guide SSE (manuel)", "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa",
            { ["COMSPEC_SSE", "Start"] call BIS_fnc_openFieldManual }, { true }, { [] }, [], {[0, 0, 0]}, 1, [false, false, false, false, true]] call comspec_sse_fnc_aceAction
    };
    [player, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], call _mk] call ace_interact_menu_fnc_addActionToObject;
    missionNamespace setVariable ["comspec_sse_guideActionMk", _mk];
    player addEventHandler ["Respawn", {
        params ["_unit"];
        [_unit, 1, ["ACE_SelfActions", "COMSPEC_SSE_SELF"], call (missionNamespace getVariable "comspec_sse_guideActionMk")] call ace_interact_menu_fnc_addActionToObject;
    }];
}, [], 60] call CBA_fnc_waitUntilAndExecute;

[{ !isNil "zen_custom_modules_fnc_register" }, {
    ["COMSPEC SSE · Outils", "Tutoriel : manuel du Zeus", { ["Zeus"] call comspec_sse_fnc_openGuide }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa"] call comspec_sse_fnc_zenModule;
    ["COMSPEC SSE · Outils", "Tutoriel : choisir un chapitre", {
        if (isNil "zen_dialog_fnc_create") exitWith { ["Zeus"] call comspec_sse_fnc_openGuide; };
        private _ids = ["Zeus", "ModTools", "ModMission", "ModPeople", "ModDigital", "Datasets", "CaseRef", "Campaigns", "Documents", "Items", "Models", "Eden"];
        private _lbls = _ids apply { getText (configFile >> "CfgHints" >> "COMSPEC_SSE" >> _x >> "displayName") };
        [
            ("Manuel SSE") call comspec_sse_fnc_tr,
            [["COMBO", ("Chapitre") call comspec_sse_fnc_tr, [_ids, _lbls, 0]]],
            { params ["_v"]; [_v select 0] call comspec_sse_fnc_openGuide; }
        ] call comspec_sse_fnc_zenDialog;
    }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\documents_ca.paa"] call comspec_sse_fnc_zenModule;
    ["COMSPEC SSE · Outils", "Tutoriel : poser un exercice ici", {
        params ["_pos"];
        [ASLToAGL _pos, player] call comspec_sse_fnc_guideExercise;
        // Retour immédiat dans Zeus ; la consigne complète arrive quand le serveur a posé la cellule.
        if (!isNil "zen_common_fnc_showMessage") then { [("Exercice en cours de pose : 5 civils, téléphone, documents et caisse.") call comspec_sse_fnc_tr] call zen_common_fnc_showMessage; };
    }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa"] call comspec_sse_fnc_zenModule;
}, [], 45] call CBA_fnc_waitUntilAndExecute;
