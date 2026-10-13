#include "script_component.hpp"
/*
    Passerelle DL-7 : réglages Eden appliqués au lancement (serveur), menus Zeus (ZEN).
*/
if (isServer) then {
    // Après la génération SSE initiale : les appareils existent quand on lit leurs données.
    [{ [] call comspec_sse_fnc_dl7ApplyEden; }, [], 3] call CBA_fnc_waitAndExecute;
};
if (!hasInterface) exitWith {};
[{ !isNil "zen_custom_modules_fnc_register" }, {
    [
        "COMSPEC SSE · Numérique",
        "Rendre extractible au DL-7",
        {
            params ["_pos", "_obj"];
            private _sel = [] call comspec_sse_fnc_curatorSelectedObjects;
            if (!isNull _obj) then { _sel pushBackUnique _obj; };
            [_sel] call comspec_sse_fnc_dl7ZeusForm;
        },
        "\A3\ui_f\data\igui\cfg\simpletasks\types\download_ca.paa"
    ] call comspec_sse_fnc_zenModule;
}, [], 45] call CBA_fnc_waitUntilAndExecute;
