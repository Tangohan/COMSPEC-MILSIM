#include "script_component.hpp"
/*
    Équipement, toutes machines. Serveur : objets marqués « caisse de kits » dans Eden (attributs d'objet, liste
    remplie par leurs expressions) rendus caisses une seconde après le lancement, tenue collée vérifiée.
*/
if (!isServer) exitWith {};
[{
    {
        if (!isNull _x) then {
            ["crate", [_x, _x getVariable ["comspec_sse_loCrateName", ""], _x getVariable ["comspec_sse_loCrateText", ""]]] call comspec_sse_fnc_loadoutTake;
        };
    } forEach (missionNamespace getVariable ["comspec_sse_loPending", []]);
    missionNamespace setVariable ["comspec_sse_loPending", []];
}, [], 1] call CBA_fnc_waitAndExecute;
