#include "script_component.hpp"
/*
    Passerelle DL-7 : réglages Eden appliqués au lancement (serveur), menus Zeus (ZEN).
*/
if (isServer) then {
    // Supports numériques : génération serveur puis câble DL-7 automatique dès que leurs appareils existent.
    ["comspec_sse_recordChanged", {
        params ["_e", ["_changed", []]];
        if (isNull _e || {_e isKindOf "CAManBase"}) exitWith {};
        if ("s:digitalDevices" in _changed) exitWith { [_e] call comspec_sse_fnc_dl7Auto; };
        private _data = [_e] call comspec_sse_fnc_getData;
        if (!isNil "_data" && {([_data, "type", ""] call comspec_sse_fnc_getPair) in ["PHONE", "SMARTPHONE", "COMPUTER", "LAPTOP", "TABLET", "MEDIA"]} && {!([_data, "lazyReady", false] call comspec_sse_fnc_getPair)}) then {
            [{ [_this] call comspec_sse_fnc_ensureGenerated; }, _e, 0.5] call CBA_fnc_waitAndExecute;
        };
    }] call CBA_fnc_addEventHandler;
    // Téléchargement terminé : résultats versés au dossier.
    ["comspec_atak_native_dataDownloaded", { _this call comspec_sse_fnc_dl7Downloaded; }] call CBA_fnc_addEventHandler;
    // Après la génération SSE initiale : les appareils existent quand on lit leurs données.
    [{ [] call comspec_sse_fnc_dl7ApplyEden; }, [], 3] call CBA_fnc_waitAndExecute;
};
if (!hasInterface) exitWith {};
// Photos de preuve par l'app Photo de l'ATAK.
[] call comspec_sse_fnc_photoWatch;
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
