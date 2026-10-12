#include "script_component.hpp"

if (!hasInterface) exitWith {};

[] call comspec_sse_fnc_registerZenDomexLive;

// Modules ZEN du SSE (dossier, identité, biométrie, SEEK, documents). ZEN peut se
// charger après nous : on attend qu'il soit prêt (45 s au plus).
[{ !isNil "zen_custom_modules_fnc_register" }, {
    [] call comspec_sse_fnc_registerZenSseModules;
}, [], 45] call CBA_fnc_waitUntilAndExecute;
