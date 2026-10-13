#include "script_component.hpp"
/*
    Tutoriels : manuel de terrain (ACE, Zeus), exercice Zeus.
*/
if (isServer) then {
    ["comspec_sse_guideExercise", { params ["_pos", ["_req", objNull]]; [_pos, _req] call comspec_sse_fnc_guideExercise; }] call CBA_fnc_addEventHandler;
};
if (!hasInterface) exitWith {};

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
    ["COMSPEC SSE · Outils", "Tutoriel : poser un exercice ici", {
        params ["_pos"];
        [ASLToAGL _pos, player] call comspec_sse_fnc_guideExercise;
        // Retour immédiat dans Zeus ; la consigne complète arrive quand le serveur a posé la cellule.
        if (!isNil "zen_common_fnc_showMessage") then { [("Exercice en cours de pose : 5 civils, téléphone, documents et caisse.") call comspec_sse_fnc_tr] call zen_common_fnc_showMessage; };
    }, "\a3\ui_f\data\igui\cfg\simpleTasks\types\meet_ca.paa"] call comspec_sse_fnc_zenModule;
}, [], 45] call CBA_fnc_waitUntilAndExecute;
